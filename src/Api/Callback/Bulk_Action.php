<?php
namespace App\AdvancedEntryManager\Api\Callback;

defined( 'ABSPATH' ) || exit;

use App\AdvancedEntryManager\Utility\Helper;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
class Bulk_Action {

	/**
	 * Handles bulk actions on entries.
	 *
	 * @param WP_REST_Request $request The REST API request.
	 * @return WP_REST_Response
	 */
	public function bulk_actions( WP_REST_Request $request ) {
		global $wpdb;
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

		$submissions_table = Helper::get_submission_table();
		$affected          = 0;
		$deleted_ids       = array();
		$updated_ids       = array();

		$ids_placeholder = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		switch ( $action ) {
			case 'delete':
				// Use a single query for a more efficient bulk delete.
				// The FOREIGN KEY with ON DELETE CASCADE will handle the entries table.
				$sql         = $wpdb->prepare( "DELETE FROM `$submissions_table` WHERE id IN ($ids_placeholder)", ...$ids );
				$affected    = $wpdb->query( $sql );
				$deleted_ids = $ids; // Assume all requested IDs were deleted.

				// Invalidate cached form fields and forms list
				Helper::delete_option( 'forms_cache' );
				break;

			case 'mark_read':
			case 'mark_unread':
			case 'favorite':
			case 'unfavorite':
			case 'mark_spam':
			case 'unmark_spam':
				$update_data = $this->get_update_data_for_action( $action );
				if ( ! empty( $update_data ) ) {
					list($set_clause, $params) = $this->build_update_query_from_data( $update_data );

					$sql = $wpdb->prepare(
						"UPDATE `$submissions_table` SET $set_clause WHERE id IN ($ids_placeholder)",
						...array_merge( $params, $ids )
					);

					$affected    = $wpdb->query( $sql );
					$updated_ids = $ids; // Assume all requested IDs were updated.
				}
				break;
			default:
				return new WP_REST_Response(
					array(
						'success' => false,
						'message' => __( 'Invalid action provided.', 'forms-entries-manager' ),
					),
					400
				);
		}

		return rest_ensure_response(
			array(
				'success'     => true,
				'message'     => $action === 'delete'
					? sprintf( _n( '%d entry deleted.', '%d entries deleted.', $affected, 'forms-entries-manager' ), $affected )
					: sprintf( _n( '%d entry updated.', '%d entries updated.', $affected, 'forms-entries-manager' ), $affected ),
				'deleted_ids' => $deleted_ids,
				'updated_ids' => $updated_ids,
				'affected'    => $affected,
			)
		);
	}

	/**
	 * Gets the data to be updated based on the action.
	 *
	 * @param string $action The bulk action.
	 * @return array An associative array of data to be updated.
	 */
	private function get_update_data_for_action( $action ) {
		$data = array();
		switch ( $action ) {
			case 'mark_read':
				$data['status'] = 'read';
				break;
			case 'mark_unread':
				$data['status'] = 'unread';
				break;
			case 'favorite':
				$data['is_favorite'] = 1;
				break;
			case 'unfavorite':
				$data['is_favorite'] = 0;
				break;
			case 'mark_spam':
				$data['is_spam'] = 1;
				break;
			case 'unmark_spam':
				$data['is_spam'] = 0;
				break;
		}
		return $data;
	}

	/**
	 * Builds the SET clause for an UPDATE query.
	 *
	 * @param array $data The data to update.
	 * @return array An array containing the SET clause and parameters.
	 */
	private function build_update_query_from_data( array $data ) {
		$set_clause = array();
		$params     = array();
		foreach ( $data as $key => $value ) {
			$set_clause[] = "$key = %s";
			$params[]     = $value;
		}
		return array( implode( ', ', $set_clause ), $params );
	}

	/**
	 * Exports a bulk selection of entries as a CSV file.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function export_entries_csv_bulk( WP_REST_Request $request ) {
		global $wpdb;
		$ids = array_unique( array_map( 'absint', $request->get_param( 'ids' ) ) );

		if ( empty( $ids ) ) {
			return new WP_Error( 'invalid_data', __( 'No entries selected.', 'forms-entries-manager' ), array( 'status' => 400 ) );
		}

		$submissions_table = Helper::get_submission_table();
		$entries_table     = Helper::get_data_table();

		// Fetch all data in two queries for efficiency.
		$ids_placeholder = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$submissions     = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM `$submissions_table` WHERE id IN ($ids_placeholder)", ...$ids ),
			ARRAY_A
		);
		$entries_raw     = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM `$entries_table` WHERE submission_id IN ($ids_placeholder)", ...$ids ),
			ARRAY_A
		);

		if ( empty( $submissions ) ) {
			return new WP_Error( 'no_data', __( 'No data found.', 'forms-entries-manager' ), array( 'status' => 404 ) );
		}

		// Process and structure the data.
		list($csv_rows, $headers) = $this->prepare_csv_data( $submissions, $entries_raw );

		// Load WordPress filesystem.
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();
		global $wp_filesystem;

		// Create a temp file path using WordPress temp path.
		$tmp_file = wp_tempnam( 'fem-entries.csv' );
		if ( ! $tmp_file ) {
			return new WP_Error( 'fs_error', __( 'Unable to create temp file.', 'forms-entries-manager' ), array( 'status' => 500 ) );
		}

		// Build CSV content in memory.
		$csv_content  = '';
		$csv_content .= Helper::get_csv_line( $headers );
		foreach ( $csv_rows as $row ) {
			$csv_content .= Helper::get_csv_line( $row );
		}

		// Write the full CSV content to the file using put_contents().
		if ( ! $wp_filesystem->put_contents( $tmp_file, $csv_content, FS_CHMOD_FILE ) ) {
			return new WP_Error( 'fs_write_error', __( 'Unable to write to temp file.', 'forms-entries-manager' ), array( 'status' => 500 ) );
		}

		// Set HTTP headers for file download
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="fem-entries.csv"' );

		// Output the raw CSV content directly.
		echo $csv_content;

		// Important: Exit the script to prevent any other output.
		exit;
	}

	/**
	 * Prepares the data for CSV export.
	 *
	 * @param array $submissions The submissions data.
	 * @param array $entries_raw The raw entries data.
	 * @return array An array containing the prepared rows and a unique set of headers.
	 */
	private function prepare_csv_data( $submissions, $entries_raw ) {
		$data_by_submission = array();
		$all_keys           = array( 'id', 'name', 'email', 'status', 'created_at' );

		// Group entries by submission_id.
		foreach ( $entries_raw as $entry ) {
			$data_by_submission[ $entry['submission_id'] ][ $entry['field_key'] ] = $entry['field_value'];
			if ( ! in_array( $entry['field_key'], $all_keys ) ) {
				$all_keys[] = $entry['field_key'];
			}
		}

		$csv_rows = array();
		foreach ( $submissions as $submission ) {
			$row = array(
				$submission['id'],
				$submission['name'],
				$submission['email'],
				$submission['status'],
				$submission['created_at'],
			);

			// Add dynamic field values.
			$submission_id = $submission['id'];
			if ( isset( $data_by_submission[ $submission_id ] ) ) {
				foreach ( $all_keys as $key ) {
					if ( ! in_array( $key, array( 'id', 'name', 'email', 'status', 'created_at' ) ) ) {
						$row[] = $data_by_submission[ $submission_id ][ $key ] ?? '-';
					}
				}
			}
			$csv_rows[] = $row;
		}

		return array( $csv_rows, $all_keys );
	}
}
