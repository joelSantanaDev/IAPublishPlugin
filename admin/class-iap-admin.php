<?php

if (!defined('ABSPATH')) {
    exit;
}

class IAP_Admin {
    
    private $version;
    
    public function __construct($version) {
        $this->version = $version;
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'IA Publish Plugin',
            'IA Publish',
            'manage_options',
            'ia-publish-plugin',
            [$this, 'display_integrations_page'],
            'dashicons-rss',
            30
        );
        
        add_submenu_page(
            'ia-publish-plugin',
            'Integrações',
            'Integrações',
            'manage_options',
            'ia-publish-plugin',
            [$this, 'display_integrations_page']
        );
        
        add_submenu_page(
            'ia-publish-plugin',
            'Feeds RSS',
            'Feeds RSS',
            'manage_options',
            'ia-publish-feeds',
            [$this, 'display_feeds_page']
        );
        
        add_submenu_page(
            'ia-publish-plugin',
            'Logs',
            'Logs',
            'manage_options',
            'ia-publish-logs',
            [$this, 'display_logs_page']
        );
        
        add_submenu_page(
            'ia-publish-plugin',
            'Debug',
            'Debug',
            'manage_options',
            'ia-publish-debug',
            [$this, 'display_debug_page']
        );
    }
    
    public function enqueue_styles() {
        if (!$this->is_plugin_page()) {
            return;
        }
        
        wp_enqueue_style('ia-publish-admin', IAP_PLUGIN_URL . 'admin/css/admin.css', [], $this->version);
    }
    
    public function enqueue_scripts() {
        if (!$this->is_plugin_page()) {
            return;
        }
        
        wp_enqueue_script('ia-publish-admin', IAP_PLUGIN_URL . 'admin/js/admin.js', ['jquery'], $this->version, true);
        
        wp_localize_script('ia-publish-admin', 'iapAjax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('iap_ajax_nonce')
        ]);
    }
    
    private function is_plugin_page() {
        $screen = get_current_screen();
        return $screen && strpos($screen->id, 'ia-publish') !== false;
    }
    
    public function display_integrations_page() {
        $integration_manager = new IAP_Integration_Manager();
        $ai_manager = new IAP_AI_Manager();
        $feed_manager = new IAP_Feed_Manager();
        
        $integrations = $integration_manager->get_all_integrations();
        $categories = get_categories(['hide_empty' => false]);
        $ai_providers = $ai_manager->get_providers();
        $feeds = $feed_manager->get_all_feeds();
        
        include IAP_PLUGIN_DIR . 'admin/views/integrations.php';
    }
    
    public function display_feeds_page() {
        $feed_manager = new IAP_Feed_Manager();
        $feeds = $feed_manager->get_all_feeds();
        
        include IAP_PLUGIN_DIR . 'admin/views/feeds.php';
    }
    
    public function display_logs_page() {
        global $wpdb;
        $table = $wpdb->prefix . 'iap_logs';
        
        $logs = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC LIMIT 100");
        
        include IAP_PLUGIN_DIR . 'admin/views/logs.php';
    }
    
    public function display_debug_page() {
        include IAP_PLUGIN_DIR . 'admin/views/debug.php';
    }
    
    public function ajax_save_integration() {
        check_ajax_referer('iap_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permissão negada']);
        }
        
        $integration_manager = new IAP_Integration_Manager();
        
        $data = [
            'id' => isset($_POST['id']) ? intval($_POST['id']) : null,
            'name' => sanitize_text_field($_POST['name']),
            'category_id' => intval($_POST['category_id']),
            'ai_provider' => sanitize_text_field($_POST['ai_provider']),
            'ai_config' => [
                'api_key' => sanitize_text_field($_POST['api_key']),
                'model' => sanitize_text_field($_POST['model']),
                'temperature' => floatval($_POST['temperature']),
                'max_tokens' => intval($_POST['max_tokens'])
            ],
            'feed_ids' => isset($_POST['feed_ids']) ? array_map('intval', $_POST['feed_ids']) : [],
            'status' => sanitize_text_field($_POST['status']),
            'schedule_frequency' => sanitize_text_field($_POST['schedule_frequency'])
        ];
        
        $integration_id = $integration_manager->save_integration($data);
        
        if ($integration_id) {
            wp_send_json_success(['message' => 'Integração salva com sucesso', 'id' => $integration_id]);
        } else {
            wp_send_json_error(['message' => 'Erro ao salvar integração']);
        }
    }
    
    public function ajax_delete_integration() {
        check_ajax_referer('iap_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permissão negada']);
        }
        
        $integration_manager = new IAP_Integration_Manager();
        $id = intval($_POST['id']);
        
        if ($integration_manager->delete_integration($id)) {
            wp_send_json_success(['message' => 'Integração excluída com sucesso']);
        } else {
            wp_send_json_error(['message' => 'Erro ao excluir integração']);
        }
    }
    
    public function ajax_test_ai_connection() {
        try {
            check_ajax_referer('iap_ajax_nonce', 'nonce');
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Permissão negada']);
                return;
            }
            
            $ai_manager = new IAP_AI_Manager();
            
            $provider = sanitize_text_field($_POST['provider']);
            $config = [
                'api_key' => sanitize_text_field($_POST['api_key']),
                'model' => sanitize_text_field($_POST['model'])
            ];
            
            $result = $ai_manager->test_connection($provider, $config);
            
            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result);
            }
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Erro fatal: ' . $e->getMessage()]);
        }
    }
    
    public function ajax_run_integration() {
        check_ajax_referer('iap_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permissão negada']);
        }
        
        $integration_manager = new IAP_Integration_Manager();
        $id = intval($_POST['id']);
        
        $result = $integration_manager->run_integration($id);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    public function ajax_save_feed() {
        check_ajax_referer('iap_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permissão negada']);
        }
        
        $feed_manager = new IAP_Feed_Manager();
        
        $data = [
            'id' => isset($_POST['id']) ? intval($_POST['id']) : null,
            'name' => sanitize_text_field($_POST['name']),
            'url' => esc_url_raw($_POST['url']),
            'status' => sanitize_text_field($_POST['status'])
        ];
        
        $feed_id = $feed_manager->save_feed($data);
        
        if ($feed_id) {
            wp_send_json_success(['message' => 'Feed salvo com sucesso', 'id' => $feed_id]);
        } else {
            wp_send_json_error(['message' => 'Erro ao salvar feed']);
        }
    }
    
    public function ajax_delete_feed() {
        check_ajax_referer('iap_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permissão negada']);
        }
        
        $feed_manager = new IAP_Feed_Manager();
        $id = intval($_POST['id']);
        
        if ($feed_manager->delete_feed($id)) {
            wp_send_json_success(['message' => 'Feed excluído com sucesso']);
        } else {
            wp_send_json_error(['message' => 'Erro ao excluir feed']);
        }
    }
    
    public function ajax_get_debug_log() {
        try {
            check_ajax_referer('iap_ajax_nonce', 'nonce');
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Permissão negada']);
                return;
            }
            
            if (!defined('WP_CONTENT_DIR')) {
                wp_send_json_error(['message' => 'WP_CONTENT_DIR não definido']);
                return;
            }
            
            $log_file = WP_CONTENT_DIR . '/debug.log';
            
            if (!file_exists($log_file)) {
                $message = 'Arquivo debug.log não encontrado.<br><br>';
                $message .= '<strong>Para ativar o debug, adicione no wp-config.php:</strong><br>';
                $message .= '<code>define(\'WP_DEBUG\', true);<br>';
                $message .= 'define(\'WP_DEBUG_LOG\', true);<br>';
                $message .= 'define(\'WP_DEBUG_DISPLAY\', false);</code>';
                wp_send_json_error(['message' => $message]);
                return;
            }
            
            $lines = @file($log_file);
            if ($lines === false) {
                wp_send_json_error(['message' => 'Erro ao ler arquivo de log. Verifique permissões.']);
                return;
            }
            
            $last_lines = array_slice($lines, -200);
            $content = implode('', $last_lines);
            
            if (empty($content)) {
                $content = '(Log vazio - nenhum erro registrado ainda)';
            }
            
            wp_send_json_success(['content' => $content]);
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Erro: ' . $e->getMessage()]);
        }
    }
    
    public function ajax_clear_debug_log() {
        try {
            check_ajax_referer('iap_ajax_nonce', 'nonce');
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Permissão negada']);
                return;
            }
            
            $log_file = WP_CONTENT_DIR . '/debug.log';
            
            if (!file_exists($log_file)) {
                wp_send_json_error(['message' => 'Arquivo debug.log não encontrado.']);
                return;
            }
            
            if (@file_put_contents($log_file, '') !== false) {
                wp_send_json_success(['message' => 'Debug log limpo com sucesso']);
            } else {
                wp_send_json_error(['message' => 'Erro ao limpar debug log']);
            }
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Erro: ' . $e->getMessage()]);
        }
    }
    
    public function ajax_list_gemini_models() {
        try {
            check_ajax_referer('iap_ajax_nonce', 'nonce');
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Permissão negada']);
                return;
            }
            
            $api_key = sanitize_text_field($_POST['api_key']);
            
            if (empty($api_key)) {
                wp_send_json_error(['message' => 'API Key não fornecida']);
                return;
            }
            
            $url = 'https://generativelanguage.googleapis.com/v1beta/models?key=' . $api_key;
            
            $response = wp_remote_get($url, ['timeout' => 30]);
            
            if (is_wp_error($response)) {
                wp_send_json_error(['message' => 'Erro na requisição: ' . $response->get_error_message()]);
                return;
            }
            
            $http_code = wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);
            
            if ($http_code !== 200) {
                $error_msg = isset($body['error']['message']) ? $body['error']['message'] : 'Erro desconhecido';
                wp_send_json_error(['message' => 'HTTP ' . $http_code . ': ' . $error_msg]);
                return;
            }
            
            if (!isset($body['models']) || !is_array($body['models'])) {
                wp_send_json_error(['message' => 'Resposta inválida da API']);
                return;
            }
            
            $models_list = "Modelos disponíveis:\n\n";
            foreach ($body['models'] as $model) {
                $name = isset($model['name']) ? str_replace('models/', '', $model['name']) : 'N/A';
                $display_name = isset($model['displayName']) ? $model['displayName'] : '';
                $supported = isset($model['supportedGenerationMethods']) ? implode(', ', $model['supportedGenerationMethods']) : '';
                
                $models_list .= "• {$name}\n";
                if ($display_name) {
                    $models_list .= "  Nome: {$display_name}\n";
                }
                if ($supported) {
                    $models_list .= "  Suporta: {$supported}\n";
                }
                $models_list .= "\n";
            }
            
            wp_send_json_success(['models' => $models_list]);
            
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Erro: ' . $e->getMessage()]);
        }
    }
}
