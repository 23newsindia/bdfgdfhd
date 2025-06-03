<?php
/**
 * Plugin Name: Remove Unused CSS
 * Description: Automatically removes unused CSS rules from stylesheets
 * Version: 1.0.0
 * Author: Your Name
 * License: GPLv2 or later
 * Text Domain: remove-unused-css
 */

namespace RemoveUnusedCSS;

defined('ABSPATH') || exit;

// Define constants
define('RUCS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RUCS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('RUCS_VERSION', '1.0.0');

// Autoload classes
require_once RUCS_PLUGIN_DIR . 'vendor/autoload.php';

// Load core files
require_once RUCS_PLUGIN_DIR . 'includes/Database/Tables/UsedCSS.php';
require_once RUCS_PLUGIN_DIR . 'includes/Database/Queries/UsedCSS.php';
require_once RUCS_PLUGIN_DIR . 'includes/Frontend/Filesystem.php';
require_once RUCS_PLUGIN_DIR . 'includes/Frontend/Processor.php';
require_once RUCS_PLUGIN_DIR . 'includes/Frontend/BackgroundQueue.php';
require_once RUCS_PLUGIN_DIR . 'includes/Admin/Options/Options_Data.php';
require_once RUCS_PLUGIN_DIR . 'includes/Admin/Settings.php';
require_once RUCS_PLUGIN_DIR . 'includes/Admin/Subscriber.php';
require_once RUCS_PLUGIN_DIR . 'includes/Admin/Ajax.php';
require_once RUCS_PLUGIN_DIR . 'includes/Manager/CacheManager.php';

// Initialize the plugin
add_action('plugins_loaded', function() {
    // Check dependencies
    if (!class_exists('WP_Background_Process')) {
        require_once RUCS_PLUGIN_DIR . 'vendor/wp-media/wp-background-processing/classes/wp-background-process.php';
    }

    // Initialize database table
    $used_css_table = new Database\Tables\UsedCSS();
    $used_css_table->install();
    
    // Initialize main components
    $used_css_query = new Database\Queries\UsedCSS();
    $filesystem = new Frontend\Filesystem();
    $options = new Admin\Options\Options_Data();
    
    // Initialize frontend processor
    new Frontend\Processor($options, $used_css_query);
    
    // Initialize admin if in admin area
    if (is_admin()) {
        $settings = new Admin\Settings($options);
        new Admin\Subscriber($settings, $used_css_table);
        new Admin\Ajax();
    }
});

// Register activation hook
register_activation_hook(__FILE__, function() {
    // Create database tables
    $used_css_table = new Database\Tables\UsedCSS();
    $used_css_table->install();
    
    // Verify dependencies
    if (!file_exists(RUCS_PLUGIN_DIR . 'vendor/autoload.php')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('Please run <code>composer install</code> first to install dependencies.');
    }
});

// Register deactivation hook
register_deactivation_hook(__FILE__, function() {
    // Clean up on deactivation
    $queue = new Frontend\BackgroundQueue();
    $queue->cancel_process();
    
    // Clear cache directory
    $cache_dir = WP_CONTENT_DIR . '/cache/remove-unused-css/';
    if (file_exists($cache_dir)) {
        array_map('unlink', glob($cache_dir . '*.css'));
        @rmdir($cache_dir);
    }
});