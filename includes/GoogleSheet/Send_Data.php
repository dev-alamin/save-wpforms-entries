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

namespace App\AdvancedEntryManger\GoogleSheet;

include __DIR__ . '/../helper.php';

class Send_Data
{
    /**
     * Constructor.
     *
     * Hooks into the `wpforms_process_complete` action to trigger Google Sheets sync
     * when a WPForms form is successfully submitted.
     */
    public function __construct()
    {
        add_action('wpforms_process_complete', [$this, 'swpfe_send_to_google_sheets'], 10, 4);
    }

    /**
     * Send form data to Google Sheets.
     *
     * Called when WPForms completes processing a submission.
     * It builds the request body from submitted form fields and
     * appends a new row to the connected Google Sheet using Sheets API v4.
     *
     * @param array $fields     Array of submitted form fields.
     * @param array $entry      Entry-specific meta data.
     * @param array $form_data  Form settings and configuration.
     * @param int   $entry_id   WPForms entry ID.
     *
     * @return void
     */
    public function swpfe_send_to_google_sheets($fields, $entry, $form_data, $entry_id)
    {
        $access_token = swpfe_get_access_token();

        if (!$access_token) {
            error_log('Google Sheets: No access token available.');
            return;
        }

        $values = [];
        foreach ($fields as $field) {
            $values[] = $field['value'];
        }

        $body = ['values' => [$values]];

        $spreadsheet_id = '1iaHvsexQRtdtuDSYNPhnWmuPuy_0ij7PCQnMQj0SWuY';
        $range = 'Sheet1';

        $response = wp_remote_post(
            "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheet_id}/values/{$range}:append?valueInputOption=RAW",
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type'  => 'application/json',
                ],
                'body'    => json_encode($body),
                'timeout' => 15,
            ]
        );

        if (is_wp_error($response)) {
            error_log('Google Sheets API error: ' . $response->get_error_message());
        } else {
            $code = wp_remote_retrieve_response_code($response);
            if ($code !== 200) {
                error_log('Google Sheets API response code: ' . $code);
                error_log('Response body: ' . wp_remote_retrieve_body($response));
            }
        }
    }
}
