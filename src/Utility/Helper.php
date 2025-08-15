<?php

namespace App\AdvancedEntryManager\Utility;

use WP_Error;
use WP_REST_Response;
use App\AdvancedEntryManager\Utility\DB;

class Helper {

    const OPTION_PREFIX = 'aemfw_';

    /**
     * Set AEM Transient
     * 
     * @param string $key
     * @param mixed $value
     */
    public static function set_transient( string $key, $value ): void {
        set_transient( self::OPTION_PREFIX . $key, $value, HOUR_IN_SECONDS );
    }

    /**
     * Get AEMFWP Table
     * 
     * @return string
     */
    public static function get_table_name(): string {
        global $wpdb;
        // return $wpdb->prefix . AEMFW_TABLE_NAME;
        return $wpdb->prefix . 'aemfw_entries';
    }

    /**
     * Get AEM Transient
     * 
     * @param string $key
     * @return mixed
     */
    public static function get_transient( string $key ) {
        return get_transient( self::OPTION_PREFIX . $key );
    }

    /**
     * Delete AEM Transient
     * 
     * @param string $key
     */
    public static function delete_transient( string $key ): void {
        delete_transient( self::OPTION_PREFIX . $key );
    }

    /**
     * Set AEM Error Log
     * 
     * @param mixed $data
     */
    public static function set_error_log( $data ): void {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            $output = is_scalar( $data ) ? $data : print_r( $data, true );
            error_log( "[AEM] " . $output );
        }
    }

    /**
     * Get AEM Error Log
     * 
     * @return array
     */
    public static function get_error_log(): array {
        $log_file = WP_CONTENT_DIR . '/debug.log';
        if ( file_exists( $log_file ) ) {
            $log_contents = file_get_contents( $log_file );
            return explode( "\n", trim( $log_contents ) );
        }
        return [];
    }

    /**
     * Check if the WPFormsDB table exists.
     *
     * @return bool
     */
    public static function table_exists( $table_name ): bool {
        global $wpdb;

        $table_name_like = str_replace('_', '\\_', $table_name); // Escape underscores
        $result = $wpdb->get_var(
            $wpdb->prepare("SHOW TABLES LIKE %s", $table_name_like)
        );
        
        return ! empty($result);
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
        return new WP_Error( $code, __( $message, 'advanced-entries-manager-for-wpforms' ), $data );
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

    public static function wpformd_db_data_summary() {
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

        $data = [];
        if ( ! empty( $results ) ) {
            foreach ( $results as $row ) {
                $data[] = [
                    'form_post_id' => (int) $row->form_post_id,
                    'post_title'   => $row->post_title,
                    'entry_count'  => (int) $row->entry_count,
                ];
            }
        }

        return $data;
    }

    /**
     * Check if the WordPress REST API is enabled.
     *
     * @return bool True if REST API is accessible, false otherwise.
     */
    public static function is_rest_enabled() {
        $response = wp_remote_get( site_url( '/wp-json/' ), [
            'timeout' => 5,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ] );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $code = wp_remote_retrieve_response_code( $response );

        return ( $code >= 200 && $code < 300 );
    }

    public static function get_access_token() {
        $access_token = self::get_option('google_access_token');
        $expires_at   = (int) self::get_option('google_token_expires', 0);

        // If valid and not expired, return
        if ($access_token && $expires_at > (time() + 60)) {
            return $access_token;
        }

        // Else: Refresh via POST request to proxy's REST endpoint
        $response = wp_remote_post( AEMFW_PROXY_BASE_URL . 'wp-json/aemfw/v1/refresh', [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => json_encode([
                'site' => self::get_settings_page_url(),
            ]),
        ]);

        if (is_wp_error($response)) {
            Helper::set_error_log('Token refresh failed: ' . $response->get_error_message());
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!empty($body['access_token'])) {
            self::update_option('google_access_token', sanitize_text_field($body['access_token']));
            self::update_option('google_token_expires', time() + intval($body['expires_in'] ?? 3600)); // fallback: 1 hour
            return $body['access_token'];
        }

        Helper::set_error_log('Invalid refresh response: ' . wp_remote_retrieve_body($response));
        return false;
    }

    /**
     * Get Settings page url wihout sanitization
     *
     * @return string
     */
    public static function get_settings_page_url(){
        return admin_url( 'admin.php?page=aemfw-settings');
    }

    /**
     * Retrieves the number of seconds remaining until the Google token expires.
     *
     * @return int Number of seconds until token expiration. Returns 0 if expired or not set.
     */
    public static function get_token_expires_in(): int {
        
        $token_expires = self::get_option('google_token_expires');
        $now           = time();

        return $token_expires ? max(0, $token_expires - $now) : 0;
    }

    /**
     * Converts a number of seconds into a human-readable string format.
     *
     * @param int $seconds The number of seconds to convert.
     * @return string Human-readable representation (e.g., "2h 15m", "Expired", "Less than a minute").
     */
    public static function seconds_to_human_readable(int $seconds): string {
        if ($seconds <= 0) {
            return esc_html__('Expired', 'advanced-entries-manager-for-wpforms');
        }

        $h = floor($seconds / 3600);
        $m = floor(($seconds % 3600) / 60);

        if ($h || $m) {
            return trim(($h ? $h . 'h ' : '') . ($m ? $m . 'm' : ''));
        }

        return esc_html__('Less than a minute', 'advanced-entries-manager-for-wpforms');
    }

    /**
     * Checks if the Pro version of the plugin is active.
     * This method is crucial for handling free/pro version limitations.
     *
     * @return bool
     */
    public static function is_pro_version()
    {
        // For demonstration, we assume a constant is defined in the Pro version.
        // In a real plugin, this constant would be defined in the main plugin file of the Pro version.
        // Example: define('AEMFW_PRO_VERSION', true);
        return defined('AEMFW_PRO_VERSION') && AEMFW_PRO_VERSION;
    }
}
