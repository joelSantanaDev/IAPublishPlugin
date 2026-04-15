<?php

if (!defined('ABSPATH')) {
    exit;
}

class IAP_Activator {
    
    public static function activate() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $table_integrations = $wpdb->prefix . 'iap_integrations';
        $table_feeds = $wpdb->prefix . 'iap_feeds';
        $table_logs = $wpdb->prefix . 'iap_logs';
        $table_processed = $wpdb->prefix . 'iap_processed_items';
        
        $sql_integrations = "CREATE TABLE IF NOT EXISTS $table_integrations (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            category_id bigint(20) NOT NULL,
            ai_provider varchar(50) NOT NULL,
            ai_config longtext,
            feed_ids longtext,
            custom_prompt longtext,
            status varchar(20) DEFAULT 'active',
            schedule_frequency varchar(50) DEFAULT 'hourly',
            last_run datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        $sql_feeds = "CREATE TABLE IF NOT EXISTS $table_feeds (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            url varchar(500) NOT NULL,
            status varchar(20) DEFAULT 'active',
            last_fetch datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        $sql_logs = "CREATE TABLE IF NOT EXISTS $table_logs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            integration_id bigint(20),
            post_id bigint(20),
            action varchar(50) NOT NULL,
            status varchar(20) NOT NULL,
            message longtext,
            sources longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        $sql_processed = "CREATE TABLE IF NOT EXISTS $table_processed (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            feed_item_url varchar(500) NOT NULL,
            feed_item_title varchar(500) NOT NULL,
            feed_id bigint(20) NOT NULL,
            integration_id bigint(20) NOT NULL,
            post_id bigint(20),
            processed_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_feed_item (feed_item_url, integration_id),
            KEY idx_feed_id (feed_id),
            KEY idx_integration_id (integration_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_integrations);
        dbDelta($sql_feeds);
        dbDelta($sql_logs);
        dbDelta($sql_processed);
        
        self::migrate_existing_tables();
        self::insert_default_feeds();
        
        if (!wp_next_scheduled('iap_run_integrations')) {
            wp_schedule_event(time(), 'hourly', 'iap_run_integrations');
        }
    }
    
    private static function migrate_existing_tables() {
        global $wpdb;
        
        $table_integrations = $wpdb->prefix . 'iap_integrations';
        $table_logs = $wpdb->prefix . 'iap_logs';
        
        // Verificar e adicionar coluna custom_prompt na tabela de integrações
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_integrations}` LIKE 'custom_prompt'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE `{$table_integrations}` ADD COLUMN `custom_prompt` LONGTEXT AFTER `feed_ids`");
        }
        
        // Verificar e adicionar coluna sources na tabela de logs
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_logs}` LIKE 'sources'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE `{$table_logs}` ADD COLUMN `sources` LONGTEXT AFTER `message`");
        }
    }
    
    private static function insert_default_feeds() {
        global $wpdb;
        $table_feeds = $wpdb->prefix . 'iap_feeds';
        
        $default_feeds = [
            ['name' => 'InfoMoney', 'url' => 'https://www.infomoney.com.br/feed/'],
            ['name' => 'InvestNews', 'url' => 'https://investnews.com.br/feed/'],
            ['name' => 'Valor Econômico', 'url' => 'https://valor.globo.com/rss/home/feed.xml'],
            ['name' => 'G1 Economia', 'url' => 'http://g1.globo.com/dynamo/economia/rss2.xml']
        ];
        
        foreach ($default_feeds as $feed) {
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_feeds WHERE url = %s",
                $feed['url']
            ));
            
            if (!$exists) {
                $wpdb->insert($table_feeds, $feed);
            }
        }
    }
}
