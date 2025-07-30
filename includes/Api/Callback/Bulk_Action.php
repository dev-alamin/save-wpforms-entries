<?php

namespace App\AdvancedEntryManger\Api\Callback;

use App\AdvancedEntryManger\Api\Route;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Class Bulk_Action
 *
 * Handles the retrieval of forms from the custom database table.
 */
class Bulk_Action {
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
        $ids = $request->get_param('ids');
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
        $table = $wpdb->prefix . 'swpfe_entries';

        $affected = 0;

        foreach ($ids as $id) {
            switch ($action) {
                case 'delete':
                    $deleted = $wpdb->delete($table, ['id' => $id]);
                    if ($deleted !== false) {
                        $affected++;
                    }
                    break;

                case 'mark_read':
                    $updated = $wpdb->update($table, ['status' => 'read'], ['id' => $id]);
                    if ($updated !== false) {
                        $affected++;
                    }
                    break;

                case 'mark_unread':
                    $updated = $wpdb->update($table, ['status' => 'unread'], ['id' => $id]);
                    if ($updated !== false) {
                        $affected++;
                    }
                    break;

                case 'favorite':
                    $updated = $wpdb->update($table, ['is_favorite' => 1], ['id' => $id]);
                    if ($updated !== false) {
                        $affected++;
                    }
                    break;

                case 'unfavorite':
                    $updated = $wpdb->update($table, ['is_favorite' => 0], ['id' => $id]);
                    if ($updated !== false) {
                        $affected++;
                    }
                    break;

                case 'mark_spam':
                    $updated = $wpdb->update($table, ['is_spam' => 1], ['id' => $id]);
                    break;

                case 'unmark_spam':
                    $updated = $wpdb->update($table, ['is_spam' => 0], ['id' => $id]);
                    break;
            }
        }

        return rest_ensure_response([
            'success' => true,
            'message' => sprintf(
                // translators: %d is number of affected entries
                _n('%d entry updated.', '%d entries updated.', $affected, 'advanced-entries-manager-for-wpforms'),
                $affected
            ),
            'affected' => $affected,
        ]);
    }
}