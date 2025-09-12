<?php

namespace App\AdvancedEntryManager\Api\Callback;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use App\AdvancedEntryManager\Utility\Helper;
use App\AdvancedEntryManager\Utility\FileSystem;

/**
 * Class Export_Entries
 *
 * Handles the asynchronous generation of CSV exports for form entries
 * using Action Scheduler for background processing.
 */
class Export_Entries {

	/**
	 * FileSystem instance for file operations.
	 *
	 * @var FileSystem The instance of the FileSystem class.
	 */
	protected $fs;

	/**
	 * Plugin Prefix
	 */
	const FEM_PREFIX = 'fem_';

	/**
	 * The hook used by Action Scheduler to process a single batch.
	 */
	const BATCH_PROCESSING_HOOK = self::FEM_PREFIX . 'process_export_batch';

	/**
	 * The hook used by Action Scheduler to finalize the export.
	 */
	const FINALIZE_HOOK = self::FEM_PREFIX . 'finalize_export_file';

	/**
	 * The group for all export-related actions in Action Scheduler.
	 */
	const SCHEDULE_GROUP = self::FEM_PREFIX . 'export_jobs';

	/**
	 * Prefix for the transient key used to store a job's state.
	 */
	const JOB_TRANSIENT_PREFIX = self::FEM_PREFIX . 'export_job_';

	/**
	 * Directory inside wp-content/uploads to store temporary and final CSV files.
	 */
	const TEMP_DIR = self::FEM_PREFIX . 'exports';

	const SYSTEM_FIELDS_TO_EXCLUDE = array(
		'form_id',           // This identifies the form, often not needed as a field in the export itself
		'status',            // 'read'/'unread' is internal state
		'is_favorite',       // Internal favorite flag
		'exported_to_csv',   // Internal tracking of export
		'synced_to_gsheet',  // Internal tracking of Google Sheet sync
		'printed_at',        // Internal tracking of print time
		'is_spam',           // Internal spam flag
		'resent_at',         // Internal tracking of resending
		'updated_at',        // Internal update timestamp
		'retry_count',       // Internal retry mechanism
	);

	public function __construct() {
		$this->fs = new FileSystem();
	}

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
			return new WP_Error( 'missing_scheduler', __( 'Action Scheduler is required but not available.', 'forms-entries-manager' ), array( 'status' => 500 ) );
		}

		$form_id = absint( $request->get_param( 'form_id' ) );
		if ( ! $form_id ) {
			return new WP_Error( 'missing_form_id', __( 'A valid Form ID is required.', 'forms-entries-manager' ), array( 'status' => 400 ) );
		}

		global $wpdb;
		$submissions_table = Helper::get_submission_table();
		$entries_table     = Helper::get_data_table();

		// Build query to count total entries based on filters
		$query_args    = array();
		$where_clauses = array( 'form_id = %d' );
		$query_args[]  = $form_id;

		$date_from = sanitize_text_field( $request->get_param( 'date_from' ) ?? '' );
		if ( $date_from ) {
			$where_clauses[] = 'created_at >= %s';
			$query_args[]    = $date_from;
		}

		$date_to = sanitize_text_field( $request->get_param( 'date_to' ) ?? '' );
		if ( $date_to ) {
			$where_clauses[] = 'created_at <= %s';
			$query_args[]    = $date_to;
		}

		$where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );

		// Step 1: Count entries from the submissions table
		$count_query   = $wpdb->prepare( "SELECT COUNT(*) FROM {$submissions_table} {$where_sql}", ...$query_args );
		$total_entries = (int) $wpdb->get_var( $count_query );

		if ( $total_entries === 0 ) {
			return new WP_Error(
				'no_entries',
				__( 'No entries found for the selected criteria.', 'forms-entries-manager' ),
				array( 'status' => 404 )
			);
		}

		// Step 2: If low volume, fetch directly
		if ( $total_entries <= 10000 ) {
			$select_query   = $wpdb->prepare(
				"SELECT * FROM {$submissions_table} {$where_sql} ORDER BY created_at ASC",
				...$query_args
			);
			$low_entries    = $wpdb->get_results( $select_query, ARRAY_A );
			$all_entry_data = $wpdb->get_results(
				$wpdb->prepare( "SELECT submission_id, field_key, field_value FROM {$entries_table} WHERE submission_id IN ($ids_placeholder)", ...array_column( $low_entries, 'id' ) ),
				ARRAY_A
			);

			$this->export_entries_otg( $low_entries, $all_entry_data );
		}

		// Generate a unique ID for this export job
		$job_id = 'export_' . $form_id . '_' . wp_generate_password( 12, false );

		$exclude_fields = $request->get_param( 'exclude_fields' );
		$exclude_fields = is_string( $exclude_fields ) ? explode( ',', $exclude_fields ) : (array) $exclude_fields;

		// Store the initial state of the job in a transient
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
			'header_built'    => false,
		);
		Helper::set_transient( self::JOB_TRANSIENT_PREFIX . $job_id, $job_state, DAY_IN_SECONDS );

		// Schedule the first batch
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

		if ( false === $job_state || $job_state['status'] === 'complete' ) {
			return;
		}

		$job_state['status'] = 'in-progress';
		Helper::set_transient( $transient_key, $job_state, DAY_IN_SECONDS );

		global $wpdb;
		$submissions_table = Helper::get_submission_table();
		$entries_table     = Helper::get_data_table();

		// Build query for submissions
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

		$where_clauses[] = 'id > %d';
		$query_args[]    = $job_state['last_id'];

		$where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );

		$submissions_query = $wpdb->prepare(
			"SELECT id, name, email, status, note, is_favorite, created_at FROM {$submissions_table} {$where_sql} ORDER BY id ASC LIMIT %d",
			array_merge( $query_args, array( $job_state['batch_size'] ) )
		);
		$submissions       = $wpdb->get_results( $submissions_query, ARRAY_A );

		if ( empty( $submissions ) ) {
			as_schedule_single_action(
				time() + 5,
				self::FINALIZE_HOOK,
				array( 'job_id' => $job_id ),
				self::SCHEDULE_GROUP
			);
			return;
		}

		$submission_ids  = array_column( $submissions, 'id' );
		$ids_placeholder = implode( ',', array_fill( 0, count( $submission_ids ), '%d' ) );

		$entries_raw = $wpdb->get_results(
			$wpdb->prepare( "SELECT submission_id, field_key, field_value FROM {$entries_table} WHERE submission_id IN ($ids_placeholder)", ...$submission_ids ),
			ARRAY_A
		);

		$merged_entries = $this->merge_entries_data( $submissions, $entries_raw );

		if ( ! $job_state['header_built'] ) {
			$header = $this->get_header_from_entries( $merged_entries );
			// Exclude fields if necessary
			$header                    = array_diff( $header, $job_state['filters']['exclude_fields'] );
			$job_state['header']       = $header;
			$job_state['header_built'] = true;
		}

		$this->write_batch_to_csv( $job_id, $job_state['page'], $merged_entries, $job_state['header'] );

		$job_state['processed_count'] += count( $submissions );
		$last_submission               = end( $submissions );
		$job_state['last_id']          = $last_submission['id'];
		++$job_state['page'];

		if ( $job_state['processed_count'] < $job_state['total_entries'] ) {
			Helper::set_transient( $transient_key, $job_state, DAY_IN_SECONDS );
			as_schedule_single_action(
				time() + 5,
				self::BATCH_PROCESSING_HOOK,
				array( 'job_id' => $job_id ),
				self::SCHEDULE_GROUP
			);
		} else {
			Helper::set_transient( $transient_key, $job_state, DAY_IN_SECONDS );
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

		$final_file_path = $upload_dir['path'] . '/' . $job_id . '.csv';
		// Use WP_Filesystem API for robust file operations
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();
		global $wp_filesystem;

		$final_content  = "\xEF\xBB\xBF"; // UTF-8 BOM
		$header_written = false;

		$batch_files = glob( $upload_dir['path'] . '/' . $job_id . '_batch_*.csv' );

		foreach ( $batch_files as $batch_file ) {
			$content = $wp_filesystem->get_contents( $batch_file );
			if ( $content ) {
				// If header is not written, add the first batch content entirely
				if ( ! $header_written ) {
					$final_content .= $content;
					$header_written = true;
				} else {
					// Skip the first line (header) of subsequent files
					$lines = explode( "\n", $content );
					array_shift( $lines );
					$final_content .= implode( "\n", $lines );
				}
			}
			$wp_filesystem->delete( $batch_file );
		}

		if ( ! $wp_filesystem->put_contents( $final_file_path, $final_content ) ) {
			// Log error
			return;
		}

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

		$file_path   = $upload_dir['path'] . '/' . $job_id . '_batch_' . $page . '.csv';
		$file_handle = fopen( $file_path, 'a' );

		if ( $file_handle === false ) {
			return;
		}

		if ( $page === 1 ) {
			fputcsv( $file_handle, $header );
		}

		foreach ( $entries as $entry ) {
			$row = array();
			foreach ( $header as $col ) {
				$row[] = $entry[ $col ] ?? '';
			}
			fputcsv( $file_handle, $row );
		}

		fclose( $file_handle );
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
		$job_id = sanitize_key( $request->get_param( 'job_id' ) );
		if ( empty( $job_id ) ) {
			return new WP_Error(
				'missing_job_id',
				__( 'Job ID is required.', 'forms-entries-manager' ),
				array( 'status' => 400 )
			);
		}

		$transient_key = self::JOB_TRANSIENT_PREFIX . $job_id;
		$job_state     = Helper::get_transient( $transient_key );

		if ( false === $job_state || $job_state['status'] !== 'complete' ) {
			return new WP_Error(
				'invalid_job',
				__( 'Export job not found or not yet complete.', 'forms-entries-manager' ),
				array( 'status' => 404 )
			);
		}

		$file_path = $job_state['file_path'];
		$fs        = new FileSystem();

		if ( empty( $file_path ) || ! $fs->exists( $file_path ) ) {
			return new WP_Error(
				'file_not_found',
				__( 'Export file not found on the server.', 'forms-entries-manager' ),
				array( 'status' => 404 )
			);
		}

		// Clean any buffers to avoid "ob_end_flush" fatal
		while ( ob_get_level() ) {
			ob_end_clean();
		}

		$file_name = basename( $file_path );

		// Headers for direct download
		header( 'Content-Description: File Transfer' );
		header( 'Content-Type: text/csv' );
		header( 'Content-Disposition: attachment; filename="' . $file_name . '"' );
		header( 'Content-Length: ' . filesize( $file_path ) );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// Stream the file instead of loading into memory
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		readfile( $file_path );

		exit; // Important: stop WP from continuing
	}


	/**
	 * REST API endpoint to securely delete a completed export file.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_export_file( WP_REST_Request $request ) {
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
			// File is already gone, which is fine
			Helper::delete_transient( $transient_key ); // Clean up the transient
			return new WP_REST_Response(
				array(
					'success' => true,
					'message' => __( 'File was already deleted.', 'forms-entries-manager' ),
				),
				200
			);
		}

		// Delete the file
		if ( wp_delete_file( $file_path ) ) {
			// Delete the transient as well
			Helper::delete_transient( $transient_key );
			return new WP_REST_Response(
				array(
					'success' => true,
					'message' => __( 'Export file deleted successfully.', 'forms-entries-manager' ),
				),
				200
			);
		} else {
			return new WP_Error( 'delete_failed', __( 'Failed to delete the export file.', 'forms-entries-manager' ), array( 'status' => 500 ) );
		}
	}

	public function export_entries_otg( $submissions, $entries_raw ) {
		if ( empty( $submissions ) ) {
			return new \WP_Error(
				'no_data',
				__( 'No data found.', 'forms-entries-manager' ),
				array( 'status' => 404 )
			);
		}

		// Merge the data from both tables
		$merged_entries = $this->merge_entries_data( $submissions, $entries_raw );

		// Get headers from all entries
		$all_keys = $this->get_header_from_entries( $merged_entries );

		// Build the CSV content in memory
		$csv_content  = "\xEF\xBB\xBF"; // UTF-8 BOM
		$csv_content .= Helper::get_csv_line( $all_keys );

		foreach ( $merged_entries as $entry ) {
			$row = array();
			foreach ( $all_keys as $key ) {
				$row[] = $entry[ $key ] ?? '';
			}
			$csv_content .= Helper::get_csv_line( $row );
		}

		// Set headers and echo
		header( 'Content-Type: text/csv' );
		header( 'Content-Disposition: attachment; filename="fem-entries-' . time() . '.csv"' );
		echo $csv_content;
		exit;
	}

	private function get_header_from_entries( array $entries ): array {
		$headers = array();
		foreach ( $entries as $entry ) {
			foreach ( $entry as $key => $value ) {
				// Check if the key is not in our exclusion list.
				if ( ! in_array( $key, $headers ) && ! in_array( $key, self::SYSTEM_FIELDS_TO_EXCLUDE ) ) {
					$headers[] = $key;
				}
			}
		}
		sort( $headers );
		return $headers;
	}

	/**
	 * Merges submissions data with their corresponding entry fields,
	 * de-duplicating core fields like 'name' and 'email' based on value.
	 *
	 * @param array $submissions An array of submissions from the submissions table.
	 * @param array $entries_raw An array of raw entry data.
	 * @return array The merged array of entries.
	 */
	private function merge_entries_data( array $submissions, array $entries_raw ): array {
		$merged      = array();
		$entries_map = array();

		// Map raw entries by submission ID
		foreach ( $entries_raw as $entry ) {
			$submission_id = $entry['submission_id'];
			if ( ! isset( $entries_map[ $submission_id ] ) ) {
				$entries_map[ $submission_id ] = array();
			}
			$entries_map[ $submission_id ][ $entry['field_key'] ] = $entry['field_value'];
		}

		foreach ( $submissions as $submission ) {
			$submission_id = $submission['id'];
			$entry_data    = $entries_map[ $submission_id ] ?? array();

			// Use the new static helper method to de-duplicate entry data
			$filtered_entry_data = Helper::filter_duplicate_entry_fields(
				$entry_data,
				$submission['name'] ?? null,
				$submission['email'] ?? null
			);

			// Merge the submission data with the now-filtered entry data.
			$merged[] = array_merge( $submission, $filtered_entry_data );
		}

		return $merged;
	}
}
