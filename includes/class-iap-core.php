<?php

if (!defined('ABSPATH')) {
    exit;
}

class IAP_Core {
    
    protected $loader;
    protected $version;
    
    public function __construct() {
        $this->version = IAP_VERSION;
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }
    
    private function load_dependencies() {
        require_once IAP_PLUGIN_DIR . 'includes/class-iap-loader.php';
        require_once IAP_PLUGIN_DIR . 'admin/class-iap-admin.php';
        require_once IAP_PLUGIN_DIR . 'includes/class-iap-ai-manager.php';
        require_once IAP_PLUGIN_DIR . 'includes/class-iap-feed-manager.php';
        require_once IAP_PLUGIN_DIR . 'includes/class-iap-integration-manager.php';
        
        $this->loader = new IAP_Loader();
    }
    
    private function define_admin_hooks() {
        $plugin_admin = new IAP_Admin($this->version);
        
        $this->loader->add_action('admin_menu', $plugin_admin, 'add_admin_menu');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
        
        $this->loader->add_action('wp_ajax_iap_save_integration', $plugin_admin, 'ajax_save_integration');
        $this->loader->add_action('wp_ajax_iap_delete_integration', $plugin_admin, 'ajax_delete_integration');
        $this->loader->add_action('wp_ajax_iap_test_ai_connection', $plugin_admin, 'ajax_test_ai_connection');
        $this->loader->add_action('wp_ajax_iap_run_integration', $plugin_admin, 'ajax_run_integration');
        $this->loader->add_action('wp_ajax_iap_save_feed', $plugin_admin, 'ajax_save_feed');
        $this->loader->add_action('wp_ajax_iap_delete_feed', $plugin_admin, 'ajax_delete_feed');
        $this->loader->add_action('wp_ajax_iap_get_debug_log', $plugin_admin, 'ajax_get_debug_log');
        $this->loader->add_action('wp_ajax_iap_clear_debug_log', $plugin_admin, 'ajax_clear_debug_log');
        $this->loader->add_action('wp_ajax_iap_list_gemini_models', $plugin_admin, 'ajax_list_gemini_models');
    }
    
    private function define_public_hooks() {
        $this->loader->add_action('iap_run_integrations', $this, 'run_scheduled_integrations');
    }
    
    public function run_scheduled_integrations() {
        $integration_manager = new IAP_Integration_Manager();
        $integration_manager->run_all_active_integrations();
    }
    
    public function run() {
        $this->loader->run();
    }
    
    public function get_version() {
        return $this->version;
    }
}
