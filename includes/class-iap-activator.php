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
        $table_settings = $wpdb->prefix . 'iap_settings';
        
        $sql_integrations = "CREATE TABLE IF NOT EXISTS $table_integrations (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            category_id bigint(20) NOT NULL,
            ai_provider varchar(50) NOT NULL,
            ai_config longtext,
            feed_ids longtext,
            custom_prompt longtext,
            feed_items_count int(11) DEFAULT 3,
            feed_order varchar(20) DEFAULT 'recent',
            fallback_image_id bigint(20) DEFAULT NULL,
            post_status varchar(20) DEFAULT 'draft',
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
        
        $sql_settings = "CREATE TABLE IF NOT EXISTS $table_settings (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            setting_key varchar(255) NOT NULL,
            setting_value longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_setting_key (setting_key)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_integrations);
        dbDelta($sql_feeds);
        dbDelta($sql_logs);
        dbDelta($sql_processed);
        dbDelta($sql_settings);
        
        self::migrate_existing_tables();
        self::insert_default_feeds();
        self::insert_default_settings();
        
        // Não criar agendamento global - cada integração tem seu próprio schedule
        // Os schedules individuais são criados/atualizados ao salvar cada integração
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
        
        // Verificar e adicionar coluna feed_items_count
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_integrations}` LIKE 'feed_items_count'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE `{$table_integrations}` ADD COLUMN `feed_items_count` INT(11) DEFAULT 3 AFTER `custom_prompt`");
        }
        
        // Verificar e adicionar coluna feed_order
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_integrations}` LIKE 'feed_order'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE `{$table_integrations}` ADD COLUMN `feed_order` VARCHAR(20) DEFAULT 'recent' AFTER `feed_items_count`");
        }
        
        // Verificar e adicionar coluna fallback_image_id
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_integrations}` LIKE 'fallback_image_id'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE `{$table_integrations}` ADD COLUMN `fallback_image_id` BIGINT(20) DEFAULT NULL AFTER `feed_order`");
        }
        
        // Verificar e adicionar coluna post_status
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_integrations}` LIKE 'post_status'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE `{$table_integrations}` ADD COLUMN `post_status` VARCHAR(20) DEFAULT 'draft' AFTER `fallback_image_id`");
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
    
    private static function insert_default_settings() {
        global $wpdb;
        $table_settings = $wpdb->prefix . 'iap_settings';
        
        // Prompt global padrão
        $default_prompt = "Instruções:\n";
        $default_prompt .= "- Crie um título original e atraente\n";
        $default_prompt .= "- Sugira 5 tags relevantes (palavras-chave principais do tema)\n";
        $default_prompt .= "- Escreva o conteúdo com mínimo 600 palavras em HTML\n";
        $default_prompt .= "- Use títulos HTML(H1,H2,H3) para seções, <p> para parágrafos, <ul>/<ol> para listas, <strong> para destaques. Preciso que a semântica pro SEO seja importante.\n";
        $default_prompt .= "- Tom jornalístico profissional\n";
        $default_prompt .= "- Combine as fontes de forma coerente caso você consuma mais de 1 feed\n";
        $default_prompt .= "- Conteúdo 100% original, mas não viaje tanto na criatividade.\n\n";
        $default_prompt .= "⚠️ IMPORTANTE - Formato de Resposta:\n\n";
        $default_prompt .= "TÍTULO: [apenas o título, sem repetir no conteúdo]\n\n";
        $default_prompt .= "META_TÍTULO: [versão otimizada do título para SEO, máximo 60 caracteres]\n\n";
        $default_prompt .= "META_DESCRIÇÃO: [resumo atraente do conteúdo para SEO, máximo 160 caracteres]\n\n";
        $default_prompt .= "FOCUS_KEYWORD: [palavra-chave principal do artigo, 1-3 palavras]\n\n";
        $default_prompt .= "TAGS: [tag1, tag2, tag3, tag4, tag5]\n\n";
        $default_prompt .= "CONTEÚDO:\n";
        $default_prompt .= "[Comece direto com o HTML do conteúdo. NÃO repita o título aqui. NÃO inclua as tags aqui. Apenas o corpo do artigo em HTML]";
        
        // Verificar se já existe
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_settings WHERE setting_key = %s",
            'global_prompt'
        ));
        
        if (!$exists) {
            $wpdb->insert($table_settings, [
                'setting_key' => 'global_prompt',
                'setting_value' => $default_prompt
            ]);
        }
    }
}
