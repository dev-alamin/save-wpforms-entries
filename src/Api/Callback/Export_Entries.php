<?php

namespace App\AdvancedEntryManager\Api\Callback;

defined( 'ABSPATH' ) || exit;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use App\AdvancedEntryManager\Utility\Helper;

/**
 * Class Export_Entries
 *
 * Handles the asynchronous generation of CSV exports for form entries
 * using Action Scheduler for background processing.
 */
class Export_Entries {

	/**
	 * Plugin Prefix
	 */
	const AEM_PREFIX = 'fem';

	/**
	 * The hook used by Action Scheduler to process a single batch.
	 */
	const BATCH_PROCESSING_HOOK = self::AEM_PREFIX . 'process_export_batch';

	/**
	 * The hook used by Action Scheduler to finalize the export.
	 */
	const FINALIZE_HOOK = self::AEM_PREFIX . 'finalize_export_file';

	/**
	 * The group for all export-related actions in Action Scheduler.
	 */
	const SCHEDULE_GROUP = self::AEM_PREFIX . 'export_jobs';

	/**
	 * Prefix for the transient key used to store a job's state.
	 */
	const JOB_TRANSIENT_PREFIX = self::AEM_PREFIX . 'export_job_';

	/**
	 * Directory inside wp-content/uploads to store temporary and final CSV files.
	 */
	const TEMP_DIR = self::AEM_PREFIX . 'exports';

	/**
	 * Initiates a new CSV export background job.
	 *
	 * This is the REST API endpoint handler that validates the request,
	 * calculates the total entries, creates an initial job state, and
	 * schedules the first batch for processing.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error The response object.
	 */
	public function start_export_job( WP_REST_Request $request ) {
		if ( ! class_exists( 'ActionScheduler' ) ) {
			return new WP_Error(
				'missing_scheduler',
				__( 'Action Scheduler is required but not available.', 'forms-entries-manager' ),
				array( 'status' => 500 )
			);
		}

		$form_id = absint( $request->get_param( 'form_id' ) );
		if ( ! $form_id ) {
			return new WP_Error(
				'missing_form_id',
				__( 'A valid Form ID is required.', 'forms-entries-manager' ),
				array( 'status' => 400 )
			);
		}

		global $wpdb;
		$target_table = esc_sql( Helper::get_table_name() ); // sanitize table name

		// Prepare date filters
		$date_from = sanitize_text_field( $request->get_param( 'date_from' ) ?? '' );
		$date_to   = sanitize_text_field( $request->get_param( 'date_to' ) ?? '' );

		// ---------- Step 1: Count total entries with caching ----------
		$count_cache_key = 'export_total_entries_' . $form_id . '_' . md5( $date_from . '|' . $date_to );
		$total_entries   = wp_cache_get( $count_cache_key, 'aem_exports' );

		if ( false === $total_entries ) {
			$count_sql  = "SELECT COUNT(*) FROM {$target_table} WHERE form_id = %d";
			$query_args = array( $form_id );

			if ( $date_from ) {
				$count_sql   .= ' AND created_at >= %s';
				$query_args[] = $date_from;
			}
			if ( $date_to ) {
				$count_sql   .= ' AND created_at <= %s';
				$query_args[] = $date_to;
			}

			$count_query = $wpdb->prepare( $count_sql, ...$query_args );
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$total_entries = (int) $wpdb->get_var( $count_query );

			wp_cache_set( $count_cache_key, $total_entries, 'aem_exports', HOUR_IN_SECONDS );
		}

		if ( $total_entries === 0 ) {
			return new WP_Error(
				'no_entries',
				__( 'No entries found for the selected criteria.', 'forms-entries-manager' ),
				array( 'status' => 404 )
			);
		}

		// ---------- Step 2: Fetch low-volume entries directly ----------
		if ( $total_entries <= 10000 ) {
			$entries_cache_key = 'export_low_entries_' . $form_id . '_' . md5( $date_from . '|' . $date_to );
			$low_entries       = wp_cache_get( $entries_cache_key, 'aem_exports' );

			if ( false === $low_entries ) {
				$select_sql = "SELECT * FROM {$target_table} WHERE form_id = %d";
				$query_args = array( $form_id );

				if ( $date_from ) {
					$select_sql  .= ' AND created_at >= %s';
					$query_args[] = $date_from;
				}
				if ( $date_to ) {
					$select_sql  .= ' AND created_at <= %s';
					$query_args[] = $date_to;
				}

				$select_sql .= ' ORDER BY created_at ASC';

				$select_query = $wpdb->prepare( $select_sql, ...$query_args );
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$low_entries = $wpdb->get_results( $select_query, ARRAY_A );

				wp_cache_set( $entries_cache_key, $low_entries, 'aem_exports', HOUR_IN_SECONDS );
			}

			$this->export_entries_otg( $low_entries );
		}

		// ---------- Step 3: Schedule export job ----------
		$job_id = 'export_' . $form_id . '_' . wp_generate_password( 12, false );

		$exclude_fields = $request->get_param( 'exclude_fields' );
		$exclude_fields = is_string( $exclude_fields ) ? explode( ',', $exclude_fields ) : (array) $exclude_fields;

		$job_state = array(
			'job_id'          => $job_id,
			'status'          => 'queued',
			'form_id'         => $form_id,
			'total_entries'   => $total_entries,
			'processed_count' => 0,
			'last_id'         => 0,
			'batch_size'      => 5000,
			'page'            => 1,
			'filters'         => array(
				'date_from'      => $date_from,
				'date_to'        => $date_to,
				'exclude_fields' => $exclude_fields,
			),
			'started_at'      => time(),
			'file_path'       => null,
			'file_url'        => null,
			'header'          => array(),
		);

		Helper::set_transient( self::JOB_TRANSIENT_PREFIX . $job_id, $job_state, DAY_IN_SECONDS );

		as_schedule_single_action(
			time(),
			self::BATCH_PROCESSING_HOOK,
			array( 'job_id' => $job_id ),
			self::SCHEDULE_GROUP
		);

		return rest_ensure_response(
			array(
				'success'       => true,
				'message'       => __( 'CSV export has been successfully queued.', 'forms-entries-manager' ),
				'job_id'        => $job_id,
				'total_entries' => $total_entries,
			)
		);
	}

	/**
	 * Processes one batch of entries for a given export job.
	 *
	 * This method is executed by Action Scheduler. It fetches the job state,
	 * queries the database for the current batch of entries, writes them to a
	 * temporary CSV file, and then schedules the next batch or the finalization job.
	 *
	 * @param string $job_id The ID of the export job to process.
	 * @return void
	 */
	public function process_export_batch( string $job_id ): void {
		$transient_key = self::JOB_TRANSIENT_PREFIX . $job_id;
		$job_state     = Helper::get_transient( $transient_key );

		// If the job state doesn't exist or is already complete, exit.
		if ( false === $job_state || $job_state['status'] === 'complete' ) {
			return;
		}

		// Update status to 'in-progress'
		$job_state['status'] = 'in-progress';
		Helper::set_transient( $transient_key, $job_state, DAY_IN_SECONDS );

		global $wpdb;
		$target_table = Helper::get_table_name();

		// Build query to fetch the current batch
		$query_args    = array();
		$where_clauses = array( 'form_id = %d' );
		$query_args[]  = $job_state['form_id'];

		if ( ! empty( $job_state['filters']['date_from'] ) ) {
			$where_clauses[] = 'created_at >= %s';
			$query_args[]    = $job_state['filters']['date_from'];
		}
		if ( ! empty( $job_state['filters']['date_to'] ) ) {
			$where_clauses[] = 'created_at <= %s';
			$query_args[]    = $job_state['filters']['date_to'];
		}

		// Use cursor-based pagination to avoid performance issues with OFFSET.
		// We fetch entries with an ID greater than the last processed ID.
		$where_clauses[] = 'id > %d';
		$query_args[]    = $job_state['last_id'];

		$where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );

		$entries_query = $wpdb->prepare(
			"SELECT * FROM {$target_table} {$where_sql} ORDER BY id ASC LIMIT %d",
			array_merge( $query_args, array( $job_state['batch_size'] ) )
		);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$entries = $wpdb->get_results( $entries_query, ARRAY_A );

		if ( empty( $entries ) ) {
			// No more entries found, so we are done. Schedule finalization.
			as_schedule_single_action(
				time() + 5,
				self::FINALIZE_HOOK,
				array( 'job_id' => $job_id ),
				self::SCHEDULE_GROUP
			);
			return;
		}

		// The header is now built and stored only once.
		if ( $job_state['page'] === 1 ) {
			$header           = array();
			$first_entry_data = ! empty( $entries[0]['entry'] ) ? maybe_unserialize( $entries[0]['entry'] ) : array();
			if ( is_array( $first_entry_data ) ) {
				$header = $this->flatten_array_keys( $first_entry_data );
			}

			$default_columns = array( 'id', 'form_id', 'email', 'note', 'created_at', 'status', 'is_favorite' );
			$header          = array_merge( $default_columns, $header );
			$header          = array_diff( $header, $job_state['filters']['exclude_fields'] );

			// Sort the header for consistent output
			// sort($header);

			// Store the final header in the job state for all future batches.
			$job_state['header'] = $header;
		}

		// Now, always get the header from the job state.
		$header = $job_state['header'];

		// Process and write the batch to the CSV file.
		$this->write_batch_to_csv( $job_id, $job_state['page'], $entries, $header );

		$job_state['processed_count'] += count( $entries );

		// Update the last_id to the ID of the last entry in the current batch for the next query.
		$last_entry           = end( $entries );
		$job_state['last_id'] = $last_entry['id'];

		++$job_state['page'];

		// Check if there are more entries left to process.
		if ( $job_state['processed_count'] < $job_state['total_entries'] ) {
			// Schedule the next batch.
			Helper::set_transient( $transient_key, $job_state, DAY_IN_SECONDS );

			as_schedule_single_action(
				time() + 5,
				self::BATCH_PROCESSING_HOOK,
				array( 'job_id' => $job_id ),
				self::SCHEDULE_GROUP
			);
		} else {
			// All entries have been processed. Save the final state.
			Helper::set_transient( $transient_key, $job_state, DAY_IN_SECONDS );

			// Now schedule the finalization job.
			as_schedule_single_action(
				time() + 5,
				self::FINALIZE_HOOK,
				array( 'job_id' => $job_id ),
				self::SCHEDULE_GROUP
			);
		}
	}

	/**
	 * Merges all temporary batch CSVs into a final file.
	 *
	 * This method is executed by Action Scheduler after the last data batch
	 * has been processed. It combines all partial files, deletes them, and
	 * updates the job state to 'complete' with the final file path and URL.
	 *
	 * @param string $job_id The ID of the export job to finalize.
	 * @return void
	 */
	public function finalize_export_file( string $job_id ): void {
		$upload_dir = $this->get_temp_dir();
		if ( is_wp_error( $upload_dir ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		global $wp_filesystem;
		WP_Filesystem();

		$final_file_path = $upload_dir['path'] . '/' . $job_id . '.csv';
		$final_handle    = $wp_filesystem->fopen( $final_file_path, 'w' );

		$batch_files    = glob( $upload_dir['path'] . '/' . $job_id . '_batch_*.csv' );
		$header_written = false;

		foreach ( $batch_files as $batch_file ) {
			$batch_handle = $wp_filesystem->fopen( $batch_file, 'r' );
			if ( ! $batch_handle ) {
				continue;
			}

			if ( $header_written ) {
				fgetcsv( $batch_handle ); // skip header
			}

			while ( ( $data = fgetcsv( $batch_handle ) ) !== false ) {
				Helper::fputcsv( $final_handle, $data );
			}

			$wp_filesystem->fclose( $batch_handle );
			$wp_filesystem->delete( $batch_file ); // safer than unlink
			$header_written = true;
		}

		$wp_filesystem->fclose( $final_handle );

		// Update job state
		$transient_key = self::JOB_TRANSIENT_PREFIX . $job_id;
		$job_state     = Helper::get_transient( $transient_key );
		if ( $job_state ) {
			$job_state['status']    = 'complete';
			$job_state['file_path'] = $final_file_path;
			$job_state['file_url']  = $upload_dir['url'] . '/' . $job_id . '.csv';
			Helper::set_transient( $transient_key, $job_state, DAY_IN_SECONDS );
		}
	}

	/**
	 * Retrieves the progress of an ongoing export job.
	 *
	 * @param WP_REST_Request $request The REST request, must contain 'job_id'.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_export_progress( WP_REST_Request $request ) {
		$job_id = sanitize_key( $request->get_param( 'job_id' ) );
		if ( ! $job_id ) {
			return new WP_Error( 'missing_job_id', __( 'Job ID is required.', 'forms-entries-manager' ), array( 'status' => 400 ) );
		}

		$job_state = Helper::get_transient( self::JOB_TRANSIENT_PREFIX . $job_id );
		if ( false === $job_state ) {
			return new WP_Error( 'invalid_job', __( 'Export job not found or has expired.', 'forms-entries-manager' ), array( 'status' => 404 ) );
		}

		$progress = 0;
		if ( $job_state['total_entries'] > 0 ) {
			$progress = round( ( $job_state['processed_count'] / $job_state['total_entries'] ) * 100, 2 );
		}

		if ( $job_state['status'] === 'complete' ) {
			$progress = 100;
		}

		return rest_ensure_response(
			array(
				'job_id'    => $job_id,
				'status'    => $job_state['status'],
				'progress'  => $progress,
				'total'     => $job_state['total_entries'],
				'processed' => $job_state['processed_count'],
				'file_url'  => $job_state['file_url'], // Will be null until the job is complete
			)
		);
	}

	/**
	 * Writes a set of entries to a temporary CSV file for a specific batch.
	 *
	 * This optimized version only writes the header for the first batch and
	 * streamlines the data processing for each entry.
	 *
	 * @param string $job_id The main job ID.
	 * @param int    $page The current batch number.
	 * @param array  $entries The entry data to write.
	 * @return void
	 */
	private function write_batch_to_csv( string $job_id, int $page, array $entries, array $header ): void {
		$upload_dir = $this->get_temp_dir();
		if ( is_wp_error( $upload_dir ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		global $wp_filesystem;
		WP_Filesystem();

		$file_path = $upload_dir['path'] . '/' . $job_id . '_batch_' . $page . '.csv';

		// Open file for writing
		$handle = $wp_filesystem->fopen( $file_path, 'w' );
		if ( ! $handle ) {
			return;
		}

		// Write header only for first batch
		if ( $page === 1 ) {
			Helper::fputcsv( $handle, $header ); // Use your Helper::fputcsv method
		}

		foreach ( $entries as $entry ) {
			$entry_data      = ! empty( $entry['entry'] ) ? maybe_unserialize( $entry['entry'] ) : array();
			$flat_entry_data = $this->flatten_entry_data( $entry_data );

			$row = array_merge(
				array(
					'id'          => $entry['id'],
					'form_id'     => $entry['form_id'],
					'email'       => $entry['email'],
					'note'        => $entry['note'],
					'created_at'  => $entry['created_at'],
					'status'      => $entry['status'],
					'is_favorite' => $entry['is_favorite'],
				),
				$flat_entry_data
			);

			$final_row = array();
			foreach ( $header as $col ) {
				$final_row[] = $row[ $col ] ?? '';
			}

			Helper::fputcsv( $handle, $final_row );
		}

		$wp_filesystem->fclose( $handle );
	}


	/**
	 * A helper function to flatten nested array keys for a consistent header.
	 *
	 * @param array $data
	 * @return array
	 */
	private function flatten_array_keys( array $data ): array {
		$keys = array();
		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) {
				foreach ( $value as $sub_key => $sub_value ) {
					$keys[] = $key . '_' . $sub_key;
				}
			} else {
				$keys[] = $key;
			}
		}
		return $keys;
	}

	/**
	 * A helper function to flatten entry data.
	 *
	 * @param array $entry_data
	 * @return array
	 */
	private function flatten_entry_data( array $entry_data ): array {
		$flat_data = array();
		foreach ( $entry_data as $key => $value ) {
			if ( is_array( $value ) ) {
				foreach ( $value as $sub_key => $sub_value ) {
					$flat_data[ $key . '_' . $sub_key ] = $sub_value;
				}
			} else {
				$flat_data[ $key ] = $value;
			}
		}
		return $flat_data;
	}

	/**
	 * Gets the temporary directory for storing export files, creating it if it doesn't exist.
	 *
	 * @return array|WP_Error An array with 'path' and 'url' on success, or WP_Error on failure.
	 */
	private function get_temp_dir() {
		$upload_dir = wp_get_upload_dir();
		$temp_path  = $upload_dir['basedir'] . '/' . self::TEMP_DIR;
		$temp_url   = $upload_dir['baseurl'] . '/' . self::TEMP_DIR;

		if ( ! is_dir( $temp_path ) ) {
			if ( ! wp_mkdir_p( $temp_path ) ) {
				return new WP_Error( 'dir_creation_failed', __( 'Could not create temporary export directory.', 'forms-entries-manager' ) );
			}
		}

		// Add security files to prevent browsing
		if ( ! file_exists( $temp_path . '/.htaccess' ) ) {
			file_put_contents( $temp_path . '/.htaccess', 'deny from all' );
		}
		if ( ! file_exists( $temp_path . '/index.php' ) ) {
			file_put_contents( $temp_path . '/index.php', '<?php // Silence is golden.' );
		}

		return array(
			'path' => $temp_path,
			'url'  => $temp_url,
		);
	}

	/**
	 * REST API endpoint to securely serve a completed export file.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function download_export_file( WP_REST_Request $request ) {
		// Sanitize the job ID from the request
		$job_id = sanitize_key( $request->get_param( 'job_id' ) );
		if ( empty( $job_id ) ) {
			return new WP_Error( 'missing_job_id', __( 'Job ID is required.', 'forms-entries-manager' ), array( 'status' => 400 ) );
		}

		// Get the job state from the transient
		$transient_key = self::JOB_TRANSIENT_PREFIX . $job_id;
		$job_state     = Helper::get_transient( $transient_key );

		// Check if the job exists and is complete
		if ( false === $job_state || $job_state['status'] !== 'complete' ) {
			return new WP_Error( 'invalid_job', __( 'Export job not found or not yet complete.', 'forms-entries-manager' ), array( 'status' => 404 ) );
		}

		// Check if a file path is set
		$file_path = $job_state['file_path'];
		if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
			return new WP_Error( 'file_not_found', __( 'Export file not found on the server.', 'forms-entries-manager' ), array( 'status' => 404 ) );
		}

		// --- All checks passed. Now serve the file ---

		// Get the file name from the path
		$file_name = basename( $file_path );

		// Set headers for file download
		header( 'Content-Type: text/csv' );
		header( 'Content-Disposition: attachment; filename="' . $file_name . '"' );
		header( 'Content-Length: ' . filesize( $file_path ) );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// Read the file and send it to the browser
		readfile( $file_path );

		exit;
	}

	/**
	 * REST API endpoint to securely delete a completed export file.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_export_file( WP_REST_Request $request ) {
		$job_id = sanitize_key( $request->get_param( 'job_id' ) );
		if ( empty( $job_id ) ) {
			return new WP_Error( 'missing_job_id', __( 'Job ID is required.', 'forms-entries-manager' ), array( 'status' => 400 ) );
		}

		$transient_key = self::JOB_TRANSIENT_PREFIX . $job_id;
		$job_state     = Helper::get_transient( $transient_key );

		if ( false === $job_state || $job_state['status'] !== 'complete' ) {
			return new WP_Error( 'invalid_job', __( 'Export job not found or not yet complete.', 'forms-entries-manager' ), array( 'status' => 404 ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		global $wp_filesystem;
		WP_Filesystem();

		$file_path = $job_state['file_path'];

		if ( empty( $file_path ) || ! $wp_filesystem->exists( $file_path ) ) {
			Helper::delete_transient( $transient_key );
			return rest_ensure_response(
				array(
					'success' => true,
					'message' => __( 'File was already deleted.', 'forms-entries-manager' ),
				)
			);
		}

		if ( $wp_filesystem->delete( $file_path ) ) {
			Helper::delete_transient( $transient_key );
			return rest_ensure_response(
				array(
					'success' => true,
					'message' => __( 'Export file deleted successfully.', 'forms-entries-manager' ),
				)
			);
		}

		return new WP_Error( 'delete_failed', __( 'Failed to delete the export file.', 'forms-entries-manager' ), array( 'status' => 500 ) );
	}

	public function export_entries_otg( $entries ) {
		if ( empty( $entries ) ) {
			return new \WP_Error(
				'no_data',
				__( 'No data found.', 'forms-entries-manager' ),
				array( 'status' => 404 )
			);
		}

		// Set headers for CSV file download
		header( 'Content-Type: text/csv' );
		header( 'Content-Disposition: attachment; filename="aem-entries-' . time() . '.csv"' );

		$output = fopen( 'php://output', 'w' );

		$all_keys  = array();
		$field_map = array(); // normalized_key => original_label

		// Step 1: Collect all normalized keys from all entries
		foreach ( $entries as $entry ) {
			$data = maybe_unserialize( $entry['entry'] );
			if ( is_array( $data ) ) {
				foreach ( array_keys( $data ) as $label ) {
					$normalized               = $this->normalize_label( $label );
					$field_map[ $normalized ] = $label; // Keep original label
					$all_keys[]               = $normalized;
				}
			}
		}

		// Step 2: Prepare final ordered unique keys
		$all_keys = array_unique( $all_keys );
		sort( $all_keys );
		array_unshift( $all_keys, 'id' ); // Always show ID first

		// Step 3: Write CSV headers using original labels
		$csv_headers = array_map(
			function ( $key ) use ( $field_map ) {
				return $key === 'id' ? 'id' : $field_map[ $key ] ?? $key;
			},
			$all_keys
		);

		fputcsv( $output, $csv_headers );

		// Step 4: Write CSV rows
		foreach ( $entries as $entry ) {
			$data = maybe_unserialize( $entry['entry'] );
			$row  = array();

			foreach ( $all_keys as $key ) {
				if ( $key === 'id' ) {
					$row[] = $entry['id'];
					continue;
				}

				$original_label = $field_map[ $key ] ?? $key;
				$value          = $data[ $original_label ] ?? '';

				// Convert arrays to comma-separated values
				if ( is_array( $value ) ) {
					$value = implode( ', ', $value );
				}

				// Remove newlines for safe CSV rows
				$value = preg_replace( '/\r\n|\r|\n/', ' ', $value );

				$row[] = $value;
			}

			fputcsv( $output, $row );
		}

		fclose( $output );
		exit;
	}

	private function normalize_label( $label ) {
		$label = strtolower( $label );
		$label = preg_replace( '/[^a-z0-9]+/', '_', $label );
		return trim( $label, '_' );
	}
}
