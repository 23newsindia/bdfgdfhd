<?php
namespace RemoveUnusedCSS;

class ServiceProvider {
    /**
     * Register all plugin services
     */
    public function register() {
        // Database
        $used_css_table = new Database\Tables\UsedCSS();
        $used_css_query = new Database\Queries\UsedCSS();
        
        // Frontend
        $filesystem = new Frontend\Filesystem();
        $queue = new Frontend\BackgroundQueue();
        
        // Admin
        $options = new Admin\Options\Options_Data();
        $settings = new Admin\Settings($options);
        $ajax = new Admin\Ajax();
        
        // Manager
        $cache_manager = new Manager\CacheManager($used_css_query, $filesystem);
        
        // Initialize processor
        new Frontend\Processor($options, $used_css_query);
        
        // Initialize admin
        new Admin\Subscriber($settings, $used_css_table);
    }
}