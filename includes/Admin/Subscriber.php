<?php
namespace RemoveUnusedCSS\Admin;

use RemoveUnusedCSS\Admin\Options\Options_Data;
use RemoveUnusedCSS\Database\Tables\UsedCSS;

class Subscriber {
    /**
     * Settings instance
     *
     * @var Settings
     */
    private $settings;

    /**
     * Used CSS table
     *
     * @var UsedCSS
     */
    private $used_css_table;

    /**
     * Constructor
     *
     * @param Settings $settings
     * @param UsedCSS $used_css_table
     */
    public function __construct(Settings $settings, UsedCSS $used_css_table) {
        $this->settings = $settings;
        $this->used_css_table = $used_css_table;
        
        $this->init();
    }

    /**
     * Initialize hooks
     */
    private function init() {
        add_action('admin_notices', [$this, 'display_notices']);
        add_filter('plugin_action_links_' . plugin_basename(RUCS_PLUGIN_DIR . 'remove-unused-css.php'), [$this, 'add_action_links']);
    }

    /**
     * Display admin notices
     */
    public function display_notices() {
        if (!$this->used_css_table->exists()) {
            $this->display_table_notice();
        }
    }

    /**
     * Display table creation notice
     */
    private function display_table_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }

        ?>
        <div class="notice notice-error">
            <p>
                <?php _e('Remove Unused CSS: Could not create the required database table. Please try reactivating the plugin.', 'remove-unused-css'); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Add action links to plugins page
     *
     * @param array $links
     * @return array
     */
    public function add_action_links($links) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('options-general.php?page=remove-unused-css'),
            __('Settings', 'remove-unused-css')
        );
        
        array_unshift($links, $settings_link);
        return $links;
    }
}