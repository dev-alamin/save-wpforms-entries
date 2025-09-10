<?php

namespace App\AdvancedEntryManager\Api\Callback;

defined( 'ABSPATH' ) || exit;

use App\AdvancedEntryManager\Utility\Helper;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Class Create_Entries
 *
 * Handles the retrieval of entries from the custom database table.
 */
class Create_Entries {
	/**
	 * Handle creation of a new WPForms entry saved into custom DB table using rest.
	 *
	 * This method accepts a REST POST request and stores form entry data into
	 * the custom `fem_entries_manager` table. It supports metadata like read status,
	 * favorite flag, export/sync tracking, and internal notes.
	 *
	 * @param WP_REST_Request $request The incoming REST request with form entry data.
	 *
	 * @return WP_REST_Response A JSON response indicating success/failure, including inserted entry ID if successful.
	 */
	public function create_entries( WP_REST_Request $request ) {
		global $wpdb;
		$table = Helper::get_submission_table(); // e.g., 'fem_entries_manager'

		// Temp off
		// return rest_ensure_response( ['success' => false, 'message' => __('This endpoint is temporarily disabled.', 'forms-entries-manager')] );

		// Get parameters from JSON body
		$params = $request->get_json_params();

		// Sanitize and validate required fields
		$form_id = isset( $params['form_id'] ) ? absint( $params['form_id'] ) : 0;
		$entry   = isset( $params['entry'] ) ? $params['entry'] : null; // expecting array

		if ( ! $form_id || ! is_array( $entry ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Invalid or missing form_id or entry data.', 'forms-entries-manager' ),
				),
				400
			);
		}

		// Optional fields with sanitization and normalization
		$status           = isset( $params['status'] ) ? sanitize_text_field( $params['status'] ) : 'unread';
		$is_favorite      = isset( $params['is_favorite'] ) ? absint( $params['is_favorite'] ) : 0;
		$note             = isset( $params['note'] ) ? sanitize_textarea_field( $params['note'] ) : null;
		$exported_to_csv  = isset( $params['exported_to_csv'] ) ? absint( $params['exported_to_csv'] ) : 0;
		$synced_to_gsheet = isset( $params['synced_to_gsheet'] ) ? absint( $params['synced_to_gsheet'] ) : 0;
		// Normalize datetime fields or set null if empty/invalid
		$printed_at = ! empty( $params['printed_at'] ) ? gmdate( 'Y-m-d H:i:s', strtotime( $params['printed_at'] ) ) : null;
		$resent_at  = ! empty( $params['resent_at'] ) ? gmdate( 'Y-m-d H:i:s', strtotime( $params['resent_at'] ) ) : null;

		// Optional: capability check (uncomment if needed)
		// if ( ! current_user_can( 'manage_options' ) ) {
		// return new WP_REST_Response([
		// 'success' => false,
		// 'message' => __( 'Insufficient permissions to create entry.', 'forms-entries-manager' ),
		// ], 403);
		// }

		/**
		 * Fires before inserting a new entry.
		 *
		 * @param int             $form_id Form ID.
		 * @param array           $entry   Entry data (array).
		 * @param array           $params  Full request parameters.
		 * @param WP_REST_Request $request REST request object.
		 */
		do_action( 'fem_before_entry_create', $form_id, $entry, $params, $request );

		// Prepare data for DB insert
		$data = array(
			'form_id'          => $form_id,
			'entry'            => maybe_serialize( $entry ),
			'status'           => $status,
			'is_favorite'      => $is_favorite,
			'note'             => $note,
			'exported_to_csv'  => $exported_to_csv,
			'synced_to_gsheet' => $synced_to_gsheet,
			'printed_at'       => $printed_at,
			'resent_at'        => $resent_at,
			'created_at'       => current_time( 'mysql' ),
		);

		// Define formats — handle nullable string fields properly
		$format = array(
			'%d', // form_id
			'%s', // entry
			'%s', // status
			'%d', // is_favorite
			$note === null ? '%s' : '%s', // note (allow null)
			'%d', // exported_to_csv
			'%d', // synced_to_gsheet
			$printed_at === null ? '%s' : '%s', // printed_at (allow null)
			$resent_at === null ? '%s' : '%s', // resent_at (allow null)
			'%s', // created_at
		);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inserted = $wpdb->insert( $table, $data, $format );

		if ( $inserted === false ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Database insert failed.', 'forms-entries-manager' ),
				),
				500
			);
		}

		/**
		 * Fires after successfully inserting a new entry.
		 *
		 * @param int             $entry_id Inserted entry ID.
		 * @param int             $form_id  Form ID.
		 * @param array           $entry    Entry data.
		 * @param array           $params   Full request parameters.
		 * @param WP_REST_Request $request  REST request object.
		 */
		do_action( 'fem_after_entry_create', $wpdb->insert_id, $form_id, $entry, $params, $request );

		return new WP_REST_Response(
			array(
				'success'  => true,
				'message'  => __( 'Entry created successfully.', 'forms-entries-manager' ),
				'entry_id' => $wpdb->insert_id,
			),
			201
		);
	}
}
