<?php

namespace App\AdvancedEntryManager\GoogleSheet;

defined( 'ABSPATH' ) || exit;

use App\AdvancedEntryManager\Utility\Helper;
use App\AdvancedEntryManager\Logger\FileLogger;
use WP_Error;

class Send_Data {


	protected $logger;

	public function __construct() {
		$this->logger = new FileLogger();
		// Capture token on init
		add_action( 'admin_init', array( $this, 'capture_token' ) );

		// Add a new hook to handle the revocation action
		add_action( 'admin_init', array( $this, 'handle_google_revocation_action' ) );
	}

	/**
	 * Handles the revocation action when the user clicks the "Revoke Connection" button.
	 */
	public function handle_google_revocation_action() {
		// Check if the action parameter is set
		if ( ! isset( $_GET['action'] ) || $_GET['action'] !== 'revoke_google_connection' ) {
			return;
		}

		// Verify the nonce for security
		if ( check_admin_referer( 'revoke_connection_nonce' ) === false ) {
			printf(
				'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
				esc_html__( 'Security check failed. Please try again.', 'forms-entries-manager' )
			);
		}

		// Ensure the current user has the right permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'You do not have sufficient permissions to perform this action.' );
		}

		$success = Helper::revokeConnection();

		// Set a redirect URL with a status message
		$redirect_url = admin_url( 'admin.php?page=form-entries-settings' );
		if ( $success ) {
			$redirect_url = add_query_arg( 'revoked', 'success', $redirect_url );
		} else {
			$redirect_url = add_query_arg( 'revoked', 'failed', $redirect_url );
		}

		// Redirect the user
		wp_safe_redirect( $redirect_url );
		exit;
	}

	public function capture_token() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['oauth_proxy_code'] ) ) {
			return;
		}

		$auth_code = sanitize_text_field( wp_unslash( $_GET['oauth_proxy_code'] ) ); //phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// Exchange the one-time auth code for real tokens
		$response = wp_remote_post(
			FEM_PROXY_BASE_URL . 'wp-json/swpfe/v1/token',
			array(
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => json_encode(
					array(
						'auth_code' => $auth_code,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->logger->log( 'Token exchange failed: ' . $response->get_error_message(), 'ERROR' );
			return;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! empty( $body['access_token'] ) ) {
			Helper::update_option( 'google_access_token', sanitize_text_field( $body['access_token'] ) );
			Helper::update_option( 'google_token_expires', time() + intval( $body['expires_in'] ?? 3600 ) );

			Helper::update_option( 'user_remvoked_google_connection', false );
			// Optional: Store refresh token too if ever needed on client (rare)
			wp_safe_redirect( admin_url( 'admin.php?page=form-entries-settings&connected=true' ) );
			exit;
		}

		$this->logger->log( 'Token exchange failed: Invalid response', 'ERROR' );
	}

	/**
	 * Get or create the necessary spreadsheet and sheet for a given form.
	 * This is the master function for ensuring a sync target exists and is ready.
	 *
	 * @param int $form_id The ID of the WPForm.
	 * @return array|WP_Error An array containing ['spreadsheet_id', 'sheet_title'] or a WP_Error on failure.
	 */
	protected function get_or_create_sheet_for_form( int $form_id ) {
		// Enforce Free Version form limitation
		if ( ! Helper::is_pro_version() ) {
			$linked_forms = Helper::get_option( 'aem_linked_forms', array() );
			if ( ! in_array( $form_id, $linked_forms ) && count( $linked_forms ) >= 1 ) {
				return new WP_Error( 'limit_exceeded', 'The free version supports synchronizing data from only one form. Please upgrade to Pro to sync more forms.' );
			}
		}

		$spreadsheet_id = Helper::get_option( "gsheet_spreadsheet_id_{$form_id}" );
		$sheet_title    = Helper::get_option( "gsheet_sheet_title_{$form_id}" );
		$headers_set    = Helper::get_option( "gsheet_headers_set_{$form_id}" );

		// If we have a spreadsheet and the headers have already been set and formatted,
		// we can return immediately. This is the main performance optimization.
		if ( $spreadsheet_id && $sheet_title && $headers_set ) {
			return array(
				'spreadsheet_id' => $spreadsheet_id,
				'sheet_title'    => $sheet_title,
			);
		}

		// Prevent multiple simultaneous creation attempts for the same form.
		$lock_key = 'aem_gsheet_creating_lock_' . $form_id;
		if ( get_transient( $lock_key ) ) {
			return new WP_Error( 'locked', 'Sheet creation for this form is already in progress.' );
		}
		set_transient( $lock_key, true, 60 ); // Lock for 60 seconds

		// --- Create Spreadsheet if it doesn't exist ---
		if ( ! $spreadsheet_id ) {
			$form_data         = wpforms()->form->get( $form_id, array( 'content_only' => true ) );
			$form_title        = $form_data['settings']['form_title'] ?? "Form #{$form_id}";
			$spreadsheet_title = ( get_bloginfo( 'name' ) ?: 'WPForms Sync' ) . " - {$form_title}";

			$spreadsheet_id = $this->gsheet_create_spreadsheet( $spreadsheet_title );
			if ( is_wp_error( $spreadsheet_id ) ) {
				delete_transient( $lock_key );
				return $spreadsheet_id;
			}
			Helper::update_option( "gsheet_spreadsheet_id_{$form_id}", $spreadsheet_id );
			Helper::update_option( "gsheet_spreadsheet_title_{$form_id}", $spreadsheet_title ); // Save for UI

			// Track linked forms for the free version
			$linked_forms = Helper::get_option( 'aem_linked_forms', array() );
			if ( ! in_array( $form_id, $linked_forms ) ) {
				$linked_forms[] = $form_id;
				Helper::update_option( 'aem_linked_forms', $linked_forms );
			}
		}

		// --- Configure the Sheet (Tab) ---
		$metadata = $this->get_spreadsheet_metadata( $spreadsheet_id );
		if ( is_wp_error( $metadata ) ) {
			delete_transient( $lock_key );
			return $metadata;
		}

		$sheet_properties = $metadata['sheets'][0]['properties']; // Use the first default sheet
		$sheet_id         = $sheet_properties['sheetId'];
		$sheet_title      = $sheet_properties['title']; // This is the initial title, e.g., "Sheet1"

		// Get canonical headers from form data
		$headers = $this->get_form_headers( $form_id );

		// Prepare batch requests for efficiency
		$requests = array(
			// 1. Freeze the header row
			array(
				'updateSheetProperties' => array(
					'properties' => array(
						'sheetId'        => $sheet_id,
						'gridProperties' => array( 'frozenRowCount' => 1 ),
					),
					'fields'     => 'gridProperties.frozenRowCount',
				),
			),
			// 2. Write the headers with bold formatting
			array(
				'updateCells' => array(
					'rows'   => array( array( 'values' => $this->format_cells( $headers, true ) ) ), // <-- PASS `true` HERE
					'fields' => 'userEnteredValue,userEnteredFormat.textFormat.bold',
					'start'  => array(
						'sheetId'     => $sheet_id,
						'rowIndex'    => 0,
						'columnIndex' => 0,
					),
				),
			),
		);

		$batch_result = $this->gsheet_batch_update( $spreadsheet_id, $requests );
		if ( is_wp_error( $batch_result ) ) {
			delete_transient( $lock_key );
			return $batch_result;
		}

		// Save the sheet info for future use
		Helper::update_option( "gsheet_sheet_id_{$form_id}", $sheet_id );
		Helper::update_option( "gsheet_sheet_title_{$form_id}", $sheet_title ); // Use the actual sheet title
		Helper::update_option( "gsheet_headers_{$form_id}", $headers ); // Cache headers
		Helper::update_option( "gsheet_headers_set_{$form_id}", true ); // Set the flag to true after successful setup

		delete_transient( $lock_key );

		return array(
			'spreadsheet_id' => $spreadsheet_id,
			'sheet_title'    => $sheet_title,
		);
	}

	protected function format_cells( array $values, bool $bold = false ): array {
		$formatted_cells = array();
		foreach ( $values as $value ) {
			$cell = array( 'userEnteredValue' => array( 'stringValue' => (string) $value ) );
			if ( $bold ) { // <-- Conditional formatting based on the new parameter
				$cell['userEnteredFormat'] = array( 'textFormat' => array( 'bold' => true ) );
			}
			$formatted_cells[] = $cell;
		}
		return $formatted_cells;
	}

	/**
	 * Process a single queued entry to send to Google Sheets.
	 */
	public function process_single_entry( $args ) {
		global $wpdb;

		$entry_id = absint( $args['entry_id'] );
		$table    = Helper::get_table_name(); // Safe table

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$entry = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $entry_id ) );

		if ( ! $entry ) {
			$this->logger->log( 'No entry data found for ID: ' . $entry_id, 'ERROR' );
			return false;
		}

		if ( $entry->synced_to_gsheet ) {
			$this->logger->log( 'Entry ID ' . $entry_id . ' already synced to Google Sheets.', 'INFO' );
			return false;
		}

		$form_id = absint( $entry->form_id );

		// Step 1: Ensure the target sheet is ready.
		$sheet_info = $this->get_or_create_sheet_for_form( $form_id );
		if ( is_wp_error( $sheet_info ) ) {
			$this->logger->log( 'GSheet preparation failed for form ' . $form_id . ': ' . $sheet_info->get_error_message(), 'ERROR' );
			$this->handle_sync_failure( $entry_id, $entry->retry_count );
			return false;
		}

		$spreadsheet_id = $sheet_info['spreadsheet_id'];
		$sheet_title    = $sheet_info['sheet_title'];

		// Enforce Free Version row limitation
		if ( ! Helper::is_pro_version() ) {
			$metadata = $this->get_spreadsheet_metadata( $spreadsheet_id );
			if ( ! is_wp_error( $metadata ) && isset( $metadata['sheets'][0]['properties']['gridProperties']['rowCount'] ) ) {
				$row_count = $metadata['sheets'][0]['properties']['gridProperties']['rowCount'];
				if ( $row_count >= 1000 ) {
					$this->logger->log( 'GSheet row limit reached for form ' . $form_id . '. Entry ' . $entry_id . ' not synced.', 'ERROR' );
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$wpdb->update( $table, array( 'synced_to_gsheet' => 2 ), array( 'id' => $entry_id ) ); // '2' can indicate 'sync_limit_reached'
					return false;
				}
			}
		}

		// Step 2: Prepare the data row, ensuring it matches the header order.
		$row_data = $this->prepare_row_data( $entry );
		if ( is_wp_error( $row_data ) ) {
			$this->logger->log( 'GSheet data preparation failed for entry ' . $entry_id . ': ' . $row_data->get_error_message(), 'ERROR' );
			$this->handle_sync_failure( $entry_id, $entry->retry_count );
			return false;
		}

		// Step 3: Append data to the sheet.
		$range = rawurlencode( $sheet_title ) . '!A:Z';
		$body  = array( 'values' => array( $row_data ) );
		$url   = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheet_id}/values/{$range}:append?valueInputOption=USER_ENTERED&insertDataOption=INSERT_ROWS";

		$response = $this->_make_google_api_request( $url, $body, 'POST' );

		if ( is_wp_error( $response ) ) {
			$this->logger->log( 'GSheet append failed for entry ' . $entry_id . ': ' . $response->get_error_message(), 'ERROR' );
			$this->handle_sync_failure( $entry_id, $entry->retry_count );
			return false;
		}

		// If we reach here, the entry was successfully synced.
		$this->logger->log( 'Entry ID ' . $entry_id . ' successfully synced to Google Sheets.', 'INFO' );

		// Step 4: Mark as synced on success.
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$table,
			array(
				'synced_to_gsheet' => 1,
				'retry_count'      => 0,
			),
			array( 'id' => $entry_id )
		);

		return true;
	}

	/**
	 * Retrieves the canonical headers for a form by inspecting a sample entry.
	 *
	 * This method ensures the header order is consistent with the data structure.
	 *
	 * @param int $form_id The ID of the form.
	 * @return array An array of headers.
	 */
	protected function get_form_headers( int $form_id ): array {
		$cached_headers = Helper::get_option( "gsheet_headers_{$form_id}" );
		if ( $cached_headers && is_array( $cached_headers ) ) {
			return $cached_headers;
		}

		global $wpdb;
		$table = Helper::get_table_name(); // Safe table

		// Fetch a sample entry to infer headers
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT entry, note, status FROM {$table} WHERE form_id = %d LIMIT 1", $form_id ),
			ARRAY_A
		);

		// If sample entry is unavailable somehow, use WPFORMS Default
		// $default = wpforms()->form->get($form_id);

		$headers = array(
			__( 'Entry ID', 'forms-entries-manager' ),
			__( 'Submission Date', 'forms-entries-manager' ),
			__( 'Name', 'forms-entries-manager' ),
			__( 'Email', 'forms-entries-manager' ),
		);

		$entry_data = array();
		if ( ! empty( $row['entry'] ) ) {
			$entry_data = maybe_unserialize( $row['entry'] );
		}

		if ( is_array( $entry_data ) ) {
			foreach ( $entry_data as $field_label => $field_value ) {
				$headers[] = $field_label;
			}
		}

		$headers[] = __( 'Status', 'forms-entries-manager' );
		$headers[] = __( 'Note', 'forms-entries-manager' );

		Helper::update_option( "gsheet_headers_{$form_id}", $headers );

		return $headers;
	}

	/**
	 * Prepares a single row of data, ensuring the order and values
	 * match the canonical headers for a form.
	 *
	 * @param object $entry The entry object from the database.
	 * @return array The formatted row data.
	 */
	protected function prepare_row_data( $entry ) {
		$form_id = absint( $entry->form_id );
		$headers = $this->get_form_headers( $form_id );

		if ( is_wp_error( $headers ) ) {
			return $headers;
		}

		$row        = array();
		$entry_data = maybe_unserialize( $entry->entry );
		$entry_data = is_array( $entry_data ) ? $entry_data : array();

		foreach ( $headers as $header_title ) {
			$value = '';

			switch ( $header_title ) {
				case __( 'Entry ID', 'forms-entries-manager' ):
					$value = $entry->id;
					break;
				case __( 'Submission Date', 'forms-entries-manager' ):
					$value = get_date_from_gmt( $entry->created_at, 'Y-m-d H:i:s' );
					break;
				case __( 'Name', 'forms-entries-manager' ):
					$value = $entry->name;
					break;
				case __( 'Email', 'forms-entries-manager' ):
					$value = $entry->email;
					break;
				case __( 'Status', 'forms-entries-manager' ):
					$value = $entry->status ?? '';
					break;
				case __( 'Note', 'forms-entries-manager' ):
					$value = $entry->note ?? '';
					break;
				default:
					if ( isset( $entry_data[ $header_title ] ) ) {
						$value = $entry_data[ $header_title ];
					}
					break;
			}

			if ( is_string( $value ) && preg_match( '/^[=+]/', trim( $value ) ) ) {
				$value = "'" . $value;
			}

			$row[] = (string) $value;
		}

		return $row;
	}

	/**
	 * Handles the logic for a failed sync attempt (retry or mark as failed).
	 */
	protected function handle_sync_failure( int $entry_id, int $current_retry_count ) {
		global $wpdb;
		$table = Helper::get_table_name();

		if ( $current_retry_count < 5 ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update( $table, array( 'retry_count' => $current_retry_count + 1 ), array( 'id' => $entry_id ) );
			// Schedule retry with exponential backoff
			$delay = 60 * pow( 2, $current_retry_count ); // 1 min, 2 min, 4 min, etc.
			as_schedule_single_action( time() + $delay, 'femprocess_gsheet_entry', array( 'entry_id' => $entry_id ) );
		} else {
			$this->logger->log( 'Max retry limit reached for entry ID ' . $entry_id . '. Sync abandoned.', 'ERROR' );
			// Optionally, mark as failed in the DB
			// $wpdb->update($table, ['status' => 'failed_sync'], ['id' => $entry_id]);
		}
	}

	/**
	 * Performs a batch update request to the Google Sheets API.
	 */
	public function gsheet_batch_update( $spreadsheet_id, $requests ) {
		$body = array( 'requests' => $requests );
		$url  = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheet_id}:batchUpdate";
		return $this->_make_google_api_request( $url, $body, 'POST' );
	}

	public function gsheet_create_spreadsheet( $title = 'WPForms Entries' ) {
		$body     = array(
			'properties' => array(
				'title' => $title,
			),
		);
		$url      = 'https://sheets.googleapis.com/v4/spreadsheets';
		$response = $this->_make_google_api_request( $url, $body, 'POST' );
		return $response['spreadsheetId'] ?? new WP_Error( 'create_failed', 'Spreadsheet creation failed.' );
	}

	/**
	 * Fetch spreadsheet metadata (to get sheet info like sheetId)
	 */
	protected function get_spreadsheet_metadata( string $spreadsheet_id ) {
		$url      = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheet_id}";
		$response = $this->_make_google_api_request( $url, array(), 'GET' );
		return $response;
	}

	/**
	 * Enqueue unsynced entries for Google Sheets sync in batches.
	 *
	 * @param int|null $form_id Optional. Limit enqueueing to this form ID.
	 * @param int      $batch_size Number of entries to enqueue per batch. Default 50.
	 * @param int      $delay_between Delay in seconds between scheduled jobs. Default 5.
	 *
	 * @return int Number of entries enqueued in this batch.
	 */
	public function enqueue_unsynced_entries( $form_id = null, $batch_size = 50, $delay_between = 5 ) {
		global $wpdb;

		$table = Helper::get_table_name();

		$where  = 'synced_to_gsheet = 0';
		$params = array();

		if ( $form_id ) {
			$where   .= ' AND form_id = %d';
			$params[] = $form_id;
		}

		$query    = "SELECT id FROM $table WHERE $where ORDER BY id ASC LIMIT %d";
		$params[] = $batch_size;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$entries = $wpdb->get_results( $wpdb->prepare( $query, ...$params ) );

		if ( empty( $entries ) ) {
			return 0;
		}

		$now = time();

		foreach ( $entries as $index => $entry ) {
			$scheduled_time = $now + ( $index * $delay_between );

			// Check if already scheduled to avoid duplicates
			if ( ! as_next_scheduled_action( 'femprocess_gsheet_entry', array( 'entry_id' => $entry->id ) ) ) {
				as_schedule_single_action( $scheduled_time, 'femprocess_gsheet_entry', array( 'entry_id' => $entry->id ) );
			}
		}

		return count( $entries );
	}

	private function _make_google_api_request( string $url, array $body = array(), string $method = 'POST' ) {
		$access_token = Helper::get_access_token();
		if ( ! $access_token ) {
			return new WP_Error( 'no_token', 'Missing or invalid access token.' );
		}

		$args = array(
			'method'  => $method,
			'headers' => array(
				'Authorization' => 'Bearer ' . $access_token,
				'Content-Type'  => 'application/json',
			),
			'timeout' => 20,
		);

		if ( ! empty( $body ) ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			$error_body = wp_remote_retrieve_body( $response );
			$this->logger->log( 'Google API request failed with code ' . $code . '. Response: ' . $error_body, 'ERROR' );

			return new WP_Error( 'api_error', "Google API request failed with code {$code}.", array( 'body' => $error_body ) );
		}

		return json_decode( wp_remote_retrieve_body( $response ), true );
	}

	/**
	 * Finds and removes a specific entry's row from the Google Sheet.
	 * This effectively "unsyncs" the entry.
	 *
	 * @param int $entry_id The ID of the entry to remove from the sheet.
	 * @return bool|WP_Error True on successful deletion, WP_Error on failure.
	 */
	public function unsync_entry_from_sheet( int $entry_id ) {
		global $wpdb;
		$table = Helper::get_table_name(); // Safe table

		// 1. Get Form ID from Entry ID
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$form_id = $wpdb->get_var( $wpdb->prepare( "SELECT form_id FROM $table WHERE id = %d", $entry_id ) );
		if ( ! $form_id ) {
			return new WP_Error( 'entry_not_found', "Entry with ID {$entry_id} not found in the local database." );
		}

		// 2. Get Spreadsheet and Sheet configuration
		$spreadsheet_id = Helper::get_option( "gsheet_spreadsheet_id_{$form_id}" );
		$sheet_info     = $this->get_spreadsheet_metadata( $spreadsheet_id );

		if ( is_wp_error( $sheet_info ) || ! $spreadsheet_id ) {
			// If there's no sheet configured, it's already "unsynced".
			$this->logger->log( 'Unsync skipped for entry ' . $entry_id . ': No spreadsheet is configured for form ' . $form_id . '.', 'INFO' );
			return true;
		}

		// We need the numeric sheetId for the delete request, not the title.
		$sheet_id    = $sheet_info['sheets'][0]['properties']['sheetId'];
		$sheet_title = $sheet_info['sheets'][0]['properties']['title'];

		// 3. Find the row number by searching the Entry ID column (assuming it's column A)
		$range = rawurlencode( $sheet_title ) . '!A:A';
		$url   = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheet_id}/values/{$range}";

		$response = $this->_make_google_api_request( $url, array(), 'GET' );

		if ( is_wp_error( $response ) ) {
			$this->logger->log( 'Unsync failed for entry ' . $entry_id . ': Could not read sheet to find row. ' . $response->get_error_message(), 'ERROR' );
			return $response;
		}

		$rows_to_delete = array();
		$values         = $response['values'] ?? array();

		foreach ( $values as $index => $row ) {
			if ( isset( $row[0] ) && (int) $row[0] === $entry_id ) {
				// API is 0-indexed, so the index is the row number we need.
				$rows_to_delete[] = $index;
			}
		}

		if ( empty( $rows_to_delete ) ) {
			$this->logger->log( 'Unsync notice for entry ' . $entry_id . ': Row was not found in the Google Sheet.', 'INFO' );
			// The desired state (row is gone) is achieved, so we can return true.

			// Also update local status to be sure.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update( $table, array( 'synced_to_gsheet' => 0 ), array( 'id' => $entry_id ) );
			return true;
		}

		// 4. Build and send the batch delete request.
		// We process the rows in reverse order to avoid shifting indices.
		rsort( $rows_to_delete );
		$requests = array();
		foreach ( $rows_to_delete as $row_index ) {
			$requests[] = array(
				'deleteDimension' => array(
					'range' => array(
						'sheetId'    => $sheet_id,
						'dimension'  => 'ROWS',
						'startIndex' => $row_index,
						'endIndex'   => $row_index + 1,
					),
				),
			);
		}

		$batch_update_result = $this->gsheet_batch_update( $spreadsheet_id, $requests );

		if ( is_wp_error( $batch_update_result ) ) {
			$this->logger->log( 'Unsync failed for entry ' . $entry_id . ': Batch delete request failed. ' . $batch_update_result->get_error_message(), 'ERROR' );
			return $batch_update_result;
		}

		// 5. Update the local database to mark it as unsynced
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update( $table, array( 'synced_to_gsheet' => 0 ), array( 'id' => $entry_id ) );

		$this->logger->log( 'Entry ID ' . $entry_id . ' successfully unsynced from Google Sheets.', 'INFO' );

		return true;
	}
}
