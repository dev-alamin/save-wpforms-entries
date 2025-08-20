<?php

namespace App\AdvancedEntryManager\GoogleSheet;

defined('ABSPATH') || exit;

use App\AdvancedEntryManager\Utility\Helper;
use WP_Error;

class Send_Data
{

    public function __construct()
    {
        // Capture token on init
        // add_action('admin_init', [$this, 'capture_token']);
    }

    public function capture_token()
    {
        if (!isset($_GET['oauth_proxy_code'])) {
            return;
        }

        $auth_code = sanitize_text_field($_GET['oauth_proxy_code']);

        // Exchange the one-time auth code for real tokens
        $response = wp_remote_post(FEM_PROXY_BASE_URL . 'wp-json/fem/v1/token', [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => json_encode([
                'auth_code' => $auth_code,
            ]),
        ]);

        if (is_wp_error($response)) {
            error_log('[fem] Token exchange failed: ' . $response->get_error_message());
            return;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!empty($body['access_token'])) {
            Helper::update_option('google_access_token', sanitize_text_field($body['access_token']));
            Helper::update_option('google_token_expires', time() + intval($body['expires_in'] ?? 3600));
            // Optional: Store refresh token too if ever needed on client (rare)
            wp_safe_redirect(admin_url('admin.php?page=fem-settings&connected=true'));
            exit;
        }

        Helper::set_error_log('Invalid token exchange response: ' . wp_remote_retrieve_body($response));
    }

    /**
     * Get or create the necessary spreadsheet and sheet for a given form.
     * This is the master function for ensuring a sync target exists and is ready.
     *
     * @param int $form_id The ID of the WPForm.
     * @return array|WP_Error An array containing ['spreadsheet_id', 'sheet_title'] or a WP_Error on failure.
     */
    protected function get_or_create_sheet_for_form(int $form_id)
    {
        // Enforce Free Version form limitation
        if (!Helper::is_pro_version()) {
            $linked_forms = Helper::get_option('aem_linked_forms', []);
            if (!in_array($form_id, $linked_forms) && count($linked_forms) >= 1) {
                return new WP_Error('limit_exceeded', 'The free version supports synchronizing data from only one form. Please upgrade to Pro to sync more forms.');
            }
        }

        $spreadsheet_id = Helper::get_option("gsheet_spreadsheet_id_{$form_id}");
        $sheet_title = Helper::get_option("gsheet_sheet_title_{$form_id}");
        $headers_set = Helper::get_option("gsheet_headers_set_{$form_id}");

        // If we have a spreadsheet and the headers have already been set and formatted,
        // we can return immediately. This is the main performance optimization.
        if ($spreadsheet_id && $sheet_title && $headers_set) {
            return [
                'spreadsheet_id' => $spreadsheet_id,
                'sheet_title'    => $sheet_title,
            ];
        }

        // Prevent multiple simultaneous creation attempts for the same form.
        $lock_key = 'aem_gsheet_creating_lock_' . $form_id;
        if (get_transient($lock_key)) {
            return new WP_Error('locked', 'Sheet creation for this form is already in progress.');
        }
        set_transient($lock_key, true, 60); // Lock for 60 seconds

        // --- Create Spreadsheet if it doesn't exist ---
        if (!$spreadsheet_id) {
            $form_data = wpforms()->form->get($form_id, ['content_only' => true]);
            $form_title = $form_data['settings']['form_title'] ?? "Form #{$form_id}";
            $spreadsheet_title = (get_bloginfo('name') ?: 'WPForms Sync') . " - {$form_title}";

            $spreadsheet_id = $this->gsheet_create_spreadsheet($spreadsheet_title);
            if (is_wp_error($spreadsheet_id)) {
                delete_transient($lock_key);
                return $spreadsheet_id;
            }
            Helper::update_option("gsheet_spreadsheet_id_{$form_id}", $spreadsheet_id);
            Helper::update_option("gsheet_spreadsheet_title_{$form_id}", $spreadsheet_title); // Save for UI

            // Track linked forms for the free version
            $linked_forms = Helper::get_option('aem_linked_forms', []);
            if (!in_array($form_id, $linked_forms)) {
                $linked_forms[] = $form_id;
                Helper::update_option('aem_linked_forms', $linked_forms);
            }
        }

        // --- Configure the Sheet (Tab) ---
        $metadata = $this->get_spreadsheet_metadata($spreadsheet_id);
        if (is_wp_error($metadata)) {
            delete_transient($lock_key);
            return $metadata;
        }

        $sheet_properties = $metadata['sheets'][0]['properties']; // Use the first default sheet
        $sheet_id = $sheet_properties['sheetId'];
        $sheet_title = $sheet_properties['title']; // This is the initial title, e.g., "Sheet1"

        // Get canonical headers from form data
        $headers = $this->get_form_headers($form_id);

        // Prepare batch requests for efficiency
        $requests = [
            // 1. Freeze the header row
            [
                'updateSheetProperties' => [
                    'properties' => ['sheetId' => $sheet_id, 'gridProperties' => ['frozenRowCount' => 1]],
                    'fields'     => 'gridProperties.frozenRowCount',
                ],
            ],
            // 2. Write the headers with bold formatting
            [
                'updateCells' => [
                    'rows'   => [['values' => $this->format_cells($headers, true)]], // <-- PASS `true` HERE
                    'fields' => 'userEnteredValue,userEnteredFormat.textFormat.bold',
                    'start'  => ['sheetId' => $sheet_id, 'rowIndex' => 0, 'columnIndex' => 0],
                ]
            ]
        ];

        $batch_result = $this->gsheet_batch_update($spreadsheet_id, $requests);
        if (is_wp_error($batch_result)) {
            delete_transient($lock_key);
            return $batch_result;
        }

        // Save the sheet info for future use
        Helper::update_option("gsheet_sheet_id_{$form_id}", $sheet_id);
        Helper::update_option("gsheet_sheet_title_{$form_id}", $sheet_title); // Use the actual sheet title
        Helper::update_option("gsheet_headers_{$form_id}", $headers); // Cache headers
        Helper::update_option("gsheet_headers_set_{$form_id}", true); // Set the flag to true after successful setup

        delete_transient($lock_key);

        return [
            'spreadsheet_id' => $spreadsheet_id,
            'sheet_title'    => $sheet_title,
        ];
    }

    protected function format_cells(array $values, bool $bold = false): array
    {
        $formatted_cells = [];
        foreach ($values as $value) {
            $cell = ['userEnteredValue' => ['stringValue' => (string) $value]];
            if ($bold) { // <-- Conditional formatting based on the new parameter
                $cell['userEnteredFormat'] = ['textFormat' => ['bold' => true]];
            }
            $formatted_cells[] = $cell;
        }
        return $formatted_cells;
    }

    /**
     * Process a single queued entry to send to Google Sheets.
     */
    public function process_single_entry($args)
    {
        global $wpdb;

        $entry_id = absint($args['entry_id']);
        $table = Helper::get_table_name();

        $entry = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $entry_id));

        if (!$entry) {
            error_log('[AEM]: No entry data found. Sorry');
            return false;
        }

        if ($entry->synced_to_gsheet) {
            error_log('[AEM]: Already synced this entry...');
            return false;
        }

        $form_id = absint($entry->form_id);

        // Step 1: Ensure the target sheet is ready.
        $sheet_info = $this->get_or_create_sheet_for_form($form_id);
        if (is_wp_error($sheet_info)) {
            error_log("[AEM] GSheet preparation failed for form $form_id: " . $sheet_info->get_error_message());
            $this->handle_sync_failure($entry_id, $entry->retry_count);
            return false;
        }

        $spreadsheet_id = $sheet_info['spreadsheet_id'];
        $sheet_title = $sheet_info['sheet_title'];
        
        // Enforce Free Version row limitation
        if (!Helper::is_pro_version()) {
            $metadata = $this->get_spreadsheet_metadata($spreadsheet_id);
            if (!is_wp_error($metadata) && isset($metadata['sheets'][0]['properties']['gridProperties']['rowCount'])) {
                $row_count = $metadata['sheets'][0]['properties']['gridProperties']['rowCount'];
                if ($row_count >= 1000) {
                    error_log("[AEM] GSheet row limit reached for form $form_id. Entry {$entry_id} not synced.");
                    $wpdb->update($table, ['synced_to_gsheet' => 2], ['id' => $entry_id]); // '2' can indicate 'sync_limit_reached'
                    return false;
                }
            }
        }

        // Step 2: Prepare the data row, ensuring it matches the header order.
        $row_data = $this->prepare_row_data($entry);
        if (is_wp_error($row_data)) {
            error_log("[AEM] GSheet data preparation failed for entry $entry_id: " . $row_data->get_error_message());
            $this->handle_sync_failure($entry_id, $entry->retry_count);
            return false;
        }

        // Step 3: Append data to the sheet.
        $range = rawurlencode($sheet_title) . '!A:Z';
        $body = ['values' => [$row_data]];
        $url = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheet_id}/values/{$range}:append?valueInputOption=USER_ENTERED&insertDataOption=INSERT_ROWS";

        $response = $this->_make_google_api_request($url, $body, 'POST');

        if (is_wp_error($response)) {
            error_log("[AEM] GSheet append failed for entry $entry_id. Error: " . $response->get_error_message());
            $this->handle_sync_failure($entry_id, $entry->retry_count);
            return false;
        }

        error_log('[AEM] Google sync is going on ' . $entry_id . ' Form ID: ' . $form_id );

        // Step 4: Mark as synced on success.
        $wpdb->update($table, ['synced_to_gsheet' => 1, 'retry_count' => 0], ['id' => $entry_id]);

        return true;
    }

    /**
     * Retrieves the canonical headers for a form by inspecting a sample entry.
     *
     * This method ensures the header order is consistent with the data structure.
     * @param int $form_id The ID of the form.
     * @return array An array of headers.
     */
    protected function get_form_headers(int $form_id): array
    {
        $cached_headers = Helper::get_option("gsheet_headers_{$form_id}");
        if ($cached_headers && is_array($cached_headers)) {
            return $cached_headers;
        }

        $headers = [
            __('Entry ID', 'forms-entries-manager'),
            __('Submission Date', 'forms-entries-manager'),
            __( 'Name', 'forms-entries-manager' ),
            __( 'Email', 'forms-entries-manager' )
        ];
        
        // **BUG FIX**: Directly get headers from WPForms form fields
        $form_data = wpforms()->get('form_handler')->get_form_fields($form_id, false);
        if (!is_wp_error($form_data) && !empty($form_data['fields'])) {
            foreach ($form_data['fields'] as $field_id => $field_data) {
                if (!empty($field_data['label'])) {
                    $headers[] = $field_data['label'];
                }
            }
        }

        $headers[] = __('Status', 'forms-entries-manager');
        $headers[] = __('Note', 'forms-entries-manager');

        Helper::update_option("gsheet_headers_{$form_id}", $headers);

        return $headers;
    }

    /**
     * Prepares a single row of data, ensuring the order and values
     * match the canonical headers for a form.
     *
     * @param object $entry The entry object from the database.
     * @return array The formatted row data.
     */
    protected function prepare_row_data($entry)
    {
        $form_id = absint($entry->form_id);
        $headers = $this->get_form_headers($form_id);

        if (is_wp_error($headers)) {
            return $headers;
        }

        $row = [];
        $entry_data = maybe_unserialize($entry->entry);
        $entry_data = is_array($entry_data) ? $entry_data : [];
        
        // Helper::set_error_log( print_r( $entry_data, true ) );

        foreach ($headers as $header_title) {
            $value = '';

            switch ($header_title) {
                case __('Entry ID', 'forms-entries-manager'):
                    $value = $entry->id;
                    break;
                case __('Submission Date', 'forms-entries-manager'):
                    $value = get_date_from_gmt($entry->created_at, 'Y-m-d H:i:s');
                    break;
                case __( 'Name', 'forms-entries-manager' ):
                    $value = $entry->name;
                    break;
                case __( 'Email', 'forms-entries-manager' ):
                    $value = $entry->email;
                    break;
                case __('Status', 'forms-entries-manager'):
                    $value = $entry->status ?? '';
                    break;
                case __('Note', 'forms-entries-manager'):
                    $value = $entry->note ?? '';
                    break;
                default:
                    if (isset($entry_data[$header_title])) {
                        $value = $entry_data[$header_title];
                    }
                    break;
            }

            if ( is_string( $value ) && preg_match('/^[=+]/', trim( $value ) ) ) {
                $value = "'" . $value;
            }
            
            $row[] = (string) $value;

            // Helper::set_error_log( print_r( $header_title, true ) );
        }

        // Helper::set_error_log( print_r( $row, true ) );

        return $row;
    }

    /**
     * Handles the logic for a failed sync attempt (retry or mark as failed).
     */
    protected function handle_sync_failure(int $entry_id, int $current_retry_count)
    {
        global $wpdb;
        $table = Helper::get_table_name();

        if ($current_retry_count < 5) {
            $wpdb->update($table, ['retry_count' => $current_retry_count + 1], ['id' => $entry_id]);
            // Schedule retry with exponential backoff
            $delay = 60 * pow(2, $current_retry_count); // 1 min, 2 min, 4 min, etc.
            as_schedule_single_action(time() + $delay, 'femprocess_gsheet_entry', ['entry_id' => $entry_id]);
        } else {
            error_log("[AEM] Max retry limit reached for entry ID $entry_id. Sync abandoned.");
            // Optionally, mark as failed in the DB
            // $wpdb->update($table, ['status' => 'failed_sync'], ['id' => $entry_id]);
        }
    }

    /**
     * Performs a batch update request to the Google Sheets API.
     */
    public function gsheet_batch_update($spreadsheet_id, $requests)
    {
        $body = ['requests' => $requests];
        $url = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheet_id}:batchUpdate";
        return $this->_make_google_api_request($url, $body, 'POST');
    }

    public function gsheet_create_spreadsheet($title = 'WPForms Entries')
    {
        $body = [
            'properties' => [
                'title' => $title,
            ]
        ];
        $url = 'https://sheets.googleapis.com/v4/spreadsheets';
        $response = $this->_make_google_api_request($url, $body, 'POST');
        return $response['spreadsheetId'] ?? new WP_Error('create_failed', 'Spreadsheet creation failed.');
    }

    /**
     * Fetch spreadsheet metadata (to get sheet info like sheetId)
     */
    protected function get_spreadsheet_metadata(string $spreadsheet_id)
    {
        $url = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheet_id}";
        $response = $this->_make_google_api_request($url, [], 'GET');
        return $response;
    }

    /**
     * Enqueue unsynced entries for Google Sheets sync in batches.
     *
     * @param int|null $form_id Optional. Limit enqueueing to this form ID.
     * @param int $batch_size Number of entries to enqueue per batch. Default 50.
     * @param int $delay_between Delay in seconds between scheduled jobs. Default 5.
     *
     * @return int Number of entries enqueued in this batch.
     */
    public function enqueue_unsynced_entries($form_id = null, $batch_size = 50, $delay_between = 5)
    {
        global $wpdb;

        $table = Helper::get_table_name();

        $where = 'synced_to_gsheet = 0';
        $params = [];

        if ($form_id) {
            $where .= ' AND form_id = %d';
            $params[] = $form_id;
        }

        $query = "SELECT id FROM $table WHERE $where ORDER BY id ASC LIMIT %d";
        $params[] = $batch_size;

        $entries = $wpdb->get_results($wpdb->prepare($query, ...$params));

        if (empty($entries)) {
            return 0;
        }

        $now = time();

        foreach ($entries as $index => $entry) {
            $scheduled_time = $now + ($index * $delay_between);

            // Check if already scheduled to avoid duplicates
            if (!as_next_scheduled_action('femprocess_gsheet_entry', ['entry_id' => $entry->id])) {
                as_schedule_single_action($scheduled_time, 'femprocess_gsheet_entry', ['entry_id' => $entry->id]);
            }
        }

        // error_log('[AEM] : Entri is syncing...' . print_r($entries, true));

        return count($entries);
    }

    private function _make_google_api_request(string $url, array $body = [], string $method = 'POST')
    {
        $access_token = Helper::get_access_token();
        if (!$access_token) {
            return new WP_Error('no_token', 'Missing or invalid access token.');
        }

        $args = [
            'method'  => $method,
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 20,
        ];

        if (!empty($body)) {
            $args['body'] = wp_json_encode($body);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            $error_body = wp_remote_retrieve_body($response);
            error_log("[AEM] Google API Error: Code $code - $error_body");
            return new WP_Error('api_error', "Google API request failed with code {$code}.", ['body' => $error_body]);
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    /**
     * Finds and removes a specific entry's row from the Google Sheet.
     * This effectively "unsyncs" the entry.
     *
     * @param int $entry_id The ID of the entry to remove from the sheet.
     * @return bool|WP_Error True on successful deletion, WP_Error on failure.
     */
    public function unsync_entry_from_sheet(int $entry_id)
    {
        global $wpdb;
        $table = Helper::get_table_name();

        // 1. Get Form ID from Entry ID
        $form_id = $wpdb->get_var($wpdb->prepare("SELECT form_id FROM $table WHERE id = %d", $entry_id));
        if (!$form_id) {
            return new WP_Error('entry_not_found', "Entry with ID {$entry_id} not found in the local database.");
        }

        // 2. Get Spreadsheet and Sheet configuration
        $spreadsheet_id = Helper::get_option("gsheet_spreadsheet_id_{$form_id}");
        $sheet_info = $this->get_spreadsheet_metadata($spreadsheet_id);

        if (is_wp_error($sheet_info) || !$spreadsheet_id) {
            // If there's no sheet configured, it's already "unsynced".
            error_log("[AEM] Unsync skipped for entry {$entry_id}: No spreadsheet is configured for form {$form_id}.");
            return true;
        }
        
        // We need the numeric sheetId for the delete request, not the title.
        $sheet_id = $sheet_info['sheets'][0]['properties']['sheetId'];
        $sheet_title = $sheet_info['sheets'][0]['properties']['title'];

        // 3. Find the row number by searching the Entry ID column (assuming it's column A)
        $range = rawurlencode($sheet_title) . '!A:A';
        $url = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheet_id}/values/{$range}";
        
        $response = $this->_make_google_api_request($url, [], 'GET');

        if (is_wp_error($response)) {
            error_log("[AEM] Unsync failed for entry {$entry_id}: Could not read sheet to find row. " . $response->get_error_message());
            return $response;
        }

        $rows_to_delete = [];
        $values = $response['values'] ?? [];

        foreach ($values as $index => $row) {
            if (isset($row[0]) && (int) $row[0] === $entry_id) {
                // API is 0-indexed, so the index is the row number we need.
                $rows_to_delete[] = $index;
            }
        }

        if (empty($rows_to_delete)) {
            error_log("[AEM] Unsync notice for entry {$entry_id}: Row was not found in the Google Sheet.");
            // The desired state (row is gone) is achieved, so we can return true.
            // Also update local status to be sure.
            $wpdb->update($table, ['synced_to_gsheet' => 0], ['id' => $entry_id]);
            return true;
        }

        // 4. Build and send the batch delete request.
        // We process the rows in reverse order to avoid shifting indices.
        rsort($rows_to_delete);
        $requests = [];
        foreach ($rows_to_delete as $row_index) {
            $requests[] = [
                'deleteDimension' => [
                    'range' => [
                        'sheetId'     => $sheet_id,
                        'dimension'   => 'ROWS',
                        'startIndex'  => $row_index,
                        'endIndex'    => $row_index + 1,
                    ],
                ],
            ];
        }
        
        $batch_update_result = $this->gsheet_batch_update($spreadsheet_id, $requests);

        if (is_wp_error($batch_update_result)) {
            error_log("[AEM] Unsync failed for entry {$entry_id}: The batch delete request failed.");
            return $batch_update_result;
        }

        // 5. Update the local database to mark it as unsynced
        $wpdb->update($table, ['synced_to_gsheet' => 0], ['id' => $entry_id]);

        error_log("[AEM] Successfully unsynced entry {$entry_id} from Google Sheet.");

        return true;
    }
}