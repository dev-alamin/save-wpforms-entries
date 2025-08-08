<?php
/**
 * Send_Data Class
 *
 * Handles sending WPForms form submission data to Google Sheets.
 *
 * This class listens to the `wpforms_process_complete` action hook, extracts submitted
 * form field values, and sends the data to a specified Google Sheet using the Sheets API.
 * Requires a valid Google OAuth access token to function.
 *
 * @package    Save_WPForms_Entries
 * @subpackage Google_Sheets_Integration
 * @author     Mamu
 * @copyright  Copyright (c) 2025
 * @license    GPL-2.0-or-later
 * @since      1.1.0
 *
 * @see        https://developers.google.com/sheets/api
 */
namespace App\AdvancedEntryManager\GoogleSheet;

use App\AdvancedEntryManager\Utility\Helper;
use WP_Error;

class Send_Data {

    public function __construct() {
        // Hook async queue processing
        add_action('aemfw_process_gsheet_entry', [$this, 'process_single_entry']);

        // Capture token on init
        add_action('admin_init', [$this, 'capture_token']);
    }

    public function capture_token() {
        if (!isset($_GET['oauth_proxy_code'])) {
            return;
        }

        $auth_code = sanitize_text_field($_GET['oauth_proxy_code']);

        // Exchange the one-time auth code for real tokens
        $response = wp_remote_post( AEMFW_PROXY_BASE_URL . 'wp-json/swpfe/v1/token', [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => json_encode([
                'auth_code' => $auth_code,
            ]),
        ]);

        if (is_wp_error($response)) {
            error_log('[SWPFE] Token exchange failed: ' . $response->get_error_message());
            return;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!empty($body['access_token'])) {
            Helper::update_option('google_access_token', sanitize_text_field($body['access_token']));
            Helper::update_option('google_token_expires', time() + intval($body['expires_in'] ?? 3600));
            // Optional: Store refresh token too if ever needed on client (rare)
            wp_safe_redirect(admin_url('admin.php?page=swpfe-settings&connected=true'));
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
    protected function get_or_create_sheet_for_form(int $form_id) {
        $spreadsheet_id = Helper::get_option("gsheet_spreadsheet_id_{$form_id}");
        $sheet_title = Helper::get_option("gsheet_sheet_title_{$form_id}");

        // If we already have what we need, return it.
        if ($spreadsheet_id && $sheet_title) {
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
            // 2. Write the headers
            [
                'updateCells' => [
                    'rows'  => [['values' => $this->format_cells($headers)]],
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

        delete_transient($lock_key);

        return [
            'spreadsheet_id' => $spreadsheet_id,
            'sheet_title'    => $sheet_title,
        ];
    }
    
    /**
     * Formats cell data for the Google Sheets API, making headers bold.
     */
    protected function format_cells(array $values, bool $bold = true): array {
        $formatted_cells = [];
        foreach ($values as $value) {
            $formatted_cells[] = [
                'userEnteredValue' => ['stringValue' => $value],
                'userEnteredFormat' => ['textFormat' => ['bold' => $bold]]
            ];
        }
        return $formatted_cells;
    }

    /**
     * Process a single queued entry to send to Google Sheets.
     */
    public function process_single_entry($args) {
        global $wpdb;

        $entry_id = absint($args['entry_id']);
        $table = Helper::get_table_name();

        $entry = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $entry_id));
        if (!$entry || $entry->synced_to_gsheet) {
            return;
        }

        $form_id = absint($entry->form_id);

        // Step 1: Ensure the target sheet is ready.
        $sheet_info = $this->get_or_create_sheet_for_form($form_id);
        if (is_wp_error($sheet_info)) {
            error_log("[AEM] GSheet preparation failed for form $form_id: " . $sheet_info->get_error_message());
            $this->handle_sync_failure($entry_id, $entry->retry_count);
            return;
        }
        
        $spreadsheet_id = $sheet_info['spreadsheet_id'];
        $sheet_title = $sheet_info['sheet_title'];
        
        // Step 2: Prepare the data row, ensuring it matches the header order.
        $row_data = $this->prepare_row_data($entry);
        if (is_wp_error($row_data)) {
            error_log("[AEM] GSheet data preparation failed for entry $entry_id: " . $row_data->get_error_message());
            $this->handle_sync_failure($entry_id, $entry->retry_count);
            return;
        }

        // Step 3: Append data to the sheet.
        $access_token = Helper::get_access_token();
        if (!$access_token) {
            error_log("[AEM] GSheet sync failed for entry $entry_id: No access token.");
            return;
        }

        $range = rawurlencode($sheet_title) . '!A:Z';
        $body = ['values' => [$row_data]];

        $response = wp_remote_post(
            "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheet_id}/values/{$range}:append?valueInputOption=USER_ENTERED&insertDataOption=INSERT_ROWS",
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type'  => 'application/json',
                ],
                'body'    => wp_json_encode($body),
                'timeout' => 20,
            ]
        );

        $code = wp_remote_retrieve_response_code($response);
        if (is_wp_error($response) || $code !== 200) {
            $error_msg = is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_body($response);
            error_log("[AEM] GSheet append failed for entry $entry_id. Code: $code. Error: $error_msg");
            $this->handle_sync_failure($entry_id, $entry->retry_count);
            return;
        }

        // Step 4: Mark as synced on success.
        $wpdb->update($table, ['synced_to_gsheet' => 1, 'retry_count' => 0], ['id' => $entry_id]);
    }

    /**
     * Retrieves the canonical headers for a form.
     * @return array
     */
    protected function get_form_headers(int $form_id): array {
        // Return cached headers if available
        $cached_headers = Helper::get_option("gsheet_headers_{$form_id}");
        if ($cached_headers && is_array($cached_headers)) {
            return $cached_headers;
        }

        $headers = [
            'Entry ID',
            'Submission Date',
            'Entry URL',
        ];

        $form_data = wpforms()->form->get($form_id, ['content_only' => true]);
        if (!empty($form_data['fields'])) {
            foreach ($form_data['fields'] as $field) {
                $headers[] = $field['label'] ?? "Field #{$field['id']}";
            }
        }
        
        return $headers;
    }

    /**
     * Prepares a single row of data, ensuring order matches headers.
     * @return array|WP_Error
     */
    protected function prepare_row_data($entry) {
        $form_id = absint($entry->form_id);
        $form_data = wpforms()->form->get($form_id, ['content_only' => true]);
        if (!$form_data) {
            return new WP_Error('no_form_data', "Could not retrieve form data for form ID {$form_id}.");
        }

        $entry_fields = json_decode($entry->entry, true);
        $row = [];

        // Add metadata values
        $row[] = $entry->id;
        $row[] = get_date_from_gmt($entry->created_at, 'Y-m-d H:i:s');
        $row[] = admin_url('admin.php?page=swpfe-entries&view_entry=' . $entry->id);

        // Add form field values in the correct order
        foreach ($form_data['fields'] as $field_info) {
            $field_id = $field_info['id'];
            $value = $entry_fields[$field_id]['value'] ?? '';

            // Handle complex fields (e.g., arrays from checkboxes)
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            $row[] = (string) $value;
        }

        return $row;
    }
    
    /**
     * Handles the logic for a failed sync attempt (retry or mark as failed).
     */
    protected function handle_sync_failure(int $entry_id, int $current_retry_count) {
        global $wpdb;
        $table = Helper::get_table_name();
        
        if ($current_retry_count < 5) {
            $wpdb->update($table, ['retry_count' => $current_retry_count + 1], ['id' => $entry_id]);
            // Schedule retry with exponential backoff
            $delay = 60 * pow(2, $current_retry_count); // 1 min, 2 min, 4 min, etc.
            as_schedule_single_action(time() + $delay, 'aemfw_process_gsheet_entry', ['entry_id' => $entry_id]);
        } else {
            error_log("[AEM] Max retry limit reached for entry ID $entry_id. Sync abandoned.");
            // Optionally, mark as failed in the DB
            // $wpdb->update($table, ['status' => 'failed_sync'], ['id' => $entry_id]);
        }
    }

    /**
     * Performs a batch update request to the Google Sheets API.
     */
    public function gsheet_batch_update($spreadsheet_id, $requests) {
        $access_token = Helper::get_access_token();
        if (!$access_token) return new WP_Error('no_token', 'Missing access token.');

        $body = ['requests' => $requests];
        $response = wp_remote_post(
            "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheet_id}:batchUpdate",
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type'  => 'application/json',
                ],
                'body' => wp_json_encode($body),
            ]
        );
        
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return new WP_Error('batch_update_failed', 'Batch update failed.');
        }

        return true;
    }

    public function gsheet_create_spreadsheet($title = 'WPForms Entries') {
        $access_token = Helper::get_access_token();

        if (!$access_token) {
            return new WP_Error('no_token', 'Missing access token.');
        }

        $body = [
            'properties' => [
                'title' => $title,
            ]
        ];

        $response = wp_remote_post(
            'https://sheets.googleapis.com/v4/spreadsheets',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type'  => 'application/json',
                ],
                'body' => wp_json_encode($body),
            ]
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        return $data['spreadsheetId'] ?? new WP_Error('create_failed', 'Spreadsheet creation failed.');
    }

    /**
     * Fetch spreadsheet metadata (to get sheet info like sheetId)
     */
    protected function get_spreadsheet_metadata(string $spreadsheet_id) {
        $access_token = Helper::get_access_token();
        if (!$access_token) {
            return new WP_Error('no_token', 'Missing access token.');
        }

        $response = wp_remote_get(
            "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheet_id}",
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json',
                ],
                'timeout' => 15,
            ]
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return new WP_Error('http_error', 'HTTP code ' . $code);
        }

        return json_decode(wp_remote_retrieve_body($response), true);
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
    public function enqueue_unsynced_entries($form_id = null, $batch_size = 50, $delay_between = 5) {
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

        if ( empty( $entries ) ) {
            return 0;
        }

        $now = time();

        foreach ($entries as $index => $entry) {
            $scheduled_time = $now + ($index * $delay_between);

            // Check if already scheduled to avoid duplicates
            if (!as_next_scheduled_action('aemfw_process_gsheet_entry', ['entry_id' => $entry->id])) {
                as_schedule_single_action($scheduled_time, 'aemfw_process_gsheet_entry', ['entry_id' => $entry->id]);
            }
        }

        return count($entries);
    }

    private function _make_google_api_request(string $url, array $body = [], string $method = 'POST') {
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
}