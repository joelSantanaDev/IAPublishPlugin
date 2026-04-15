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
            'status' => isset($data['status']) ? sanitize_text_field($data['status']) : 'active',
            'schedule_frequency' => isset($data['schedule_frequency']) ? sanitize_text_field($data['schedule_frequency']) : 'hourly'
        ];
        
        if (isset($data['id']) && !empty($data['id'])) {
            $wpdb->update($table, $integration_data, ['id' => intval($data['id'])]);
            return intval($data['id']);
        } else {
            $wpdb->insert($table, $integration_data);
            return $wpdb->insert_id;
        }
    }
    
    public function delete_integration($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'iap_integrations';
        
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
        
        $feed_items = $this->feed_manager->fetch_multiple_feeds($feed_ids, 3);
        
        if (empty($feed_items)) {
            return ['success' => false, 'message' => 'Nenhum item encontrado nos feeds'];
        }
        
        $prompt = $this->build_prompt($feed_items);
        
        $ai_config = json_decode($integration->ai_config, true);
        $result = $this->ai_manager->generate_content($integration->ai_provider, $ai_config, $prompt);
        
        if (!$result['success']) {
            $this->log_action($integration_id, null, 'generate_failed', 'error', $result['message']);
            return $result;
        }
        
        $post_data = $this->parse_ai_response($result['content']);
        
        $post_id = wp_insert_post([
            'post_title' => $post_data['title'],
            'post_content' => $post_data['content'],
            'post_status' => 'draft',
            'post_category' => [$integration->category_id],
            'post_author' => get_current_user_id()
        ]);
        
        if (is_wp_error($post_id)) {
            $this->log_action($integration_id, null, 'post_creation_failed', 'error', $post_id->get_error_message());
            return ['success' => false, 'message' => $post_id->get_error_message()];
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'iap_integrations';
        $wpdb->update($table, ['last_run' => current_time('mysql')], ['id' => $integration_id]);
        
        $this->log_action($integration_id, $post_id, 'post_created', 'success', 'Post criado com sucesso');
        
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
    
    private function build_prompt($feed_items) {
        $prompt = "Com base nas seguintes notícias de diferentes fontes, crie uma notícia original e única:\n\n";
        
        foreach ($feed_items as $index => $item) {
            $prompt .= "Notícia " . ($index + 1) . " (Fonte: {$item['feed_name']}):\n";
            $prompt .= "Título: {$item['title']}\n";
            $prompt .= "Conteúdo: " . strip_tags($item['content'] ?: $item['description']) . "\n\n";
        }
        
        $prompt .= "Instruções:\n";
        $prompt .= "1. Crie um título atraente e original\n";
        $prompt .= "2. Escreva um conteúdo completo e bem estruturado (mínimo 300 palavras)\n";
        $prompt .= "3. Combine informações das diferentes fontes de forma coerente\n";
        $prompt .= "4. Mantenha um tom jornalístico profissional\n";
        $prompt .= "5. NÃO copie textos literalmente, crie conteúdo original\n\n";
        $prompt .= "Formato de resposta:\n";
        $prompt .= "TÍTULO: [seu título aqui]\n\n";
        $prompt .= "CONTEÚDO:\n[seu conteúdo aqui]";
        
        return $prompt;
    }
    
    private function parse_ai_response($content) {
        $lines = explode("\n", $content);
        $title = '';
        $body = '';
        $in_content = false;
        
        foreach ($lines as $line) {
            if (strpos($line, 'TÍTULO:') === 0) {
                $title = trim(str_replace('TÍTULO:', '', $line));
            } elseif (strpos($line, 'CONTEÚDO:') === 0) {
                $in_content = true;
            } elseif ($in_content) {
                $body .= $line . "\n";
            }
        }
        
        if (empty($title)) {
            $title = 'Nova Notícia - ' . date('Y-m-d H:i:s');
        }
        
        return [
            'title' => $title,
            'content' => trim($body)
        ];
    }
    
    private function log_action($integration_id, $post_id, $action, $status, $message) {
        global $wpdb;
        $table = $wpdb->prefix . 'iap_logs';
        
        $wpdb->insert($table, [
            'integration_id' => $integration_id,
            'post_id' => $post_id,
            'action' => $action,
            'status' => $status,
            'message' => $message
        ]);
    }
}
