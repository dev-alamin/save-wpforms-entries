<?php

namespace App\AdvancedEntryManager\Utility;

use WP_Error;
use WP_REST_Response;
use App\AdvancedEntryManager\Utility\DB;
use WP_List_Table;

class Helper {

    const OPTION_PREFIX = 'swpfe_';

    /**
     * Check if the WPFormsDB table exists.
     *
     * @return bool
     */
    public static function is_wpformsdb_table_exists(): bool {
        return DB::table_exists( 'wpforms_db' );
    }

    /**
     * Check if the WPFormsDB plugin is active.
     * 
     * @return bool
     */
    public static function is_wpformsdb_active(): bool {
        return is_plugin_active( 'database-for-wpforms/database-for-wpforms.php' );
    }

    /**
     * Get All Entries from WPFormsDB.
     * 
     * @return array
     */
    public static function get_all_wpformsdb_entries(): array {
        return DB::get_all_entries( 'wpforms_db' );
    }

    /**
     * Count total entries in the WPFormsDB table.
     * 
     * @return int
     */
    public static function count_wpformsdb_entries(): int {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wpforms_db';
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
    }

    /**
     * Get the current user ID.
     *
     * @return int
     */
    public static function get_current_user_id(): int {
        return get_current_user_id();
    }

    /**
     * Create a WP_Error object.
     *
     * @param string $message
     * @param string|int $code
     * @param array $data
     * @return WP_Error
     */
    public static function wp_error( string $message, $code = 'error', array $data = [] ): WP_Error {
        return new WP_Error( $code, __( $message, 'save-wpf-entries' ), $data );
    }

    /**
     * Return a formatted REST response.
     *
     * @param mixed $data
     * @param int $status
     * @return WP_REST_Response
     */
    public static function wp_rest_response( $data, int $status = 200 ): WP_REST_Response {
        return new WP_REST_Response( $data, $status );
    }

    /**
     * Check if Action Scheduler is available.
     *
     * @return bool
     */
    public static function is_action_scheduler_available(): bool {
        return class_exists( 'ActionScheduler' );
    }

    /**
     * Get a namespaced plugin option.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get_option( string $key, $default = false ) {
        return get_option( self::OPTION_PREFIX . $key, $default );
    }

    /**
     * Update a namespaced plugin option.
     *
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    public static function update_option( string $key, $value ): bool {
        return update_option( self::OPTION_PREFIX . $key, $value );
    }

    /**
     * Delete a namespaced plugin option.
     *
     * @param string $key
     * @return bool
     */
    public static function delete_option( string $key ): bool {
        return delete_option( self::OPTION_PREFIX . $key );
    }

    /**
     * Write to error_log for debug (with prefix).
     *
     * @param mixed $data
     * @param string $label
     * @return void
     */
    public static function log( $data, string $label = 'DEBUG' ): void {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            $output = is_scalar( $data ) ? $data : print_r( $data, true );
            error_log( "[AEM][$label] " . $output );
        }
    }

    /**
     * Sanitize a string or array of strings recursively.
     *
     * @param mixed $data
     * @return mixed
     */
    public static function sanitize_deep( $data ) {
        if ( is_array( $data ) ) {
            return array_map( [ self::class, 'sanitize_deep' ], $data );
        }
        return sanitize_text_field( $data );
    }

    /**
     * Format migration progress percentage.
     *
     * @param int $done
     * @param int $total
     * @return string
     */
    public static function format_percent( int $done, int $total ): string {
        if ( $total === 0 ) return '0%';
        return round( ( $done / $total ) * 100, 2 ) . '%';
    }

    /**
     * Generate a unique hash for entry de-duplication.
     *
     * @param int $form_post_id
     * @param string $form_value
     * @param string $form_date
     * @return string
     */
    public static function generate_entry_hash( int $form_post_id, string $form_value, string $form_date ): string {
        return md5( $form_post_id . $form_value . $form_date );
    }

    /**
     * Check if the current request is an AJAX request.
     *
     * @return bool
     */
    public static function is_ajax(): bool {
        return defined( 'DOING_AJAX' ) && DOING_AJAX;
    }

    public static function swpfe_render_migration_summary_table() {
        global $wpdb;

        $db_table    = $wpdb->prefix . 'wpforms_db';
        $posts_table = $wpdb->posts;

        $query = "
            SELECT 
                f.ID as form_post_id,
                f.post_title,
                COUNT(d.form_id) as entry_count
            FROM {$posts_table} f
            LEFT JOIN {$db_table} d ON f.ID = d.form_post_id
            WHERE f.post_type = 'wpforms' AND f.post_status = 'publish'
            GROUP BY f.ID
            ORDER BY entry_count DESC
        ";

        $results = $wpdb->get_results( $query );

        if ( empty( $results ) ) {
            echo '<p>No forms found with entries in the source table.</p>';
            return;
        }

        echo '<table class="widefat striped">';
        echo '<thead><tr><th>Form Title</th><th>Entry Count (Old Table)</th></tr></thead>';
        echo '<tbody>';

        foreach ( $results as $row ) {
            printf(
                '<tr><td>%s</td><td>%s</td></tr>',
                esc_html( $row->post_title ),
                number_format_i18n( $row->entry_count )
            );
        }

        echo '</tbody></table>';
    }
}
