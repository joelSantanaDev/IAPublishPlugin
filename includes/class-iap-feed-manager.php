<?php

if (!defined('ABSPATH')) {
    exit;
}

class IAP_Feed_Manager {
    
    public function get_all_feeds() {
        global $wpdb;
        $table = $wpdb->prefix . 'iap_feeds';
        
        return $wpdb->get_results("SELECT * FROM $table ORDER BY name ASC");
    }
    
    public function get_feed($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'iap_feeds';
        
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    }
    
    public function save_feed($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'iap_feeds';
        
        $feed_data = [
            'name' => sanitize_text_field($data['name']),
            'url' => esc_url_raw($data['url']),
            'status' => isset($data['status']) ? sanitize_text_field($data['status']) : 'active'
        ];
        
        if (isset($data['id']) && !empty($data['id'])) {
            $wpdb->update($table, $feed_data, ['id' => intval($data['id'])]);
            return intval($data['id']);
        } else {
            $wpdb->insert($table, $feed_data);
            return $wpdb->insert_id;
        }
    }
    
    public function delete_feed($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'iap_feeds';
        
        return $wpdb->delete($table, ['id' => intval($id)]);
    }
    
    public function fetch_feed($feed_url) {
        $feed = fetch_feed($feed_url);
        
        if (is_wp_error($feed)) {
            return ['success' => false, 'message' => $feed->get_error_message()];
        }
        
        $items = [];
        foreach ($feed->get_items(0, 10) as $item) {
            $image_url = $this->extract_image_from_item($item);
            $description = $item->get_description();
            $content = $item->get_content();
            
            // Se descrição e conteúdo forem muito curtos (menos de 200 caracteres), fazer scraping
            $desc_length = strlen(strip_tags($description));
            $content_length = strlen(strip_tags($content));
            
            if ($desc_length < 200 && $content_length < 200) {
                error_log("IAP: Descrição curta detectada ({$desc_length} chars), fazendo scraping do link: " . $item->get_permalink());
                $scraping_result = $this->scrape_article_content($item->get_permalink());
                
                if (!empty($scraping_result['content'])) {
                    $content = $scraping_result['content'];
                    error_log("IAP: Scraping bem-sucedido, conteúdo extraído: " . strlen($scraping_result['content']) . " chars");
                }
                
                // Se encontrou imagem og:image no scraping e não tinha imagem no feed, usar ela
                if (!empty($scraping_result['og_image']) && empty($image_url)) {
                    $image_url = $scraping_result['og_image'];
                    error_log("IAP: Imagem og:image encontrada no scraping: " . $image_url);
                }
            }
            
            $items[] = [
                'title' => $item->get_title(),
                'link' => $item->get_permalink(),
                'description' => $description,
                'content' => $content,
                'date' => $item->get_date('Y-m-d H:i:s'),
                'author' => $item->get_author() ? $item->get_author()->get_name() : '',
                'image' => $image_url
            ];
        }
        
        return ['success' => true, 'items' => $items];
    }
    
    public function fetch_multiple_feeds($feed_ids, $limit_per_feed = 5, $integration_id = null, $order = 'recent') {
        $all_items = [];
        
        foreach ($feed_ids as $feed_id) {
            $feed = $this->get_feed($feed_id);
            if (!$feed) {
                continue;
            }
            
            $result = $this->fetch_feed($feed->url);
            if ($result['success']) {
                foreach ($result['items'] as $item) {
                    $item['feed_id'] = $feed_id;
                    $item['feed_name'] = $feed->name;
                    
                    // Verificar se já foi processado
                    if ($integration_id && $this->is_item_processed($item['link'], $integration_id)) {
                        error_log('IAP: Item já processado, pulando: ' . $item['title']);
                        continue;
                    }
                    
                    $all_items[] = $item;
                }
            }
            
            global $wpdb;
            $table = $wpdb->prefix . 'iap_feeds';
            $wpdb->update($table, ['last_fetch' => current_time('mysql')], ['id' => $feed_id]);
        }
        
        // Ordenar conforme configuração
        if ($order === 'random') {
            shuffle($all_items);
            error_log('IAP: Itens embaralhados aleatoriamente');
        } else {
            // Ordenar por data (mais recentes primeiro)
            usort($all_items, function($a, $b) {
                return strtotime($b['date']) - strtotime($a['date']);
            });
            error_log('IAP: Itens ordenados por data (mais recentes primeiro)');
        }
        
        // Limitar quantidade de itens
        if (count($all_items) > $limit_per_feed) {
            $all_items = array_slice($all_items, 0, $limit_per_feed);
        }
        
        return $all_items;
    }
    
    public function is_item_processed($item_url, $integration_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'iap_processed_items';
        
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE feed_item_url = %s AND integration_id = %d",
            $item_url,
            $integration_id
        ));
        
        return !empty($exists);
    }
    
    public function mark_item_as_processed($item_url, $item_title, $feed_id, $integration_id, $post_id = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'iap_processed_items';
        
        $wpdb->insert($table, [
            'feed_item_url' => $item_url,
            'feed_item_title' => $item_title,
            'feed_id' => $feed_id,
            'integration_id' => $integration_id,
            'post_id' => $post_id
        ]);
        
        error_log('IAP: Item marcado como processado: ' . $item_title);
    }
    
    private function extract_image_from_item($item) {
        // Tentar pegar imagem de diferentes fontes do feed
        
        // 1. Media:thumbnail (comum em feeds)
        $enclosure = $item->get_enclosure();
        if ($enclosure && $enclosure->get_thumbnail()) {
            return $this->clean_image_url($enclosure->get_thumbnail());
        }
        
        // 2. Media:content
        if ($enclosure && $enclosure->get_link()) {
            $type = $enclosure->get_type();
            if ($type && strpos($type, 'image/') === 0) {
                return $this->clean_image_url($enclosure->get_link());
            }
        }
        
        // 3. Procurar por tags de imagem no conteúdo
        $content = $item->get_content();
        if (empty($content)) {
            $content = $item->get_description();
        }
        
        if (!empty($content)) {
            // Procurar primeira tag <img>
            if (preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches)) {
                return $this->clean_image_url($matches[1]);
            }
        }
        
        // 4. Tentar pegar de meta tags Open Graph (se disponível no link)
        // Não vamos fazer requisição HTTP aqui para não deixar lento
        
        return null;
    }
    
    private function clean_image_url($url) {
        if (empty($url)) {
            return null;
        }
        
        // Decodificar entidades HTML (&amp; -> &)
        $url = html_entity_decode($url);
        
        // Remover query string para pegar imagem original em alta qualidade
        // Exemplo: image.jpg?fit=300,200&quality=70 -> image.jpg
        $parsed = parse_url($url);
        
        if (!$parsed) {
            return $url;
        }
        
        // Reconstruir URL sem query string
        $clean_url = '';
        
        if (isset($parsed['scheme'])) {
            $clean_url .= $parsed['scheme'] . '://';
        }
        
        if (isset($parsed['host'])) {
            $clean_url .= $parsed['host'];
        }
        
        if (isset($parsed['port'])) {
            $clean_url .= ':' . $parsed['port'];
        }
        
        if (isset($parsed['path'])) {
            $clean_url .= $parsed['path'];
        }
        
        error_log('IAP: URL de imagem limpa - Original: ' . $url . ' | Limpa: ' . $clean_url);
        
        return $clean_url;
    }
    
    private function scrape_article_content($url) {
        try {
            // Fazer requisição HTTP
            $response = wp_remote_get($url, [
                'timeout' => 15,
                'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
            ]);
            
            if (is_wp_error($response)) {
                error_log('IAP: Erro ao fazer scraping: ' . $response->get_error_message());
                return ['content' => '', 'og_image' => ''];
            }
            
            $html = wp_remote_retrieve_body($response);
            
            if (empty($html)) {
                error_log('IAP: HTML vazio retornado do scraping');
                return ['content' => '', 'og_image' => ''];
            }
            
            // Usar DOMDocument para parsear HTML
            libxml_use_internal_errors(true);
            $dom = new DOMDocument();
            $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
            libxml_clear_errors();
            
            $content = '';
            $og_image = '';
            
            // Extrair og:image das meta tags
            $og_image = $this->extract_og_image($dom);
            
            // Tentar extrair conteúdo de tags comuns de artigos
            $selectors = [
                'article',
                '.article-content',
                '.post-content',
                '.entry-content',
                '.content',
                'main article',
                '[itemprop="articleBody"]',
                '.story-body',
                '.article-body'
            ];
            
            foreach ($selectors as $selector) {
                $xpath = new DOMXPath($dom);
                
                // Converter seletor CSS para XPath
                $xpath_query = $this->css_to_xpath($selector);
                $nodes = $xpath->query($xpath_query);
                
                if ($nodes->length > 0) {
                    $node = $nodes->item(0);
                    $content = $this->extract_text_from_node($node);
                    
                    if (strlen($content) > 300) {
                        error_log("IAP: Conteúdo extraído usando seletor: {$selector}");
                        break;
                    }
                }
            }
            
            // Se não encontrou com seletores específicos, tentar pegar todos os parágrafos
            if (strlen($content) < 300) {
                $paragraphs = $dom->getElementsByTagName('p');
                $text_parts = [];
                
                foreach ($paragraphs as $p) {
                    $text = trim($p->textContent);
                    if (strlen($text) > 50) { // Ignorar parágrafos muito curtos
                        $text_parts[] = $text;
                    }
                }
                
                $content = implode("\n\n", $text_parts);
                error_log("IAP: Conteúdo extraído de parágrafos gerais");
            }
            
            // Limpar conteúdo
            $content = strip_tags($content);
            $content = preg_replace('/\s+/', ' ', $content);
            $content = trim($content);
            
            return [
                'content' => $content,
                'og_image' => $og_image
            ];
            
        } catch (Exception $e) {
            error_log('IAP: Exceção no scraping: ' . $e->getMessage());
            return ['content' => '', 'og_image' => ''];
        }
    }
    
    private function css_to_xpath($css_selector) {
        // Conversão básica de CSS para XPath
        $xpath = $css_selector;
        
        // Converter classes (.class)
        $xpath = preg_replace('/\.([a-zA-Z0-9_-]+)/', "*[contains(concat(' ', normalize-space(@class), ' '), ' $1 ')]", $xpath);
        
        // Converter IDs (#id)
        $xpath = preg_replace('/#([a-zA-Z0-9_-]+)/', "*[@id='$1']", $xpath);
        
        // Converter atributos ([attr="value"])
        $xpath = preg_replace('/\[([a-zA-Z0-9_-]+)="([^"]+)"\]/', "*[@$1='$2']", $xpath);
        
        // Adicionar // no início se não tiver
        if (substr($xpath, 0, 2) !== '//') {
            $xpath = '//' . $xpath;
        }
        
        return $xpath;
    }
    
    private function extract_text_from_node($node) {
        $text = '';
        
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $text .= $child->textContent . ' ';
            } elseif ($child->nodeType === XML_ELEMENT_NODE) {
                // Ignorar scripts, styles, etc
                if (!in_array($child->nodeName, ['script', 'style', 'nav', 'header', 'footer', 'aside'])) {
                    $text .= $this->extract_text_from_node($child) . ' ';
                }
            }
        }
        
        return $text;
    }
    
    private function extract_og_image($dom) {
        try {
            $xpath = new DOMXPath($dom);
            
            // Tentar diferentes meta tags de imagem
            $meta_queries = [
                "//meta[@property='og:image']/@content",
                "//meta[@property='og:image:secure_url']/@content",
                "//meta[@name='twitter:image']/@content",
                "//meta[@name='twitter:image:src']/@content",
                "//meta[@itemprop='image']/@content"
            ];
            
            foreach ($meta_queries as $query) {
                $nodes = $xpath->query($query);
                
                if ($nodes->length > 0) {
                    $image_url = $nodes->item(0)->nodeValue;
                    
                    if (!empty($image_url)) {
                        // Validar se é URL válida
                        if (filter_var($image_url, FILTER_VALIDATE_URL)) {
                            error_log("IAP: og:image encontrada: {$image_url}");
                            return $image_url;
                        }
                    }
                }
            }
            
            error_log("IAP: Nenhuma og:image encontrada");
            return '';
            
        } catch (Exception $e) {
            error_log('IAP: Erro ao extrair og:image: ' . $e->getMessage());
            return '';
        }
    }
}
