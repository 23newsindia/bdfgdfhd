<?php
namespace RemoveUnusedCSS\Frontend;

class Filesystem {
    private $cache_dir;

    public function __construct() {
        $this->cache_dir = WP_CONTENT_DIR . '/cache/remove-unused-css/';
        $this->maybe_create_cache_dir();
    }

    public function clear_cache() {
        if (!file_exists($this->cache_dir)) {
            return true;
        }

        $files = glob($this->cache_dir . '*.css');
        if (!is_array($files)) {
            return true;
        }

        $success = true;
        foreach ($files as $file) {
            if (!unlink($file)) {
                $success = false;
            }
        }

        return $success;
    }

    public function get_cache_size() {
        if (!file_exists($this->cache_dir)) {
            return '0 MB';
        }

        $size = 0;
        $files = glob($this->cache_dir . '*.css');
        
        if (is_array($files)) {
            foreach ($files as $file) {
                $size += filesize($file);
            }
        }

        return size_format($size);
    }

    public function maybe_create_cache_dir() {
        if (!file_exists($this->cache_dir)) {
            wp_mkdir_p($this->cache_dir);
            
            // Create .htaccess to protect cache directory
            $htaccess = $this->cache_dir . '.htaccess';
            if (!file_exists($htaccess)) {
                $rules = "Order Deny,Allow\nDeny from all\n";
                file_put_contents($htaccess, $rules);
            }
            
            // Create index.php to prevent directory listing
            $index = $this->cache_dir . 'index.php';
            if (!file_exists($index)) {
                file_put_contents($index, '<?php // Silence is golden');
            }
        }
        return true;
    }
}