<?php

if (!defined('ABSPATH')) {
    exit;
}

class IAP_Integration_Manager {
    
    private $ai_manager;
    private $feed_manager;
    
    public function __construct() {
        $this->ai_manager = new IAP_AI_Manager();
        $this->feed_manager = new IAP_Feed_Manager();
    }
    
    public function get_all_integrations() {
        global $wpdb;
        $table = $wpdb->prefix . 'iap_integrations';
        
        return $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC");
    }
    
    public function get_integration($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'iap_integrations';
        
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    }
    
    public function save_integration($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'iap_integrations';
        
        $integration_data = [
            'name' => sanitize_text_field($data['name']),
            'category_id' => intval($data['category_id']),
            'ai_provider' => sanitize_text_field($data['ai_provider']),
            'ai_config' => json_encode($data['ai_config']),
            'feed_ids' => json_encode($data['feed_ids']),
            'custom_prompt' => isset($data['custom_prompt']) ? sanitize_textarea_field($data['custom_prompt']) : '',
            'feed_items_count' => isset($data['feed_items_count']) ? intval($data['feed_items_count']) : 3,
            'feed_order' => isset($data['feed_order']) ? sanitize_text_field($data['feed_order']) : 'recent',
            'fallback_image_id' => isset($data['fallback_image_id']) ? $data['fallback_image_id'] : null,
            'post_status' => isset($data['post_status']) ? sanitize_text_field($data['post_status']) : 'draft',
            'status' => isset($data['status']) ? sanitize_text_field($data['status']) : 'active',
            'schedule_frequency' => isset($data['schedule_frequency']) ? sanitize_text_field($data['schedule_frequency']) : 'hourly'
        ];
        
        if (isset($data['id']) && !empty($data['id'])) {
            $integration_id = intval($data['id']);
            $wpdb->update($table, $integration_data, ['id' => $integration_id]);
        } else {
            $wpdb->insert($table, $integration_data);
            $integration_id = $wpdb->insert_id;
        }
        
        // Atualizar agendamento individual desta integração
        $this->schedule_integration($integration_id, $integration_data['schedule_frequency'], $integration_data['status']);
        
        return $integration_id;
    }
    
    private function schedule_integration($integration_id, $frequency, $status) {
        $hook = 'iap_run_integration_' . $integration_id;
        
        // Remover agendamento anterior
        $timestamp = wp_next_scheduled($hook);
        if ($timestamp) {
            wp_unschedule_event($timestamp, $hook);
        }
        
        // Se ativo, criar novo agendamento
        if ($status === 'active') {
            wp_schedule_event(time(), $frequency, $hook);
            error_log("IAP: Agendamento criado para integração #{$integration_id} - Frequência: {$frequency}");
        } else {
            error_log("IAP: Agendamento removido para integração #{$integration_id} (inativa)");
        }
    }
    
    public function delete_integration($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'iap_integrations';
        
        // Remover agendamento desta integração
        $hook = 'iap_run_integration_' . $id;
        $timestamp = wp_next_scheduled($hook);
        if ($timestamp) {
            wp_unschedule_event($timestamp, $hook);
            error_log("IAP: Agendamento removido para integração #{$id} (deletada)");
        }
        
        return $wpdb->delete($table, ['id' => intval($id)]);
    }
    
    public function run_integration($integration_id) {
        $integration = $this->get_integration($integration_id);
        
        if (!$integration) {
            return ['success' => false, 'message' => 'Integração não encontrada'];
        }
        
        $feed_ids = json_decode($integration->feed_ids, true);
        if (empty($feed_ids)) {
            return ['success' => false, 'message' => 'Nenhum feed configurado'];
        }
        
        $items_count = isset($integration->feed_items_count) ? intval($integration->feed_items_count) : 3;
        $feed_order = isset($integration->feed_order) ? $integration->feed_order : 'recent';
        
        $feed_items = $this->feed_manager->fetch_multiple_feeds($feed_ids, $items_count, $integration_id, $feed_order);
        
        if (empty($feed_items)) {
            return ['success' => false, 'message' => 'Nenhum item encontrado nos feeds (todos já foram processados ou feeds vazios)'];
        }
        
        $custom_prompt = !empty($integration->custom_prompt) ? $integration->custom_prompt : '';
        $prompt = $this->build_prompt($feed_items, $custom_prompt);
        
        $ai_config = json_decode($integration->ai_config, true);
        $result = $this->ai_manager->generate_content($integration->ai_provider, $ai_config, $prompt);
        
        if (!$result['success']) {
            $this->log_action($integration_id, null, 'generate_failed', 'error', $result['message']);
            return $result;
        }
        
        $post_data = $this->parse_ai_response($result['content']);
        
        // Log da resposta da IA para debug
        error_log('=== IAP AI Response Content ===');
        error_log('Title: ' . $post_data['title']);
        error_log('Content length: ' . strlen($post_data['content']));
        error_log('Content preview: ' . substr($post_data['content'], 0, 200));
        error_log('Tags from AI: ' . implode(', ', $post_data['tags']));
        error_log('=== End AI Response ===');
        
        // Usar tags da IA ou gerar como fallback
        $tags = !empty($post_data['tags']) ? $post_data['tags'] : $this->generate_tags($post_data['title'], $post_data['content'], $feed_items);
        
        // Usar post_status configurado na integração (padrão: draft)
        $post_status = !empty($integration->post_status) ? $integration->post_status : 'draft';
        
        error_log("IAP: Criando post com status: {$post_status}");
        
        $post_id = wp_insert_post([
            'post_title' => $post_data['title'],
            'post_content' => $post_data['content'],
            'post_status' => $post_status,
            'post_category' => [$integration->category_id],
            'post_author' => get_current_user_id(),
            'tags_input' => $tags
        ]);
        
        if (is_wp_error($post_id)) {
            $this->log_action($integration_id, null, 'post_creation_failed', 'error', $post_id->get_error_message());
            return ['success' => false, 'message' => $post_id->get_error_message()];
        }
        
        error_log("IAP: Post #{$post_id} criado com status: {$post_status}");
        
        // Tentar importar imagem destacada de um dos feeds
        $featured_image_id = $this->import_featured_image($post_id, $feed_items, $integration->fallback_image_id);
        
        // Adicionar meta SEO (Rank Math)
        $this->add_seo_meta($post_id, $post_data['title'], $post_data['content'], $featured_image_id, $tags);
        
        // Marcar todos os itens usados como processados
        foreach ($feed_items as $item) {
            $this->feed_manager->mark_item_as_processed(
                $item['link'],
                $item['title'],
                $item['feed_id'],
                $integration_id,
                $post_id
            );
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'iap_integrations';
        $wpdb->update($table, ['last_run' => current_time('mysql')], ['id' => $integration_id]);
        
        $sources_info = $this->format_sources_info($feed_items);
        $log_message = "Post criado com sucesso\n\n" . $sources_info;
        $this->log_action($integration_id, $post_id, 'post_created', 'success', $log_message, $feed_items);
        
        return [
            'success' => true,
            'message' => 'Notícia criada com sucesso',
            'post_id' => $post_id,
            'post_title' => $post_data['title']
        ];
    }
    
    public function run_all_active_integrations() {
        $integrations = $this->get_all_integrations();
        
        foreach ($integrations as $integration) {
            if ($integration->status === 'active') {
                $this->run_integration($integration->id);
            }
        }
    }
    
    private function build_prompt($feed_items, $custom_prompt = '') {
        $prompt = "Crie uma notícia original baseada nestas fontes:\n\n";
        
        foreach ($feed_items as $index => $item) {
            $content = strip_tags($item['content'] ?: $item['description']);
            // Limitar conteúdo a 500 caracteres para economizar tokens
            $content = substr($content, 0, 500) . '...';
            
            $prompt .= "Fonte " . ($index + 1) . " ({$item['feed_name']}):\n";
            $prompt .= "Título: {$item['title']}\n";
            $prompt .= "Resumo: {$content}\n\n";
        }
        
        $prompt .= "Instruções:\n";
        $prompt .= "- Crie um título original e atraente\n";
        $prompt .= "- Sugira 5 tags relevantes (palavras-chave principais do tema)\n";
        $prompt .= "- Escreva o conteúdo com mínimo 600 palavras em HTML\n";
        $prompt .= "- Use títulos HTML(H1,H2,H3) para seções, <p> para parágrafos, <ul>/<ol> para listas, <strong> para destaques. Preciso que a semântica pro SEO seja importante.\n";
        $prompt .= "- Tom jornalístico profissional\n";
        $prompt .= "- Combine as fontes de forma coerente caso você consuma mais de 1 feed\n";
        $prompt .= "- Conteúdo 100% original, mas não viaje tanto na criatividade.\n";
        
        if (!empty($custom_prompt)) {
            $prompt .= "\nPersonalização: " . $custom_prompt . "\n";
        }
        
        $prompt .= "\n⚠️ IMPORTANTE - Formato de Resposta:\n\n";
        $prompt .= "TÍTULO: [apenas o título, sem repetir no conteúdo]\n\n";
        $prompt .= "TAGS: [tag1, tag2, tag3, tag4, tag5]\n\n";
        $prompt .= "CONTEÚDO:\n";
        $prompt .= "[Comece direto com o HTML do conteúdo. NÃO repita o título aqui. NÃO inclua as tags aqui. Apenas o corpo do artigo em HTML]";
        
        return $prompt;
    }
    
    private function parse_ai_response($content) {
        $title = '';
        $body = '';
        $tags = [];
        
        // Tentar diferentes formatos de resposta
        
        // Formato 1: TÍTULO: ... TAGS: ... CONTEÚDO: ...
        if (preg_match('/TÍTULO:\s*(.+?)(?:\n|$)/i', $content, $title_match)) {
            $title = trim($title_match[1]);
            
            // Extrair tags se existirem
            if (preg_match('/TAGS:\s*(.+?)(?:\n|CONTEÚDO:)/is', $content, $tags_match)) {
                $tags_string = trim($tags_match[1]);
                // Separar por vírgula e limpar
                $tags = array_map('trim', explode(',', $tags_string));
                $tags = array_filter($tags); // Remover vazios
            }
            
            if (preg_match('/CONTEÚDO:\s*(.+)/is', $content, $content_match)) {
                $body = trim($content_match[1]);
            }
        }
        
        // Formato 2: Se não encontrou, tentar extrair primeiro H1 como título
        if (empty($title) && preg_match('/<h1[^>]*>(.+?)<\/h1>/is', $content, $h1_match)) {
            $title = strip_tags($h1_match[1]);
            $body = preg_replace('/<h1[^>]*>(.+?)<\/h1>/is', '', $content, 1);
        }
        
        // Formato 3: Se ainda não encontrou, usar primeira linha como título
        if (empty($title)) {
            $lines = explode("\n", $content);
            $first_line = trim($lines[0]);
            
            // Se primeira linha não é HTML, usar como título
            if (!empty($first_line) && strpos($first_line, '<') === false) {
                $title = $first_line;
                array_shift($lines);
                $body = implode("\n", $lines);
            } else {
                // Usar todo conteúdo como body
                $body = $content;
            }
        }
        
        // Se ainda não tem título, gerar um
        if (empty($title)) {
            $title = 'Nova Notícia - ' . date('Y-m-d H:i:s');
        }
        
        // Se não tem body, usar o conteúdo completo
        if (empty($body)) {
            $body = $content;
        }
        
        // Converter Markdown para HTML se necessário
        $body = $this->markdown_to_html($body);
        
        return [
            'title' => trim($title),
            'content' => trim($body),
            'tags' => $tags
        ];
    }
    
    private function markdown_to_html($text) {
        // Se já tem tags HTML, não converter
        if (preg_match('/<(p|h[1-6]|ul|ol|li|strong|em|div)[\s>]/i', $text)) {
            return $text;
        }
        
        // Converter Markdown para HTML
        
        // Headers (### Título -> <h3>Título</h3>)
        $text = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $text);
        $text = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $text);
        $text = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $text);
        
        // Bold (**texto** -> <strong>texto</strong>)
        $text = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text);
        $text = preg_replace('/__(.+?)__/s', '<strong>$1</strong>', $text);
        
        // Italic (*texto* -> <em>texto</em>)
        $text = preg_replace('/\*(.+?)\*/s', '<em>$1</em>', $text);
        $text = preg_replace('/_(.+?)_/s', '<em>$1</em>', $text);
        
        // Listas não ordenadas (- item -> <ul><li>item</li></ul>)
        $text = preg_replace_callback('/(?:^|\n)((?:[-*+] .+\n?)+)/m', function($matches) {
            $items = preg_replace('/^[-*+] (.+)$/m', '<li>$1</li>', trim($matches[1]));
            return "\n<ul>\n" . $items . "\n</ul>\n";
        }, $text);
        
        // Listas ordenadas (1. item -> <ol><li>item</li></ol>)
        $text = preg_replace_callback('/(?:^|\n)((?:\d+\. .+\n?)+)/m', function($matches) {
            $items = preg_replace('/^\d+\. (.+)$/m', '<li>$1</li>', trim($matches[1]));
            return "\n<ol>\n" . $items . "\n</ol>\n";
        }, $text);
        
        // Links ([texto](url) -> <a href="url">texto</a>)
        $text = preg_replace('/\[(.+?)\]\((.+?)\)/', '<a href="$2">$1</a>', $text);
        
        // Parágrafos (linhas vazias separam parágrafos)
        $paragraphs = preg_split('/\n\s*\n/', $text);
        $html_paragraphs = [];
        
        foreach ($paragraphs as $para) {
            $para = trim($para);
            if (empty($para)) continue;
            
            // Se já é uma tag HTML (h1-h6, ul, ol), não envolver em <p>
            if (preg_match('/^<(h[1-6]|ul|ol|div|blockquote)[\s>]/i', $para)) {
                $html_paragraphs[] = $para;
            } else {
                $html_paragraphs[] = '<p>' . $para . '</p>';
            }
        }
        
        return implode("\n\n", $html_paragraphs);
    }
    
    private function format_sources_info($feed_items) {
        $info = "Fontes utilizadas:\n";
        $info .= str_repeat('=', 50) . "\n\n";
        
        foreach ($feed_items as $index => $item) {
            $info .= "Fonte " . ($index + 1) . ":\n";
            $info .= "Feed: " . $item['feed_name'] . "\n";
            $info .= "Título: " . $item['title'] . "\n";
            $info .= "Link: " . $item['link'] . "\n";
            $info .= "Data: " . (isset($item['date']) ? $item['date'] : 'N/A') . "\n";
            
            if (!empty($item['image'])) {
                $info .= "Imagem: " . $item['image'] . "\n";
            }
            
            $info .= str_repeat('-', 50) . "\n\n";
        }
        
        return $info;
    }
    
    private function import_featured_image($post_id, $feed_items, $fallback_image_id = null) {
        // Procurar primeira imagem disponível nos feeds
        $image_url = null;
        $image_title = '';
        
        foreach ($feed_items as $item) {
            if (!empty($item['image'])) {
                $image_url = $item['image'];
                $image_title = $item['title'];
                break;
            }
        }
        
        // Se não encontrou imagem nos feeds, usar fallback
        if (empty($image_url) && !empty($fallback_image_id)) {
            error_log('IAP: Nenhuma imagem encontrada nos feeds, usando imagem fallback #' . $fallback_image_id);
            set_post_thumbnail($post_id, $fallback_image_id);
            return $fallback_image_id;
        }
        
        if (empty($image_url)) {
            error_log('IAP: Nenhuma imagem encontrada nos feeds e sem fallback configurado para o post #' . $post_id);
            return null;
        }
        
        error_log('IAP: Tentando importar imagem: ' . $image_url);
        
        // Baixar e importar imagem
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        // Usar media_sideload_image para baixar e importar
        $image_id = media_sideload_image($image_url, $post_id, $image_title, 'id');
        
        if (is_wp_error($image_id)) {
            error_log('IAP: Erro ao importar imagem: ' . $image_id->get_error_message());
            return null;
        }
        
        // Definir como imagem destacada
        $result = set_post_thumbnail($post_id, $image_id);
        
        if ($result) {
            error_log('IAP: Imagem destacada definida com sucesso para o post #' . $post_id . ' (attachment #' . $image_id . ')');
            
            // Adicionar SEO para a imagem
            $this->add_image_seo($image_id, $image_title);
            
            return $image_id;
        } else {
            error_log('IAP: Falha ao definir imagem destacada para o post #' . $post_id);
            return null;
        }
    }
    
    private function add_seo_meta($post_id, $title, $content, $featured_image_id = null, $tags = []) {
        // Verificar se Rank Math está ativo
        if (!class_exists('RankMath')) {
            error_log('IAP: Rank Math não está instalado/ativo - pulando SEO');
            return;
        }
        
        // Gerar meta título (máx 60 caracteres)
        $meta_title = $this->generate_meta_title($title);
        
        // Gerar meta descrição (máx 160 caracteres)
        $meta_description = $this->generate_meta_description($content);
        
        // Extrair focus keyword do título
        $focus_keyword = $this->extract_focus_keyword($title);
        
        // Salvar meta dados do Rank Math
        update_post_meta($post_id, 'rank_math_title', $meta_title);
        update_post_meta($post_id, 'rank_math_description', $meta_description);
        update_post_meta($post_id, 'rank_math_focus_keyword', $focus_keyword);
        
        // Adicionar tags como pillar content (conteúdo pilar) no Rank Math
        if (!empty($tags)) {
            update_post_meta($post_id, 'rank_math_pillar_content', 'on');
            // Salvar tags também como meta para uso futuro do Rank Math
            update_post_meta($post_id, 'rank_math_internal_links_processed', '1');
        }
        
        // Configurações adicionais do Rank Math
        update_post_meta($post_id, 'rank_math_robots', ['index', 'follow']);
        update_post_meta($post_id, 'rank_math_advanced_robots', ['noimageindex' => 'off', 'noarchive' => 'off', 'nosnippet' => 'off']);
        
        // Se tem imagem destacada, adicionar Open Graph
        if ($featured_image_id) {
            update_post_meta($post_id, 'rank_math_facebook_enable_image_overlay', 'off');
            update_post_meta($post_id, 'rank_math_facebook_image', wp_get_attachment_url($featured_image_id));
            update_post_meta($post_id, 'rank_math_facebook_image_id', $featured_image_id);
            update_post_meta($post_id, 'rank_math_twitter_enable_image_overlay', 'off');
            update_post_meta($post_id, 'rank_math_twitter_image', wp_get_attachment_url($featured_image_id));
            update_post_meta($post_id, 'rank_math_twitter_image_id', $featured_image_id);
        }
        
        error_log("IAP: SEO meta adicionado ao post #{$post_id} - Keyword: {$focus_keyword} - Tags: " . implode(', ', $tags));
    }
    
    private function generate_meta_title($title) {
        // Limitar a 60 caracteres para SEO
        $meta_title = strip_tags($title);
        
        if (strlen($meta_title) > 60) {
            $meta_title = substr($meta_title, 0, 57) . '...';
        }
        
        return $meta_title;
    }
    
    private function generate_meta_description($content) {
        // Extrair primeiro parágrafo ou primeiras 160 caracteres
        $text = strip_tags($content);
        $text = preg_replace('/\s+/', ' ', $text); // Remover espaços extras
        $text = trim($text);
        
        if (strlen($text) > 160) {
            $text = substr($text, 0, 157) . '...';
        }
        
        return $text;
    }
    
    private function extract_focus_keyword($title) {
        // Extrair palavras-chave principais do título
        $title = strip_tags($title);
        $title = strtolower($title);
        
        // Remover palavras comuns (stop words)
        $stop_words = ['o', 'a', 'os', 'as', 'de', 'da', 'do', 'das', 'dos', 'em', 'no', 'na', 'nos', 'nas', 'para', 'com', 'por', 'e', 'ou'];
        $words = explode(' ', $title);
        $keywords = [];
        
        foreach ($words as $word) {
            $word = trim($word, '.,!?;:');
            if (strlen($word) > 3 && !in_array($word, $stop_words)) {
                $keywords[] = $word;
            }
        }
        
        // Retornar primeiras 2-3 palavras-chave
        return implode(' ', array_slice($keywords, 0, 3));
    }
    
    private function add_image_seo($image_id, $source_title) {
        // Gerar alt text otimizado
        $alt_text = $this->generate_image_alt($source_title);
        
        // Atualizar alt text
        update_post_meta($image_id, '_wp_attachment_image_alt', $alt_text);
        
        // Atualizar título e descrição da imagem
        wp_update_post([
            'ID' => $image_id,
            'post_title' => $alt_text,
            'post_excerpt' => $this->generate_meta_description($source_title), // Caption
            'post_content' => $source_title // Descrição
        ]);
        
        error_log("IAP: SEO da imagem #{$image_id} configurado - Alt: {$alt_text}");
    }
    
    private function generate_image_alt($title) {
        // Criar alt text descritivo
        $alt = strip_tags($title);
        $alt = preg_replace('/\s+/', ' ', $alt);
        $alt = trim($alt);
        
        // Limitar tamanho
        if (strlen($alt) > 125) {
            $alt = substr($alt, 0, 122) . '...';
        }
        
        return $alt;
    }
    
    private function generate_tags($title, $content, $feed_items) {
        $tags = [];
        
        // 1. Extrair palavras-chave do título
        $title_keywords = $this->extract_keywords_from_text($title, 3);
        $tags = array_merge($tags, $title_keywords);
        
        // 2. Extrair palavras-chave do conteúdo
        $content_keywords = $this->extract_keywords_from_text($content, 5);
        $tags = array_merge($tags, $content_keywords);
        
        // 3. Extrair tags dos títulos das fontes
        foreach ($feed_items as $item) {
            $source_keywords = $this->extract_keywords_from_text($item['title'], 2);
            $tags = array_merge($tags, $source_keywords);
        }
        
        // 4. Remover duplicatas e limitar a 10 tags
        $tags = array_unique($tags);
        $tags = array_filter($tags, function($tag) {
            return strlen($tag) > 3; // Mínimo 4 caracteres
        });
        
        // Ordenar por relevância (mais frequentes primeiro)
        $tag_counts = array_count_values($tags);
        arsort($tag_counts);
        $tags = array_keys($tag_counts);
        
        // Limitar a 10 tags
        $tags = array_slice($tags, 0, 10);
        
        error_log('IAP: Tags geradas: ' . implode(', ', $tags));
        
        return $tags;
    }
    
    private function extract_keywords_from_text($text, $limit = 5) {
        // Limpar texto
        $text = strip_tags($text);
        $text = strtolower($text);
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text); // Remover pontuação
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Stop words em português
        $stop_words = [
            'o', 'a', 'os', 'as', 'um', 'uma', 'uns', 'umas',
            'de', 'da', 'do', 'das', 'dos', 'em', 'no', 'na', 'nos', 'nas',
            'por', 'para', 'com', 'sem', 'sob', 'sobre',
            'e', 'ou', 'mas', 'porém', 'contudo',
            'que', 'qual', 'quais', 'quando', 'onde', 'como',
            'este', 'esta', 'esse', 'essa', 'aquele', 'aquela',
            'seu', 'sua', 'meu', 'minha', 'nosso', 'nossa',
            'ser', 'estar', 'ter', 'haver', 'fazer',
            'muito', 'mais', 'menos', 'bem', 'mal',
            'já', 'ainda', 'também', 'até', 'depois', 'antes',
            'hoje', 'ontem', 'amanhã', 'agora',
            'novo', 'nova', 'novos', 'novas',
            'primeiro', 'primeira', 'segundo', 'segunda',
            'ano', 'anos', 'dia', 'dias', 'vez', 'vezes'
        ];
        
        // Extrair palavras
        $words = explode(' ', $text);
        $keywords = [];
        
        foreach ($words as $word) {
            $word = trim($word);
            
            // Filtrar: mínimo 4 caracteres, não é stop word, não é número
            if (strlen($word) >= 4 && !in_array($word, $stop_words) && !is_numeric($word)) {
                $keywords[] = ucfirst($word); // Capitalizar primeira letra
            }
        }
        
        // Contar frequência e pegar as mais comuns
        if (empty($keywords)) {
            return [];
        }
        
        $keyword_counts = array_count_values($keywords);
        arsort($keyword_counts);
        
        return array_slice(array_keys($keyword_counts), 0, $limit);
    }
    
    private function log_action($integration_id, $post_id, $action, $status, $message, $sources = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'iap_logs';
        
        $log_data = [
            'integration_id' => $integration_id,
            'post_id' => $post_id,
            'action' => $action,
            'status' => $status,
            'message' => $message
        ];
        
        if ($sources !== null) {
            $log_data['sources'] = json_encode($sources);
        }
        
        $wpdb->insert($table, $log_data);
    }
}
