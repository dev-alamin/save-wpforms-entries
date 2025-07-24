<?php

namespace SWPFE\GSHEET;

include __DIR__ . '/../helper.php';

class Send_Data
{

    public function __construct()
    {
        add_action('wpforms_process_complete', [$this, 'swpfe_send_to_google_sheets'], 10, 4);
    }

    function swpfe_send_to_google_sheets($fields, $entry, $form_data, $entry_id)
    {
        $spreadsheet_id = '1iaHvsexQRtdtuDSYNPhnWmuPuy_0ij7PCQnMQj0SWuY';

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

        $spreadsheet_id = 'your-sheet-id'; // Replace with actual ID
        $range = 'Sheet1'; // Adjust sheet name if needed

        $response = wp_remote_post(
            "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheet_id}/values/{$range}:append?valueInputOption=RAW",
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type'  => 'application/json',
                ],
                'body' => json_encode($body),
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
