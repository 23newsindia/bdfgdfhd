<?php
namespace RemoveUnusedCSS\Admin\Options;

class Options_Data {
    /**
     * Plugin options
     *
     * @var array
     */
    private $options;

    /**
     * Constructor
     */
    public function __construct() {
        $this->options = get_option('rucs_settings', []);
    }

    /**
     * Get option value
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get($key, $default = false) {
        return isset($this->options[$key]) ? $this->options[$key] : $default;
    }

    /**
     * Set option value
     *
     * @param string $key
     * @param mixed $value
     */
    public function set($key, $value) {
        $this->options[$key] = $value;
    }

    /**
     * Get all options
     *
     * @return array
     */
    public function get_all() {
        return $this->options;
    }
}