<?php
namespace RemoveUnusedCSS\Database\Tables;

class UsedCSS {
    protected $name = 'rucs_used_css';
    protected $db_version_key = 'rucs_used_css_version';
    protected $version = 20230601;

    public function exists() {
        global $wpdb;
        return $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}{$this->name}'") === $wpdb->prefix . $this->name;
    }

    public function install() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}{$this->name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            url varchar(2000) NOT NULL default '',
            css longtext,
            hash varchar(32) default '',
            status varchar(255) default 'pending',
            modified timestamp NOT NULL default CURRENT_TIMESTAMP,
            last_accessed timestamp NOT NULL default CURRENT_TIMESTAMP,
            is_mobile tinyint(1) NOT NULL default 0,
            PRIMARY KEY (id),
            KEY url (url(150), is_mobile),
            KEY status (status(191)),
            KEY hash (hash)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        
        update_option($this->db_version_key, $this->version);
        
        return true;
    }

    public function maybe_upgrade() {
        $current_version = get_option($this->db_version_key, 0);
        
        if ($current_version < $this->version) {
            $this->install();
        }
    }

    public function uninstall() {
        global $wpdb;
        
        if ($this->exists()) {
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$this->name}");
            delete_option($this->db_version_key);
        }
    }
}