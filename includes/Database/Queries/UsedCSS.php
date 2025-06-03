<?php
namespace RemoveUnusedCSS\Database\Queries;

class UsedCSS {
    /**
     * Get used CSS by URL
     *
     * @param string $url
     * @param bool $is_mobile
     * @return object|null
     */
    public function get_by_url($url, $is_mobile = false) {
        global $wpdb;
        
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}rucs_used_css 
                WHERE url = %s AND is_mobile = %d 
                LIMIT 1",
                $url,
                $is_mobile
            )
        );
    }

    /**
     * Update status for a URL
     *
     * @param string $url
     * @param string $status
     * @param bool $is_mobile
     * @return bool
     */
    public function update_status($url, $status, $is_mobile = false) {
        global $wpdb;
        
        return (bool) $wpdb->update(
            "{$wpdb->prefix}rucs_used_css",
            ['status' => $status],
            [
                'url' => $url,
                'is_mobile' => $is_mobile
            ]
        );
    }

    /**
     * Update or insert a record
     *
     * @param array $where
     * @param array $data
     * @return bool
     */
    public function update_or_insert($where, $data) {
        global $wpdb;
        
        $existing = $this->get_by_url($where['url'], $where['is_mobile'] ?? false);
        
        if ($existing) {
            return (bool) $wpdb->update(
                "{$wpdb->prefix}rucs_used_css",
                $data,
                ['id' => $existing->id]
            );
        }
        
        return (bool) $wpdb->insert(
            "{$wpdb->prefix}rucs_used_css",
            array_merge($where, $data)
        );
    }

    /**
     * Get completed count
     *
     * @return int
     */
    public function get_completed_count() {
        global $wpdb;
        
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}rucs_used_css 
            WHERE status = 'completed'"
        );
    }

    /**
     * Delete an item
     *
     * @param int $id
     * @return bool
     */
    public function delete_item($id) {
        global $wpdb;
        return (bool) $wpdb->delete(
            "{$wpdb->prefix}rucs_used_css",
            ['id' => $id]
        );
    }
}