<?php

namespace App\AdvancedEntryManager\Api\Callback;

defined( 'ABSPATH' ) || exit;

use App\AdvancedEntryManager\Core\Handle_Cache;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use App\AdvancedEntryManager\Utility\Helper;

/**
 * Class Get_Forms
 *
 * Handles the retrieval of forms from the custom database table.
 */
class Get_Forms {
	/**
	 * Get list of forms with their entry counts.
	 *
	 * Queries the custom entries table to retrieve all unique form IDs and
	 * the number of entries associated with each form. Also fetches the form
	 * title using `get_the_title()`. The result is formatted as a REST response.
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @return WP_REST_Response JSON-formatted response containing form data:
	 *                          - form_id (int)
	 *                          - form_title (string)
	 *                          - entry_count (int)
	 */
	public function get_forms() {
		// Initialize the cache handler
		$fem_cache = new Handle_Cache();
		$cache_key = 'forms_summary_counts';

		// Try to get data from the cache first
		$response = $fem_cache->get_object_cache( $cache_key );

		// If cache is empty, run the database query and populate the cache
		if ( false === $response ) {
			global $wpdb;
			$table = Helper::get_table_name(); // Safe table

			// Query distinct form IDs and their entry counts
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT 
                    form_id, 
                    COUNT(*) as entry_count,
                    SUM(CASE WHEN status = 'unread' THEN 1 ELSE 0 END) as unread_count
                FROM `{$table}`
                GROUP BY form_id"
				),
				OBJECT
			);

			$forms = array();

			foreach ( $results as $row ) {
				$form_id = (int) $row->form_id;

				// Populate the forms array
				$forms[] = array(
					'form_id'       => $form_id,
					'form_title'    => get_the_title( $form_id ),
					'entry_count'   => (int) $row->entry_count,
					'number_unread' => (int) $row->unread_count,
				);
			}

			// Filter the list of forms returned by get_forms()
			$response = apply_filters( 'fem_get_forms', $forms );

			// Store the final processed data in the cache for 1 hour
			$fem_cache->set_object_cache( $cache_key, $response, HOUR_IN_SECONDS );
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Retrieve a list of unique form field keys for a given WPForms form ID.
	 *
	 * This endpoint is used to dynamically fetch the field names from a sample
	 * entry, typically to allow users to customize export settings (e.g., include/exclude columns).
	 *
	 * ## Example Request:
	 * GET /wp-json/fem/v1/form-fields?form_id=123
	 *
	 * @param WP_REST_Request $request The REST request object containing 'form_id'.
	 *
	 * @return WP_REST_Response|WP_Error List of field keys or a WP_Error on failure.
	 */
	public function get_form_fields( WP_REST_Request $request ) {
		// Initialize the cache handler
		$fem_cache = new Handle_Cache();
		$form_id   = isset( $request['form_id'] ) ? absint( $request['form_id'] ) : 0;
		$cache_key = 'form_' . $form_id . '_fields';

		if ( $form_id <= 0 ) {
			return new WP_Error(
				'feminvalid_form_id',
				__( 'Invalid or missing form ID.', 'forms-entries-manager' ),
				array( 'status' => 400 )
			);
		}

		// Try to get data from the cache first
		$cached_fields = $fem_cache->get_object_cache( $cache_key );

		if ( false !== $cached_fields ) {
			return rest_ensure_response(
				array(
					'fields' => $cached_fields,
				)
			);
		}

		global $wpdb;
		$table = Helper::get_table_name(); // Safe table

		// Fetch a few rows to detect fields (faster than scanning all)
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE form_id = %d LIMIT 5",
				$form_id
			),
			ARRAY_A
		);

		$allowed_fields = array( 'id', 'form_id', 'email', 'note', 'created_at', 'status', 'is_favorite' );
		$fields         = array();

		foreach ( $rows as $row ) {
			// Step 1: Add only allowed top-level DB columns
			foreach ( $allowed_fields as $column ) {
				if ( array_key_exists( $column, $row ) ) {
					$fields[ $column ] = true;
				}
			}

			// Step 2: Merge in keys from deserialized 'entry'
			if ( isset( $row['entry'] ) ) {
				$entry = maybe_unserialize( $row['entry'] );
				if ( is_array( $entry ) ) {
					foreach ( array_keys( $entry ) as $field_key ) {
						$fields[ $field_key ] = true;
					}
				}
			}
		}

		$final_fields = array_values( array_unique( array_keys( $fields ) ) );

		// Store the results in the cache for 1 hour.
		$fem_cache->set_object_cache( $cache_key, $final_fields, HOUR_IN_SECONDS );

		return rest_ensure_response(
			array(
				'fields' => $final_fields,
			)
		);
	}
}
