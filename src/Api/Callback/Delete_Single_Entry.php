<?php

namespace App\AdvancedEntryManager\Api\Callback;

use App\AdvancedEntryManager\Api\Route;
use App\AdvancedEntryManager\Utility\Helper;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Class Delete_Single_Entry
 *
 * Handles the retrieval of entries from the custom database table.
 */
class Delete_Single_Entry {
    /**
     * Delete a specific form entry.
     *
     * Handles a REST API request to delete a single entry from the custom entries table
     * based on its entry ID and form ID. Returns a success or failure response.
     *
     * Example request: DELETE /wp-json/your-namespace/v1/entries?id=123&form_id=45
     *
     * @param WP_REST_Request $request REST request object containing 'id' and 'form_id'.
     *
     * @global wpdb $wpdb WordPress database abstraction object.
     *
     * @return WP_REST_Response JSON response indicating success or failure:
     *                          - deleted (bool)
     *                          - message (string, optional)
     */
    public function delete_entry(WP_REST_Request $request)
    {
        global $wpdb;

        $id      = absint($request->get_param('id'));
        $form_id = absint($request->get_param('form_id'));

        if (! $id || ! $form_id) {
            return new WP_REST_Response([
                'deleted' => false,
                'message' => __('Missing required parameters.', 'advanced-entries-manager-for-wpforms'),
            ], 400);
        }

        if (! current_user_can('manage_options')) {
            return new WP_REST_Response([
                'deleted' => false,
                'message' => __('You are not allowed to delete entries.', 'advanced-entries-manager-for-wpforms'),
            ], 403);
        }

        do_action('swpfe_before_entry_delete', $id, $form_id);

        $table = Helper::get_table_name(); // e.g., 'aemfw_entries_manager'
        $deleted = $wpdb->delete(
            $table,
            ['id' => $id, 'form_id' => $form_id],
            ['%d', '%d']
        );

        if ($deleted) {
            do_action('swpfe_after_entry_delete', $id, $form_id);

            return new WP_REST_Response(['deleted' => true], 200);
        }

        return new WP_REST_Response([
            'deleted' => false,
            'message' => __('Entry not found or already deleted.', 'advanced-entries-manager-for-wpforms'),
        ], 404);
    }
}