<?php

namespace App\AdvancedEntryManager\Api\Callback;

use App\AdvancedEntryManager\Api\Route;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use App\AdvancedEntryManager\Utility\Helper;

/**
 * Class Export_Entries
 *
 * Handles the retrieval of forms from the custom database table.
 */
class Export_Entries
{
    /**
     * Export selected WPForms entries as a CSV file download.
     *
     * This method handles a REST API POST request, expecting an array of entry IDs 
     * under the 'ids' parameter. It fetches entries from the custom `swpfe_entries` table,
     * unserializes the stored entry data, and outputs it as a CSV file.
     * 
     * The CSV file includes an 'id' column as the first column, followed by the entry data keys.
     *
     * @param WP_REST_Request $request The REST API request object containing parameters.
     * 
     * @return WP_Error|void Returns WP_Error on invalid input or no data; otherwise sends CSV download and exits.
     * 
     * @throws void Sends CSV headers and exits script after output.
     */
    public function export_entries_csv_bulk($request)
    {
        // Check nonce for REST API request
        if (! isset($_SERVER['HTTP_X_WP_NONCE']) || ! wp_verify_nonce($_SERVER['HTTP_X_WP_NONCE'], 'wp_rest')) {
            return new \WP_Error(
                'rest_forbidden',
                __('You are not allowed to perform this action.', 'advanced-entries-manager-for-wpforms'),
                ['status' => 403]
            );
        }

        global $wpdb;
        $ids = $request->get_param('ids');

        if (empty($ids) || !is_array($ids)) {
            return new \WP_Error('invalid_data', __('No entries selected.', 'advanced-entries-manager-for-wpforms'), ['status' => 400]);
        }

        // Prepare placeholders for SQL IN clause
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $query = "SELECT * FROM {$wpdb->prefix}swpfe_entries WHERE id IN ($placeholders)";
        $entries = $wpdb->get_results($wpdb->prepare($query, $ids), ARRAY_A);

        if (empty($entries)) {
            return new \WP_Error('no_data', __('No data found.', 'advanced-entries-manager-for-wpforms'), ['status' => 404]);
        }

        // Set headers for CSV file download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="aem-entries.csv"');

        $output = fopen('php://output', 'w');

        // Extract header columns from first entry's unserialized data keys
        $first = $entries[0];
        $entry_data = maybe_unserialize($first['entry']);
        $headers = array_keys($entry_data);

        // Add 'id' as first column header
        array_unshift($headers, 'id');

        // Write CSV headers
        fputcsv($output, $headers);

        // Write CSV rows including 'id' and entry data values
        foreach ($entries as $entry) {
            $data = maybe_unserialize($entry['entry']);
            $row = [];

            // Add the 'id' column value first
            $row[] = $entry['id'];

            // Add other columns in header order
            foreach (array_slice($headers, 1) as $key) {
                $row[] = $data[$key] ?? '-';
            }

            fputcsv($output, $row);
        }

        fclose($output);
        exit; // Stop execution to prevent extra output
    }

   public function export_csv_full(WP_REST_Request $request)
    {
        if (! isset($_SERVER['HTTP_X_WP_NONCE']) || ! wp_verify_nonce($_SERVER['HTTP_X_WP_NONCE'], 'wp_rest')) {
            return new WP_Error(
                'rest_forbidden',
                __('You are not allowed to perform this action.', 'advanced-entries-manager-for-wpforms'),
                ['status' => 403]
            );
        }

        global $wpdb;

        // === 1. Sanitize & validate inputs ===
        $last_id        = absint($request->get_param('last_id') ?? 0);
        $batch_size     = absint($request->get_param('batch_size') ?? 500);
        $form_id        = absint($request->get_param('form_id') ?? 0);
        $date_from      = sanitize_text_field($request->get_param('date_from') ?? '');
        $date_to        = sanitize_text_field($request->get_param('date_to') ?? '');
        $exclude_fields = $request->get_param('exclude_fields');
        $exclude_fields = is_string($exclude_fields) ? explode(',', $exclude_fields) : (array) $exclude_fields;

        if (! $form_id) {
            return new WP_Error(
                'missing_form_id',
                __('Form ID is required.', 'advanced-entries-manager-for-wpforms'),
                ['status' => 400]
            );
        }

        // === 2. Build WHERE clauses (keyset pagination) ===
        $where_clauses = ['form_id = %d'];
        $args = [$form_id];

        if ($date_from) {
            $where_clauses[] = 'created_at >= %s';
            $args[] = $date_from;
        }

        if ($date_to) {
            $where_clauses[] = 'created_at <= %s';
            $args[] = $date_to;
        }

        if ($last_id) {
            $where_clauses[] = 'id > %d';
            $args[] = $last_id;
        }

        $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);

        // === 3. Count total entries matching criteria ===
        $count_sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}swpfe_entries {$where_sql}",
            ...$args
        );

        $total_entries = (int) $wpdb->get_var($count_sql);

        // === 4. If large dataset, queue batches with Action Scheduler ===
        // if ($total_entries > 5000) {
        //     Helper::queue_export_batches($form_id, $date_from, $date_to, $exclude_fields, $batch_size);
        //     return rest_ensure_response([
        //         'message'       => __('Export queued for asynchronous processing.', 'advanced-entries-manager-for-wpforms'),
        //         'total_entries' => $total_entries,
        //         'batch_size'    => $batch_size,
        //     ]);
        // }

        // === 5. Prepare and run query for current batch ===
        $sql = $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}swpfe_entries {$where_sql} ORDER BY id ASC LIMIT %d",
            ...array_merge($args, [$batch_size])
        );

        $results = $wpdb->get_results($sql);

        if (empty($results)) {
            error_log('[AEM Export] No results found for export. SQL: ' . $sql);
            wp_send_json_error(
                ['message' => __('No entries found to export.', 'advanced-entries-manager-for-wpforms')],
                404
            );
        }

        // === 6. Prepare CSV data ===
        $csv_data   = [];
        $csv_header = [];

        foreach ($results as $row) {
            $entry_data = maybe_unserialize($row->entry, true);
            if (is_string($entry_data)) {
                $entry_data = maybe_unserialize($entry_data, true);
            }

            if (! is_array($entry_data)) {
                error_log("Unserialization failed for row ID: {$row->id}, raw entry: {$row->entry}");
            }

            $entry_row = [
                'id'               => $row->id,
                'form_id'          => $row->form_id,
                'created_at'       => $row->created_at,
                'status'           => $row->status,
                'is_favorite'      => $row->is_favorite,
                'note'             => $row->note,
                'synced_to_gsheet' => $row->synced_to_gsheet,
                'printed_at'       => $row->printed_at,
                'is_spam'          => $row->is_spam,
                'resent_at'        => $row->resent_at,
                'updated_at'       => $row->updated_at,
            ];

            if (is_array($entry_data)) {
                $entry_row = array_merge($entry_row, $entry_data);
            }

            if (! empty($exclude_fields)) {
                foreach ($exclude_fields as $exclude) {
                    unset($entry_row[$exclude]);
                }
            }

            if (empty($csv_header)) {
                $csv_header = array_keys($entry_row);
            }

            $csv_data[] = $entry_row;
        }

        // === 7. Output CSV ===
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="exported_entries.csv"');

        $fh = fopen('php://output', 'w');
        fputcsv($fh, $csv_header);

        foreach ($csv_data as $row) {
            $line = [];
            foreach ($csv_header as $col) {
                $line[] = $row[$col] ?? '';
            }
            fputcsv($fh, $line);
        }

        fclose($fh);
        exit;
    }
}
