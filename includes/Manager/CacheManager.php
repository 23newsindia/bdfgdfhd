<?php
namespace RemoveUnusedCSS\Manager;

use RemoveUnusedCSS\Database\Queries\UsedCSS;
use RemoveUnusedCSS\Frontend\Filesystem;

class CacheManager {
    private $used_css_query;
    private $filesystem;
    private $varnish_cache;

    public function __construct(UsedCSS $used_css_query, Filesystem $filesystem) {
        $this->used_css_query = $used_css_query;
        $this->filesystem = $filesystem;
        
        // Initialize Varnish cache if available
        if (class_exists('ClpVarnishCacheManager')) {
            $this->varnish_cache = new \ClpVarnishCacheManager();
        }
    }

    public function clear_all_cache() {
        global $wpdb;
        
        // Clear database entries
        $table = $wpdb->prefix . 'rucs_used_css';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            $wpdb->query("TRUNCATE TABLE $table");
        }

        // Clear filesystem cache
        $this->filesystem->clear_cache();

        // Clear Varnish cache if available
        if ($this->varnish_cache && $this->varnish_cache->is_enabled()) {
            $this->varnish_cache->purge_tag('css');
        }

        return true;
    }

    public function get_stats() {
        global $wpdb;
        $table = $wpdb->prefix . 'rucs_used_css';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return [
                'total' => 0,
                'completed' => 0,
                'pending' => 0,
                'size' => '0 MB'
            ];
        }
        
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
        $completed = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'completed'");
        
        return [
            'total' => $total,
            'completed' => $completed,
            'pending' => $total - $completed,
            'size' => $this->filesystem->get_cache_size()
        ];
    }
}