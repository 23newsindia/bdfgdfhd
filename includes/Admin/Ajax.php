<?php
namespace RemoveUnusedCSS\Admin;

use RemoveUnusedCSS\Manager\CacheManager;

class Ajax {
    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_ajax_rucs_clear_cache', [$this, 'clear_cache']);
    }

    /**
     * Clear cache via AJAX
     */
    public function clear_cache() {
        check_ajax_referer('rucs_clear_cache', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'remove-unused-css'));
        }

        $cache_manager = new CacheManager(
            new \RemoveUnusedCSS\Database\Queries\UsedCSS(),
            new \RemoveUnusedCSS\Frontend\Filesystem()
        );

        $result = $cache_manager->clear_all_cache();

        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error(__('Could not clear cache', 'remove-unused-css'));
        }
    }
}