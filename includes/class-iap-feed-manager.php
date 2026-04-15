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
            
            $items[] = [
                'title' => $item->get_title(),
                'link' => $item->get_permalink(),
                'description' => $item->get_description(),
                'content' => $item->get_content(),
                'date' => $item->get_date('Y-m-d H:i:s'),
                'author' => $item->get_author() ? $item->get_author()->get_name() : '',
                'image' => $image_url
            ];
        }
        
        return ['success' => true, 'items' => $items];
    }
    
    public function fetch_multiple_feeds($feed_ids, $limit_per_feed = 5, $integration_id = null) {
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
        
        usort($all_items, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
        
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
}
