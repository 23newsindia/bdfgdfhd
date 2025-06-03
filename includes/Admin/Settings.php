<?php
namespace RemoveUnusedCSS\Admin;

use RemoveUnusedCSS\Admin\Options\Options_Data;
use WP_Rocket\Admin\Settings\Settings as AdminSettings;

class Settings {
    /**
     * Plugin options instance
     *
     * @var Options_Data
     */
    private $options;

    /**
     * Settings page slug
     *
     * @var string
     */
    private $page_slug = 'remove-unused-css';

    /**
     * Settings page hook suffix
     *
     * @var string
     */
    private $page_hook;

    /**
     * Constructor
     *
     * @param Options_Data $options
     */
    public function __construct(Options_Data $options) {
        $this->options = $options;
        
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    /**
     * Add admin menu item
     */
    public function add_admin_menu() {
        $this->page_hook = add_options_page(
            __('Remove Unused CSS', 'remove-unused-css'),
            __('Remove Unused CSS', 'remove-unused-css'),
            'manage_options',
            $this->page_slug,
            [$this, 'render_page']
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting(
            'rucs_settings_group',
            'rucs_settings',
            [
                'sanitize_callback' => [$this, 'sanitize_options'],
                'default' => $this->get_default_options()
            ]
        );

        // General Settings Section
        add_settings_section(
            'rucs_general_section',
            __('General Settings', 'remove-unused-css'),
            '__return_empty_string',
            $this->page_slug
        );

        // Enable RUCSS
        add_settings_field(
            'enable_rucss',
            __('Enable Remove Unused CSS', 'remove-unused-css'),
            [$this, 'render_checkbox'],
            $this->page_slug,
            'rucs_general_section',
            [
                'name' => 'enable_rucss',
                'label' => __('Process CSS and remove unused rules', 'remove-unused-css')
            ]
        );

        // Exclusions Section
        add_settings_section(
            'rucs_exclusions_section',
            __('Exclusions', 'remove-unused-css'),
            '__return_empty_string',
            $this->page_slug
        );

        // CSS Exclusions
        add_settings_field(
            'excluded_css',
            __('Exclude CSS Files', 'remove-unused-css'),
            [$this, 'render_textarea'],
            $this->page_slug,
            'rucs_exclusions_section',
            [
                'name' => 'excluded_css',
                'description' => __('Enter one file per line. Use complete URLs or file names.', 'remove-unused-css')
            ]
        );

        // Cache Section
        add_settings_section(
            'rucs_cache_section',
            __('Cache Management', 'remove-unused-css'),
            [$this, 'render_cache_stats'],
            $this->page_slug
        );
    }

    /**
     * Render settings page
     */
    public function render_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <form action="options.php" method="post">
                <?php
                settings_fields('rucs_settings_group');
                do_settings_sections($this->page_slug);
                submit_button(__('Save Settings', 'remove-unused-css'));
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render checkbox field
     *
     * @param array $args
     */
    public function render_checkbox($args) {
        $name = $args['name'];
        $value = $this->options->get($name, false);
        ?>
        <fieldset>
            <legend class="screen-reader-text">
                <span><?php echo esc_html($args['label']); ?></span>
            </legend>
            <label for="<?php echo esc_attr($name); ?>">
                <input type="checkbox" 
                       id="<?php echo esc_attr($name); ?>" 
                       name="rucs_settings[<?php echo esc_attr($name); ?>]" 
                       value="1" 
                       <?php checked($value, true); ?> />
                <?php echo esc_html($args['label']); ?>
            </label>
        </fieldset>
        <?php
    }

    /**
     * Render textarea field
     *
     * @param array $args
     */
    public function render_textarea($args) {
        $name = $args['name'];
        $value = $this->options->get($name, '');
        ?>
        <textarea id="<?php echo esc_attr($name); ?>" 
                  name="rucs_settings[<?php echo esc_attr($name); ?>]" 
                  rows="5" 
                  class="large-text code"><?php echo esc_textarea($value); ?></textarea>
        <?php if (!empty($args['description'])) : ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render cache stats section
     */
    public function render_cache_stats() {
        $cache_manager = new \RemoveUnusedCSS\Manager\CacheManager(
            new \RemoveUnusedCSS\Database\Queries\UsedCSS(),
            new \RemoveUnusedCSS\Frontend\Filesystem()
        );
        
        $stats = $cache_manager->get_stats();
        ?>
        <div class="rucs-cache-stats">
            <h3><?php _e('Cache Statistics', 'remove-unused-css'); ?></h3>
            <ul>
                <li><?php printf(__('Total URLs processed: %d', 'remove-unused-css'), $stats['total']); ?></li>
                <li><?php printf(__('Completed: %d', 'remove-unused-css'), $stats['completed']); ?></li>
                <li><?php printf(__('Pending: %d', 'remove-unused-css'), $stats['pending']); ?></li>
                <li><?php printf(__('Cache size: %s', 'remove-unused-css'), $stats['size']); ?></li>
            </ul>
            
            <p>
                <button type="button" 
                        class="button button-secondary rcs-clear-cache" 
                        data-nonce="<?php echo wp_create_nonce('rucs_clear_cache'); ?>">
                    <?php _e('Clear All Cache', 'remove-unused-css'); ?>
                </button>
                <span class="spinner" style="float: none; display: inline-block;"></span>
            </p>
        </div>
        <?php
    }

    /**
     * Sanitize options before saving
     *
     * @param array $input
     * @return array
     */
    public function sanitize_options($input) {
        $output = [];
        
        if (isset($input['enable_rucss'])) {
            $output['enable_rucss'] = (bool) $input['enable_rucss'];
        }
        
        if (isset($input['excluded_css'])) {
            $output['excluded_css'] = sanitize_textarea_field($input['excluded_css']);
        }
        
        return $output;
    }

    /**
     * Get default options
     *
     * @return array
     */
    public function get_default_options() {
        return [
            'enable_rucss' => false,
            'excluded_css' => ''
        ];
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook
     */
    public function enqueue_assets($hook) {
        if ($hook !== $this->page_hook) {
            return;
        }

        wp_enqueue_style(
            'rucs-admin',
            plugins_url('assets/css/admin.css', dirname(__FILE__)),
            [],
            RUCS_VERSION
        );

        wp_enqueue_script(
            'rucs-admin',
            plugins_url('assets/js/admin.js', dirname(__FILE__)),
            ['jquery'],
            RUCS_VERSION,
            true
        );

        wp_localize_script(
            'rucs-admin',
            'rucsAdmin',
            [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'clearCacheNonce' => wp_create_nonce('rucs_clear_cache'),
                'processingText' => __('Processing...', 'remove-unused-css'),
                'successText' => __('Cache cleared successfully', 'remove-unused-css'),
                'errorText' => __('Error clearing cache', 'remove-unused-css')
            ]
        );
    }
}