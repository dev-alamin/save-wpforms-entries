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
 * @since      1.0.0
 *
 * @see        https://developers.google.com/sheets/api
 */
namespace App\AdvancedEntryManager\GoogleSheet;

use App\AdvancedEntryManager\Utility\Helper;
use WP_Error;

class Send_Data {

    public function __construct() {
        // Hook async queue processing
        add_action('swpfe_process_gsheet_entry', [$this, 'process_single_entry']);

        // Capture token on init
        add_action('init', [$this, 'capture_token']);
    }

    public function capture_token() {
        if (isset($_GET['access_token'])) {
            Helper::update_option('google_access_token', sanitize_text_field($_GET['access_token']));
            Helper::update_option('google_token_expires', time() + 3600);
            wp_safe_redirect(admin_url('admin.php?page=swpfe-settings&connected=true'));
            exit;
        }
    }

    /**
     * Save spreadsheet ID for a form in options
     */
    protected function save_spreadsheet_id_for_form(int $form_id, string $spreadsheet_id) {
        Helper::update_option("gsheet_spreadsheet_id_{$form_id}", $spreadsheet_id);
    }

    /**
     * Get spreadsheet ID for a form from options
     */
    protected function get_spreadsheet_id_for_form(int $form_id): ?string {
        return Helper::get_option("gsheet_spreadsheet_id_{$form_id}", null);
    }

    /**
     * Save sheet ID for a form in options
     */
    protected function save_sheet_id_for_form(int $form_id, int $sheet_id) {
        Helper::update_option("gsheet_sheet_id_{$form_id}", $sheet_id);
    }

    /**
     * Get sheet ID for a form from options
     */
    protected function get_sheet_id_for_form(int $form_id): ?int {
        return (int) Helper::get_option("gsheet_sheet_id_{$form_id}", 0);
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
     * Find sheetId by sheet title from spreadsheet metadata
     */
    protected function find_sheet_id_by_title(array $metadata, string $title): ?int {
        if (empty($metadata['sheets'])) {
            return null;
        }

        foreach ($metadata['sheets'] as $sheet) {
            if (!empty($sheet['properties']['title']) && $sheet['properties']['title'] === $title) {
                return (int) $sheet['properties']['sheetId'];
            }
        }

        return null;
    }

    /**
     * Process a single queued entry to send to Google Sheets
     * Uses spreadsheet and sheet info saved per form.
     */
    public function process_single_entry($args) {
        global $wpdb;

        $entry_id = absint($args['entry_id']);
        $table = $wpdb->prefix . 'swpfe_entries';

        $entry = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $entry_id));
        if (!$entry || $entry->synced_to_gsheet) {
            return;
        }

        $form_id = absint($entry->form_id);
        $spreadsheet_id = $this->get_spreadsheet_id_for_form($form_id);
        $sheet_id = $this->get_sheet_id_for_form($form_id);

        $access_token = Helper::get_access_token();
        if (!$access_token) {
            error_log("[AEM] GSheet sync failed: No token.");
            return;
        }

        // If no spreadsheet yet, create one and save ID
        if (!$spreadsheet_id) {
            $spreadsheet_title = "WPForms Sync - Form #$form_id";
            $spreadsheet_id = $this->gsheet_create_spreadsheet($spreadsheet_title);
            if (is_wp_error($spreadsheet_id)) {
                error_log("[AEM] Failed to create spreadsheet for form $form_id: " . $spreadsheet_id->get_error_message());
                return;
            }
            $this->save_spreadsheet_id_for_form($form_id, $spreadsheet_id);
        }

        // If no sheet/tab ID, create a new sheet/tab and save sheet ID
        if (!$sheet_id) {
            $sheet_title = "Form_$form_id";
            $add_sheet_result = $this->gsheet_add_sheet($spreadsheet_id, $sheet_title);
            if (is_wp_error($add_sheet_result)) {
                error_log("[AEM] Failed to add sheet for form $form_id: " . $add_sheet_result->get_error_message());
                return;
            }

            $metadata = $this->get_spreadsheet_metadata($spreadsheet_id);
            if (is_wp_error($metadata)) {
                error_log("[AEM] Failed to get metadata for spreadsheet $spreadsheet_id");
                return;
            }

            $sheet_id = $this->find_sheet_id_by_title($metadata, $sheet_title);
            if (!$sheet_id) {
                error_log("[AEM] Could not find sheet ID for title $sheet_title");
                return;
            }
            $this->save_sheet_id_for_form($form_id, $sheet_id);

            // Freeze header row
            $this->gsheet_freeze_header_row($spreadsheet_id, $sheet_id);

            // Write headers (customize as per your form fields)
            $headers = array_keys(maybe_unserialize($entry->entry));
            if (empty($headers)) {
                // fallback if entry is empty
                $headers = ['Field1', 'Field2', 'Field3'];
            }
            $this->gsheet_write_headers($spreadsheet_id, "{$sheet_title}!A1", $headers);
        }

        // Prepare data values
        $entry_data = maybe_unserialize($entry->entry);
        if (!is_array($entry_data)) {
            $entry_data = [$entry_data];
        }
        $body = ['values' => [$entry_data]];

        // Append data to sheet
        $sheet_title = "Form_$form_id";
        $range = "{$sheet_title}!A:Z";

        $response = wp_remote_post(
            "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheet_id}/values/{$range}:append?valueInputOption=RAW",
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

        if (is_wp_error($response) || !in_array($code, [200, 201], true)) {
            $error_msg = is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_body($response);
            error_log("[AEM] GSheet sync failed for entry ID $entry_id. Code: $code. Error: $error_msg");

            // Retry logic with max retry count
            $retry_count = (int) $entry->retry_count;
            if ($retry_count < 5) {
                $wpdb->update($table, ['retry_count' => $retry_count + 1], ['id' => $entry_id]);
                as_schedule_single_action(time() + 300, 'swpfe_process_gsheet_entry', ['entry_id' => $entry_id]);
            } else {
                error_log("[AEM] Max retry limit reached for entry ID $entry_id");
            }
            return;
        }

        // Mark as synced, reset retry count
        $wpdb->update($table, [
            'synced_to_gsheet' => 1,
            'retry_count' => 0,
        ], ['id' => $entry_id]);
    }

    // Your existing API wrapper methods (gsheet_create_spreadsheet, gsheet_add_sheet, gsheet_freeze_header_row, gsheet_write_headers) go here unchanged...

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

    public function gsheet_add_sheet($spreadsheet_id, $title) {
        $access_token = Helper::get_access_token();
        if (!$access_token) {
            return new WP_Error('no_token', 'Missing access token.');
        }

        $body = [
            'requests' => [
                [
                    'addSheet' => [
                        'properties' => [
                            'title' => $title,
                        ]
                    ]
                ]
            ]
        ];

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

        if (is_wp_error($response)) {
            return $response;
        }

        return true;
    }

    public function gsheet_freeze_header_row($spreadsheet_id, $sheet_id = 0) {
        $access_token = Helper::get_access_token();
        if (!$access_token) {
            return new WP_Error('no_token', 'Missing access token.');
        }

        $body = [
            'requests' => [
                [
                    'updateSheetProperties' => [
                        'properties' => [
                            'sheetId' => $sheet_id,
                            'gridProperties' => [
                                'frozenRowCount' => 1
                            ]
                        ],
                        'fields' => 'gridProperties.frozenRowCount'
                    ]
                ]
            ]
        ];

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

        if (is_wp_error($response)) {
            return $response;
        }

        return true;
    }

    public function gsheet_write_headers($spreadsheet_id, $range, $headers = []) {
        $access_token = Helper::get_access_token();
        if (!$access_token) {
            return new WP_Error('no_token', 'Missing access token.');
        }

        $body = [
            'values' => [$headers]
        ];

        $response = wp_remote_post(
            "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheet_id}/values/{$range}?valueInputOption=RAW",
            [
                'method' => 'PUT',
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json',
                ],
                'body' => wp_json_encode($body),
            ]
        );

        if (is_wp_error($response)) {
            return $response;
        }

        return true;
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

        $table = $wpdb->prefix . 'swpfe_entries';

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
            if (!as_next_scheduled_action('swpfe_process_gsheet_entry', ['entry_id' => $entry->id])) {
                as_schedule_single_action($scheduled_time, 'swpfe_process_gsheet_entry', ['entry_id' => $entry->id]);
            }
        }

        return count($entries);
    }
}

