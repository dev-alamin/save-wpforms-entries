<?php

namespace App\AdvancedEntryManager\Utility;

use WP_Error;
use WP_REST_Response;
use App\AdvancedEntryManager\Utility\DB;

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

    /**
	 * Queue Action Scheduler jobs in batches.
	 *
	 * @param string $hook_name The hook to trigger for each batch.
	 * @param array  $args       Extra args to pass in each scheduled job.
	 * @param int    $total      Total number of items to process.
	 * @param int    $batch_size Items per batch.
	 * @param int    $delay      Delay (in seconds) between each job.
	 *
	 * @return int Number of jobs queued.
	 */
    public static function queue_export_batches($form_id, $date_from, $date_to, $exclude_fields, $batch_size)
    {
        // Calculate total entries to export
        global $wpdb;

        $where_clauses = ['form_id = %d'];
        $args = [$form_id];

        if ($date_from) {
            $where_clauses[] = 'created_at >= %s';
            $args[] = $date_from;
        }

        if ($date_to) {
            $where_clauses[] = 'created_at <= %s';
            $args[] = $date_to;
        }

        $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);

        $count_sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}swpfe_entries {$where_sql}",
            ...$args
        );

        $total_entries = (int) $wpdb->get_var($count_sql);

        $batches = (int) ceil($total_entries / $batch_size);

        for ($i = 0; $i < $batches; $i++) {
            // Schedule each batch with Action Scheduler
            $args = [
                'form_id'        => $form_id,
                'date_from'      => $date_from,
                'date_to'        => $date_to,
                'exclude_fields' => $exclude_fields,
                'batch_size'     => $batch_size,
                'batch_number'   => $i + 1,
                'offset'         => $i * $batch_size,
            ];

            if (! as_next_scheduled_action('swpfe_export_csv_batch', [$args])) {
                as_schedule_single_action(time() + ($i * 15), 'swpfe_export_csv_batch', [$args], 'swpfe_export_csv_group');
            }
        }
    }
}
