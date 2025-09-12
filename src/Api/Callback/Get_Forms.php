<?php

namespace App\AdvancedEntryManager\Api\Callback;

defined( 'ABSPATH' ) || exit;

use App\AdvancedEntryManager\Core\Handle_Cache;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use App\AdvancedEntryManager\Utility\Helper;
use Error;
/**
 * Class Get_Forms
 *
 * Handles the retrieval of forms from the custom database tables.
 */
class Get_Forms {

	/**
	 * Get list of forms with their entry counts and unread counts.
	 *
	 * @return \WP_REST_Response JSON-formatted response with form data.
	 */
	public function get_forms() {
		$cache_key = 'forms_cache_';
		$response  = Helper::get_option( $cache_key );

		if ( false !== $response ) {
			return rest_ensure_response( $response );
		}

		global $wpdb;
		$submissions_table = Helper::get_submission_table();

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
                form_id, 
                COUNT(*) as entry_count,
                SUM(CASE WHEN status = 'unread' THEN 1 ELSE 0 END) as unread_count
            FROM `{$submissions_table}` 
            GROUP BY form_id"
			),
			OBJECT
		);

		$forms = array();
		foreach ( $results as $row ) {
			$form_id = (int) $row->form_id;

			$forms[] = array(
				'form_id'       => $form_id,
				'form_title'    => get_the_title( $form_id ),
				'entry_count'   => (int) $row->entry_count,
				'number_unread' => (int) $row->unread_count,
			);
		}

		$response = apply_filters( 'fem_get_forms', $forms );

		// Cache the result for future use.
		Helper::update_option( $cache_key, $response );

		return rest_ensure_response( $response );
	}

	/**
	 * Retrieve a list of unique form field keys for a given form ID.
	 *
	 * @param WP_REST_Request $request The REST request object containing 'form_id'.
	 * @return \WP_REST_Response|\WP_Error List of field keys or a WP_Error on failure.
	 */
	public function get_form_fields( WP_REST_Request $request ) {
		$form_id = isset( $request['form_id'] ) ? absint( $request['form_id'] ) : 0;

		if ( $form_id <= 0 ) {
			return new WP_Error(
				'fem_invalid_form_id',
				__( 'Invalid or missing form ID.', 'forms-entries-manager' ),
				array( 'status' => 400 )
			);
		}

		$cache_handler = new Handle_Cache();
		$cache_key     = 'form_' . $form_id . '_fields';

		// Try to get data from cache first.
		$cached_data = $cache_handler->get_object_cache( $cache_key );
		if ( false !== $cached_data ) {
			return rest_ensure_response( $cached_data );
		}

		// Fetch fields from the database.
		list($fields, $entry_schema) = $this->fetch_fields_from_db( $form_id );

		// Build the final response data.
		$response_data = array(
			'fields'       => $fields,
			'entry_schema' => $entry_schema,
		);

		// Store the results in the cache for 1 hour.
		$cache_handler->set_object_cache( $cache_key, $response_data, HOUR_IN_SECONDS );

		return rest_ensure_response( $response_data );
	}

	/**
	 * Fetches unique field keys and builds a schema for a given form ID.
	 *
	 * @param int $form_id The form ID to fetch fields for.
	 * @return array An array containing the full list of fields and a schema.
	 */
	private function fetch_fields_from_db( $form_id ) {
		global $wpdb;
		$entries_table     = Helper::get_data_table();
		$submissions_table = Helper::get_submission_table();

		// Fetch all unique field keys for the given form ID.
		// We join to submissions table to ensure we only get fields for a specific form.
		$sql = "SELECT DISTINCT t1.field_key 
                FROM $entries_table AS t1
                JOIN $submissions_table AS t2 ON t1.submission_id = t2.id
                WHERE t2.form_id = %d";

		$fields_raw = $wpdb->get_col(
			$wpdb->prepare( $sql, $form_id )
		);

		$fields       = array();
		$entry_schema = array();

		// Build the fields list and schema.
		foreach ( $fields_raw as $field_key ) {
			$fields[] = $field_key;
			if ( ! $this->is_system_key( $field_key ) ) {
				$entry_schema[] = array(
					'key'   => $field_key,
					'label' => ucwords( str_replace( array( '-', '_' ), ' ', $field_key ) ),
				);
			}
		}

		return array( $fields, $entry_schema );
	}

	/**
	 * Checks if a field key is a system key that should be ignored.
	 *
	 * @param string $key The field key to check.
	 * @return bool
	 */
	private function is_system_key( $key ) {
		$key = strtolower( $key );
		return strpos( $key, 'g-recaptcha-response' ) !== false || strpos( $key, 'file' ) !== false || strpos( $key, '_wpcf7' ) !== false;
	}
}
