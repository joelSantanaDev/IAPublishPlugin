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
            $items[] = [
                'title' => $item->get_title(),
                'link' => $item->get_permalink(),
                'description' => $item->get_description(),
                'content' => $item->get_content(),
                'date' => $item->get_date('Y-m-d H:i:s'),
                'author' => $item->get_author() ? $item->get_author()->get_name() : ''
            ];
        }
        
        return ['success' => true, 'items' => $items];
    }
    
    public function fetch_multiple_feeds($feed_ids, $limit_per_feed = 5) {
        $all_items = [];
        
        foreach ($feed_ids as $feed_id) {
            $feed = $this->get_feed($feed_id);
            if (!$feed) {
                continue;
            }
            
            $result = $this->fetch_feed($feed->url);
            if ($result['success']) {
                $items = array_slice($result['items'], 0, $limit_per_feed);
                foreach ($items as $item) {
                    $item['feed_name'] = $feed->name;
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
}
