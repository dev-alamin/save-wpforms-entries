<?php
namespace App\AdvancedEntryManager\Api\Callback;

defined( 'ABSPATH' ) || exit;

use App\AdvancedEntryManager\Utility\Helper;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class Bulk_Action {

	public function bulk_actions( WP_REST_Request $request ) {
		$ids    = array_unique( array_map( 'absint', $request->get_param( 'ids' ) ) );
		$action = sanitize_text_field( $request->get_param( 'action' ) );

		if ( empty( $ids ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Invalid or missing entry IDs.', 'forms-entries-manager' ),
				),
				400
			);
		}

		$valid_actions = array( 'delete', 'mark_read', 'mark_unread', 'favorite', 'unfavorite', 'mark_spam', 'unmark_spam' );
		if ( ! in_array( $action, $valid_actions, true ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Invalid action provided.', 'forms-entries-manager' ),
				),
				400
			);
		}

		$affected    = 0;
		$deleted_ids = array();
		$updated_ids = array();

		foreach ( $ids as $id ) {
			switch ( $action ) {
				case 'delete':
					if ( Helper::delete_entry( $id ) ) {
						$deleted_ids[] = $id;
						++$affected;
					}
					// Invalidate cached form fields and forms list
					Helper::delete_option( 'forms_cache' );
					break;

				case 'mark_read':
					if ( Helper::update_entry( $id, array( 'status' => 'read' ) ) ) {
						$updated_ids[] = $id;
						++$affected;
					}
					break;

				case 'mark_unread':
					if ( Helper::update_entry( $id, array( 'status' => 'unread' ) ) ) {
						$updated_ids[] = $id;
						++$affected;
					}
					break;

				case 'favorite':
					if ( Helper::update_entry( $id, array( 'is_favorite' => 1 ) ) ) {
						$updated_ids[] = $id;
						++$affected;
					}
					break;

				case 'unfavorite':
					if ( Helper::update_entry( $id, array( 'is_favorite' => 0 ) ) ) {
						$updated_ids[] = $id;
						++$affected;
					}
					break;

				case 'mark_spam':
					if ( Helper::update_entry( $id, array( 'is_spam' => 1 ) ) ) {
						$updated_ids[] = $id;
						++$affected;
					}
					break;

				case 'unmark_spam':
					if ( Helper::update_entry( $id, array( 'is_spam' => 0 ) ) ) {
						$updated_ids[] = $id;
						++$affected;
					}
					break;
			}
		}

		return rest_ensure_response(
			array(
				'success'     => true,
				'message'     => $action === 'delete'
					? sprintf(
						/* translators: %d is the number of deleted entries */
						_n( '%d entry deleted.', '%d entries deleted.', count( $deleted_ids ), 'forms-entries-manager' ),
						count( $deleted_ids )
					)
					: sprintf(
						/* translators: %d is the number of updated entries */
						_n( '%d entry updated.', '%d entries updated.', count( $updated_ids ), 'forms-entries-manager' ),
						count( $updated_ids )
					),

				'deleted_ids' => $deleted_ids,
				'updated_ids' => $updated_ids,
				'affected'    => $affected,
			)
		);
	}

	public function export_entries_csv_bulk( WP_REST_Request $request ) {
		$ids = $request->get_param( 'ids' );

		if ( empty( $ids ) || ! is_array( $ids ) ) {
			return new WP_Error( 'invalid_data', __( 'No entries selected.', 'forms-entries-manager' ), array( 'status' => 400 ) );
		}

		$entries = Helper::get_entries_by_ids( $ids );

		if ( empty( $entries ) ) {
			return new WP_Error( 'no_data', __( 'No data found.', 'forms-entries-manager' ), array( 'status' => 404 ) );
		}

		// Load WordPress filesystem
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();
		global $wp_filesystem;

		// Create temp file using WordPress temp path
		$tmp_file = wp_tempnam( 'fem-entries.csv' );
		if ( ! $tmp_file ) {
			return new WP_Error( 'fs_error', __( 'Unable to create temp file.', 'forms-entries-manager' ), array( 'status' => 500 ) );
		}

		// Open file for writing
		$handle = $wp_filesystem->fopen( $tmp_file, 'w' );

		// Write headers
		$first      = $entries[0];
		$entry_data = maybe_unserialize( $first['entry'] );
		$headers    = array_keys( $entry_data );
		array_unshift( $headers, 'id' );
		Helper::fputcsv( $handle, $headers );

		// Write rows
		foreach ( $entries as $entry ) {
			$data = maybe_unserialize( $entry['entry'] );
			$row  = array( $entry['id'] );
			foreach ( array_slice( $headers, 1 ) as $key ) {
				$row[] = $data[ $key ] ?? '-';
			}
			Helper::fputcsv( $handle, $row );
		}

		// Close file
		$wp_filesystem->fclose( $handle );

		// Read file content
		$csv_content = $wp_filesystem->get_contents( $tmp_file );

		// Delete temp file
		$wp_filesystem->delete( $tmp_file );

		// Return CSV as base64 string via REST
		return rest_ensure_response(
			array(
				'success'  => true,
				'filename' => 'fem-entries.csv',
				'csv'      => base64_encode( $csv_content ),
			)
		);
	}
}
