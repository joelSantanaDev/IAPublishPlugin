<?php
/**
 * Plugin Name: IA Publish Plugin
 * Plugin URI: https://github.com/joelSantanaDev/IAPublishPlugin
 * Description: Plugin para alimentação automática de notícias com IA a partir de feeds RSS
 * Version: 1.0.0
 * Author: Joel Santana
 * Author URI: https://github.com/joelSantanaDev
 * License: GPL v2 or later
 * Text Domain: ia-publish-plugin
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('IAP_VERSION', '1.0.0');
define('IAP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('IAP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('IAP_PLUGIN_BASENAME', plugin_basename(__FILE__));

require_once IAP_PLUGIN_DIR . 'includes/class-iap-activator.php';
require_once IAP_PLUGIN_DIR . 'includes/class-iap-deactivator.php';
require_once IAP_PLUGIN_DIR . 'includes/class-iap-core.php';

register_activation_hook(__FILE__, ['IAP_Activator', 'activate']);
register_deactivation_hook(__FILE__, ['IAP_Deactivator', 'deactivate']);

/**
 * Load plugin textdomain for internationalization
 */
function iap_load_textdomain() {
    load_plugin_textdomain(
        'ia-publish-plugin',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages/'
    );
}
add_action('plugins_loaded', 'iap_load_textdomain');

function run_ia_publish_plugin() {
    $plugin = new IAP_Core();
    $plugin->run();
}

run_ia_publish_plugin();
