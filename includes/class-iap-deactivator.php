<?php

if (!defined('ABSPATH')) {
    exit;
}

class IAP_Deactivator {
    
    public static function deactivate() {
        $timestamp = wp_next_scheduled('iap_run_integrations');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'iap_run_integrations');
        }
    }
}
