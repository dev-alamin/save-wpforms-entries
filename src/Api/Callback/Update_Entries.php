<?php

namespace App\AdvancedEntryManager\Api\Callback;

defined( 'ABSPATH' ) || exit;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use App\AdvancedEntryManager\Utility\Helper;
use App\AdvancedEntryManager\GoogleSheet\Send_Data;

/**
 * Class Update_Entries
 *
 * Handles the retrieval of entries from the custom database table.
 */
class Update_Entries {
	/**
	 * Update an existing WPForms entry row.
	 *
	 * Supports PATCH-style partial updates or full PUT updates.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response
	 */
	public function update_entry( WP_REST_Request $request ) {
		global $wpdb;
		$table = Helper::get_table_name();

		$params = $request->get_json_params();

		// Require entry ID and form ID, sanitize
		$id      = isset( $params['id'] ) ? absint( $params['id'] ) : 0;
		$form_id = isset( $params['form_id'] ) ? absint( $params['form_id'] ) : 0;

		if ( ! $id || ! $form_id ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Missing or invalid entry ID or form ID.', 'forms-entries-manager' ),
				),
				400
			);
		}

		// Build update data only from present fields
		$data   = array();
		$format = array();

		if ( isset( $params['entry'] ) && is_array( $params['entry'] ) ) {
			$data['entry'] = maybe_serialize( $params['entry'] );
			$format[]      = '%s';
		}

		if ( isset( $params['status'] ) ) {
			$data['status'] = sanitize_text_field( $params['status'] );
			$format[]       = '%s';
		}

		if ( isset( $params['is_favorite'] ) ) {
			$data['is_favorite'] = absint( $params['is_favorite'] );
			$format[]            = '%d';
		}

		if ( isset( $params['note'] ) ) {
			$raw_note = sanitize_textarea_field( $params['note'] );

			// Limit character length (hard limit for DB and performance)
			$max_length   = 1000;
			$trimmed_note = mb_substr( $raw_note, 0, $max_length );

			$data['note'] = $trimmed_note;
			$format[]     = '%s';
		}

		if ( isset( $params['exported_to_csv'] ) ) {
			$data['exported_to_csv'] = absint( $params['exported_to_csv'] );
			$format[]                = '%d';
		}

		if ( isset( $params['synced_to_gsheet'] ) ) {
			$data['synced_to_gsheet'] = absint( $params['synced_to_gsheet'] );
			$format[]                 = '%d';
		}

		if ( isset( $params['printed_at'] ) ) {
			$data['printed_at'] = wp_date( 'Y-m-d H:i:s', strtotime( $params['printed_at'] ) );
			$format[]           = '%s';
		}

		if ( isset( $params['resent_at'] ) ) {
			$data['resent_at'] = wp_date( 'Y-m-d H:i:s', strtotime( $params['resent_at'] ) );
			$format[]          = '%s';
		}

		// If no fields provided to update
		if ( empty( $data ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'No valid fields provided for update.', 'forms-entries-manager' ),
				),
				400
			);
		}

		/**
		 * Fires before an entry update is performed.
		 *
		 * @param int             $id      Entry ID.
		 * @param array           $data    Data to update (column => value).
		 * @param WP_REST_Request $request Full REST request object.
		 */
		do_action( 'fembefore_entry_update', $id, $data, $request );

		// Perform DB update
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated = $wpdb->update(
			$table,
			$data,
			array( 'id' => $id ),
			$format,
			array( '%d' )
		);

		if ( $updated === false ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Database update failed.', 'forms-entries-manager' ),
				),
				500
			);
		}

		/**
		 * Fires after an entry has been successfully updated.
		 *
		 * @param int             $id      Entry ID.
		 * @param array           $data    Data that was updated.
		 * @param WP_REST_Request $request Full REST request object.
		 */
		do_action( 'femafter_entry_update', $id, $data, $request );

		return new WP_REST_Response(
			array(
				'success'        => true,
				'message'        => __( 'Entry updated successfully.', 'forms-entries-manager' ),
				'updated_fields' => array_keys( $data ),
				'entry_id'       => $id,
			),
			200
		);
	}

	/**
	 * The callback function to handle the unsync request.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_unsync_request( WP_REST_Request $request ) {
		$entry_id = absint( $request['id'] );
		if ( ! $entry_id ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Invalid or missing entry ID.', 'forms-entries-manager' ),
				),
				400
			);
		}

		$send_data = new Send_Data();
		$result    = $send_data->unsync_entry_from_sheet( $entry_id );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => $result->get_error_message(), // Assuming WP_Error already has human-readable text
				),
				500
			); // Internal Server Error
		} elseif ( ! $result ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Failed to unsync entry from Google Sheet.', 'forms-entries-manager' ),
				),
				500
			); // Internal Server Error
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Entry successfully unsynced from Google Sheet.', 'forms-entries-manager' ),
			),
			200
		); // OK
	}

	/**
	 * The callback function to handle the unsync request.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_sync_request( WP_REST_Request $request ) {
		$is_authorized = Helper::is_google_authorized();

		if ( ! $is_authorized ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'You have not authorize google, please do it from settings page.', 'forms-entries-manager' ),
				),
				400
			);
		}

		$entry_id = absint( $request['id'] );

		if ( ! $entry_id ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Invalid or missing entry ID.', 'forms-entries-manager' ),
				),
				400
			);
		}

		$send_data = new Send_Data();

		$send = $send_data->process_single_entry( array( 'entry_id' => $entry_id ) );

		if ( $send ) {
			return rest_ensure_response(
				array(
					'success' => true,
					'message' => __( 'Entry successfully sync to Google Sheet.', 'forms-entries-manager' ),
				),
				200
			); // OK
		} else {
			return rest_ensure_response(
				array(
					'success' => false,
					'message' => __( 'Failed to sync entry to Google Sheet. Please check the logs.', 'forms-entries-manager' ),
				),
				500
			); // Internal Server Error
		}
	}
}
