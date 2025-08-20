<?php

namespace App\AdvancedEntryManager\Api\Callback;

defined('ABSPATH') || exit;

use App\AdvancedEntryManager\Utility\Helper;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Class Bulk_Action
 *
 * Handles the retrieval of forms from the custom database table.
 */
class Bulk_Action
{
    /**
     * Handle bulk actions on entries.
     *
     * This endpoint processes bulk operations like marking as read/unread,
     * favoriting/unfavoriting, and deleting multiple WPForms entries.
     *
     * @since 1.0.0
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response JSON response indicating success or failure.
     */
    public function bulk_actions(WP_REST_Request $request)
    {
        $ids = array_unique(array_map('absint', $request->get_param('ids')));
        $action = sanitize_text_field($request->get_param('action'));

        // Validate IDs
        if (!is_array($ids) || empty($ids)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Invalid or missing entry IDs.', 'advanced-entries-manager-for-wpforms'),
            ], 400);
        }

        // Sanitize each ID
        $ids = array_map('absint', $ids);

        // Validate action
        $valid_actions = ['delete', 'mark_read', 'mark_unread', 'favorite', 'unfavorite', 'mark_spam', 'unmark_spam'];
        if (!in_array($action, $valid_actions, true)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Invalid action provided.', 'advanced-entries-manager-for-wpforms'),
            ], 400);
        }

        global $wpdb;
        $table = Helper::get_table_name();

        $affected = 0;
        $deleted_ids = [];
        $updated_ids = [];

        foreach ($ids as $id) {
            switch ($action) {
                case 'delete':
                    $deleted = $wpdb->delete($table, ['id' => $id]);
                    if ($deleted !== false) {
                        $affected++;
                        $deleted_ids[] = $id;
                    }
                    break;

                case 'mark_read':
                    $updated = $wpdb->update($table, ['status' => 'read'], ['id' => $id]);
                    if ($updated !== false) {
                        $affected++;
                        $updated_ids[] = $id;
                    }
                    break;

                case 'mark_unread':
                    $updated = $wpdb->update($table, ['status' => 'unread'], ['id' => $id]);
                    if ($updated !== false) {
                        $affected++;
                        $updated_ids[] = $id;
                    }
                    break;

                case 'favorite':
                    $updated = $wpdb->update($table, ['is_favorite' => 1], ['id' => $id]);
                    if ($updated !== false) {
                        $affected++;
                        $updated_ids[] = $id;
                    }
                    break;

                case 'unfavorite':
                    $updated = $wpdb->update($table, ['is_favorite' => 0], ['id' => $id]);
                    if ($updated !== false) {
                        $affected++;
                        $updated_ids[] = $id;
                    }
                    break;

                case 'mark_spam':
                    $updated = $wpdb->update($table, ['is_spam' => 1], ['id' => $id]);
                    if ($updated !== false) {
                        $affected++;
                        $updated_ids[] = $id;
                    }
                    break;

                case 'unmark_spam':
                    $updated = $wpdb->update($table, ['is_spam' => 0], ['id' => $id]);
                    if ($updated !== false) {
                        $affected++;
                        $updated_ids[] = $id;
                    }
                    break;
            }
        }

        return rest_ensure_response([
            'success' => true,
            'message' => $action === 'delete'
                ? sprintf(_n('%d entry deleted.', '%d entries deleted.', count($deleted_ids), 'advanced-entries-manager-for-wpforms'), count($deleted_ids))
                : sprintf(_n('%d entry updated.', '%d entries updated.', count($updated_ids), 'advanced-entries-manager-for-wpforms'), count($updated_ids)),
            'deleted_ids' => $deleted_ids,
            'updated_ids' => $updated_ids,
            'affected' => $affected,
        ]);
    }

    /**
     * Export selected WPForms entries as a CSV file download.
     *
     * This method handles a REST API POST request, expecting an array of entry IDs 
     * under the 'ids' parameter. It fetches entries from the custom `aemfw_entries_manager` table,
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
        global $wpdb;
        $ids = $request->get_param('ids');

        if (empty($ids) || !is_array($ids)) {
            return new \WP_Error('invalid_data', __('No entries selected.', 'advanced-entries-manager-for-wpforms'), ['status' => 400]);
        }

        // Prepare placeholders for SQL IN clause
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $table = Helper::get_table_name(); // e.g., 'aemfw_entries_manager'
        $query = "SELECT * FROM {$table} WHERE id IN ($placeholders)";
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
}
