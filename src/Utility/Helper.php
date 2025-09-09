<?php

namespace App\AdvancedEntryManager\Utility;

defined( 'ABSPATH' ) || exit;

use App\AdvancedEntryManager\Logger\FileLogger;
use WP_Error;
use WP_REST_Response;
use App\AdvancedEntryManager\Utility\DB;

class Helper {

	const OPTION_PREFIX = 'fem_';
	protected static $logger;

	protected static function getLogger() {
		if ( ! self::$logger ) {
			self::$logger = new FileLogger();
		}
		return self::$logger;
	}

	/**
	 * Set FEM Transient
	 *
	 * @param string $key
	 * @param mixed  $value
	 */
	public static function set_transient( string $key, $value ): void {
		set_transient( self::OPTION_PREFIX . $key, $value, HOUR_IN_SECONDS );
	}

	/**
	 * Get FEM Table
	 *
	 * @return string
	 */
	public static function get_table_name(): string {
		global $wpdb;
		// return $wpdb->prefix . FEM_TABLE_NAME;
		return $wpdb->prefix . 'forms_entries_manager';
	}

	/**
	 * Get FEM Data Table
	 *
	 * @return string
	 */
	public static function data_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'forms_entries_manager_data';
	}

	/**
	 * Get FEM Transient
	 *
	 * @param string $key
	 * @return mixed
	 */
	public static function get_transient( string $key ) {
		return get_transient( self::OPTION_PREFIX . $key );
	}

	/**
	 * Delete FEM Transient
	 *
	 * @param string $key
	 */
	public static function delete_transient( string $key ): void {
		delete_transient( self::OPTION_PREFIX . $key );
	}

	/**
	 * Check if the WPFormsDB table exists.
	 *
	 * @return bool
	 */
	public static function table_exists( $table_name ): bool {
		global $wpdb;

		$table_name_like = str_replace( '_', '\\_', $table_name ); // Escape underscores
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . $table_name_like )
		);

		return ! empty( $result );
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

		$table_name = $wpdb->prefix . 'wpforms_db'; // Safe table

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
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
	 * Return a formatted REST response.
	 *
	 * @param mixed $data
	 * @param int   $status
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
	 * @param mixed  $default
	 * @return mixed
	 */
	public static function get_option( string $key, $default = false ) {
		return get_option( self::OPTION_PREFIX . $key, $default );
	}

	/**
	 * Update a namespaced plugin option.
	 *
	 * @param string $key
	 * @param mixed  $value
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
	 * Sanitize a string or array of strings recursively.
	 *
	 * @param mixed $data
	 * @return mixed
	 */
	public static function sanitize_deep( $data ) {
		if ( is_array( $data ) ) {
			return array_map( array( self::class, 'sanitize_deep' ), $data );
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
		if ( $total === 0 ) {
			return '0%';
		}
		return round( ( $done / $total ) * 100, 2 ) . '%';
	}

	/**
	 * Generate a unique hash for entry de-duplication.
	 *
	 * @param int    $form_post_id
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
                f.ID AS form_post_id,
                f.post_title,
                (SELECT COUNT(d.form_id) FROM {$db_table} d WHERE d.form_post_id = f.ID) AS entry_count
            FROM {$posts_table} f
            WHERE f.post_type = 'wpforms' AND f.post_status = 'publish'
            ORDER BY entry_count DESC
        ";

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results( $query );

		$data = array();
		if ( ! empty( $results ) ) {
			foreach ( $results as $row ) {
				$data[] = array(
					'form_post_id' => (int) $row->form_post_id,
					'post_title'   => $row->post_title,
					'entry_count'  => (int) $row->entry_count,
				);
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
		$response = wp_remote_get(
			site_url( '/wp-json/' ),
			array(
				'timeout' => 5,
				'headers' => array(
					'Accept' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );

		return ( $code >= 200 && $code < 300 );
	}

	public static function get_access_token() {
		$logger = new FileLogger();

		if ( self::is_user_revoked() ) {
			$logger->log( 'User has revoked Google connection. No access token available.', 'INFO' );
			return false;
		}

		$access_token = self::get_option( 'google_access_token' );
		$expires_at   = (int) self::get_option( 'google_token_expires', 0 );

        self::save_user_profile( self::get_option( 'google_access_token' ) );
        
		// If valid and not expired, return
		if ( $access_token && $expires_at > ( time() + 60 ) ) {
            return $access_token;
		}

		// Else: Refresh via POST request to proxy's REST endpoint
		$response = wp_remote_post(
			FEM_PROXY_BASE_URL . 'wp-json/swpfe/v1/refresh',
			array(
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => json_encode(
					array(
						'site' => self::get_settings_page_url(),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			$logger->log( 'Token refresh failed: ' . $response->get_error_message(), 'ERROR' );
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! empty( $body['access_token'] ) ) {
			self::update_option( 'google_access_token', sanitize_text_field( $body['access_token'] ) );
			self::update_option( 'google_token_expires', time() + intval( $body['expires_in'] ?? 3600 ) ); // fallback: 1 hour
			return $body['access_token'];
		}

		$logger->log( 'Invalid refresh response: ' . wp_remote_retrieve_body( $response ), 'ERROR' );
		return false;
	}

	public static function has_access_token(): bool {
		return (bool) self::get_option( 'google_access_token' );
	}

    private static function save_user_profile( $access_token ) {
        // Get existing saved profile
        $saved_profile = Helper::get_option( 'gsheet_user_profile', array() );

        // Always fetch user info with new token
        $userinfo_response = wp_remote_get(
            'https://www.googleapis.com/oauth2/v2/userinfo',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                ),
                'timeout' => 20,
            )
        );

        if ( is_wp_error( $userinfo_response ) ) {
            self::getLogger()->log( 'Failed to fetch Google user info: ' . $userinfo_response->get_error_message(), 'error' );
            return;
        }

        $userinfo = json_decode( wp_remote_retrieve_body( $userinfo_response ), true );

        if ( ! empty( $userinfo['email'] ) ) {
            $new_profile = array(
                'id'             => $userinfo['id'] ?? '',
                'email'          => sanitize_email( $userinfo['email'] ),
                'verified_email' => ! empty( $userinfo['verified_email'] ) ? 1 : 0,
                'name'           => sanitize_text_field( $userinfo['name'] ?? '' ),
                'given_name'     => sanitize_text_field( $userinfo['given_name'] ?? '' ),
                'family_name'    => sanitize_text_field( $userinfo['family_name'] ?? '' ),
                'picture'        => esc_url_raw( $userinfo['picture'] ?? '' ),
            );

            // Update only if email changed or profile was empty
            if ( empty( $saved_profile ) || ( $saved_profile['email'] ?? '' ) !== $new_profile['email'] ) {
                Helper::update_option( 'gsheet_user_profile', $new_profile );
                self::getLogger()->log( 'User info is saved/updated', 'info' );
            } else {
                self::getLogger()->log( 'User info unchanged, no update needed', 'info' );
            }
        } else {
            self::getLogger()->log( 'Google userinfo response invalid: ' . print_r( $userinfo, true ), 'error' );
        }
    }

	/**
	 * Revokes the Google Sheets connection by making a request to the proxy service.
	 * Deletes local tokens upon a successful response.
	 *
	 * @return bool True if the proxy confirms revocation, false otherwise.
	 */
	public static function revokeConnection(): bool {
		// The proxy needs to know which site to revoke the token for.
		$site_url = self::get_settings_page_url();

		// Make a POST request to your proxy's revoke endpoint.
		// Assuming your proxy has a dedicated endpoint for this purpose.
		$response = wp_remote_post(
			FEM_PROXY_BASE_URL . 'wp-json/swpfe/v1/revoke',
			array(
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => json_encode(
					array(
						'site' => $site_url,
					)
				),
			)
		);

		// Check for a successful response from the proxy.
		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			self::getLogger()->log( 'Proxy-based token revocation failed: ' . wp_remote_retrieve_body( $response ), 'ERROR' );
			// Even if the proxy fails, we can still try to clear our local data to show a disconnected state.
		}

		self::getLogger()->log( 'Proxy revocation response: ' . wp_remote_retrieve_body( $response ), 'INFO' );

		// Step 2: Delete the local tokens from your database.
		// This is crucial regardless of the proxy's response to ensure your app reflects the disconnected state.
		$deleted_access  = self::delete_option( 'google_access_token' );
		$deleted_expires = self::delete_option( 'google_token_expires' );

		self::update_option( 'user_remvoked_google_connection', true );

		// Unschedule all synchronization actions.
		if ( $deleted_access || $deleted_expires ) {
			as_unschedule_all_actions( 'fem_every_five_minute_sync' );
			// The key addition: also unschedule the daily sync.
			as_unschedule_all_actions( 'fem_daily_sync' );
		}

		// Return true if the local deletion was successful, confirming the disconnected state.
		return $deleted_access || $deleted_expires;
	}

	public static function is_user_revoked() {
		return self::get_option( 'user_remvoked_google_connection' );
	}

	/**
	 * Checks if the user is currently authenticated with Google
	 * by verifying if a valid access token can be retrieved.
	 *
	 * This method leverages the existing get_access_token() logic.
	 *
	 * @return bool True if a valid Google access token is available, false otherwise.
	 */
	public static function is_google_authorized() {
		return (bool) self::get_access_token();
	}

	/**
	 * Get Settings page url wihout sanitization
	 *
	 * @return string
	 */
	public static function get_settings_page_url() {
		return admin_url( 'admin.php?page=form-entries-settings' );
	}

	/**
	 * Retrieves the number of seconds remaining until the Google token expires.
	 *
	 * @return int Number of seconds until token expiration. Returns 0 if expired or not set.
	 */
	public static function get_token_expires_in(): int {

		$token_expires = self::get_option( 'google_token_expires' );
		$now           = time();

		return $token_expires ? max( 0, $token_expires - $now ) : 0;
	}

	/**
	 * Converts a number of seconds into a human-readable string format.
	 *
	 * @param int $seconds The number of seconds to convert.
	 * @return string Human-readable representation (e.g., "2h 15m", "Expired", "Less than a minute").
	 */
	public static function seconds_to_human_readable( int $seconds ): string {
		if ( $seconds <= 0 ) {
			return esc_html__( 'Expired', 'forms-entries-manager' );
		}

		$h = floor( $seconds / 3600 );
		$m = floor( ( $seconds % 3600 ) / 60 );

		if ( $h || $m ) {
			return trim( ( $h ? $h . 'h ' : '' ) . ( $m ? $m . 'm' : '' ) );
		}

		return esc_html__( 'Less than a minute', 'forms-entries-manager' );
	}

	/**
	 * Checks if the Pro version of the plugin is active.
	 * This method is crucial for handling free/pro version limitations.
	 *
	 * @return bool
	 */
	public static function is_pro_version() {
		// For demonstration, we assume a constant is defined in the Pro version.
		// In a real plugin, this constant would be defined in the main plugin file of the Pro version.
		// Example: define('FEM_PRO_VERSION', true);
		// return defined('FEM_PRO_VERSION') && FEM_PRO_VERSION;
		return true;
	}

		// Get entries by IDs with caching
	public static function get_entries_by_ids( array $ids ): array {
		if ( empty( $ids ) ) {
			return array();
		}

		$cache_key = 'fem_entries_' . md5( implode( ',', $ids ) );
		$entries   = wp_cache_get( $cache_key, 'fem' );

        // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		if ( false === $entries ) {
			global $wpdb;
			$table = self::get_table_name(); // Safe table

			// Build placeholders
			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

			// Expand $ids array into arguments using the splat operator
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$entries = $wpdb->get_results(
				$wpdb->prepare(
                    // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"SELECT * FROM {$table} WHERE id IN ($placeholders)",
					...$ids
				),
				ARRAY_A
			);

			wp_cache_set( $cache_key, $entries, 'fem', 300 ); // cache 5 mins
		}

		return $entries ?: array();
	}

	// Update a single entry with caching
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	public static function update_entry( int $id, array $data ): bool {
		global $wpdb;
		$table = self::get_table_name();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->update( $table, $data, array( 'id' => $id ) );

		if ( $result !== false ) {
			wp_cache_delete( 'fem_entries_' . $id, 'fem' );
		}

		return $result !== false;
	}

	// Delete a single entry with caching
	public static function delete_entry( int $id ): bool {
		global $wpdb;
		$table = self::get_table_name();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->delete( $table, array( 'id' => $id ) );

		if ( $result !== false ) {
			wp_cache_delete( 'fem_entries_' . $id, 'fem' );
		}

		return $result !== false;
	}

	public static function fputcsv( $handle, array $fields ) {
		$line = '';
		foreach ( $fields as $f ) {
			$line .= '"' . str_replace( '"', '""', $f ) . '",';
		}
		$line = rtrim( $line, ',' ) . "\n";
		global $wp_filesystem;
		$wp_filesystem->fwrite( $handle, $line );
	}

	/**
	 * Get all unique form IDs from the entries table.
	 *
	 * @return string[] Array of unique form IDs.
	 */
	public static function get_all_forms() {
		// Get all published Forms From Our DB
		global $wpdb;
		$table = self::get_table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$forms = $wpdb->get_results( "SELECT DISTINCT form_id FROM {$table} WHERE form_id IS NOT NULL AND form_id != 0", ARRAY_A );

		// Flaten the array to get just form IDs
		$forms = array_map(
			function ( $item ) {
				return $item['form_id'];
			},
			$forms
		);

		return $forms;
	}
}
