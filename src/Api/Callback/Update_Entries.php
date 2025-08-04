<?php

namespace App\AdvancedEntryManager\Api\Callback;

use App\AdvancedEntryManager\Api\Route;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use App\AdvancedEntryManager\Utility\Helper;

/**
 * Class Update_Entries
 *
 * Handles the retrieval of entries from the custom database table.
 */
class Update_Entries {
    /**
     * Update an existing WPForms entry row.
     *
     * Supports PATCH-style partial updates or full PUT updates.
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response
     */
    public function update_entries(WP_REST_Request $request)
    {
        global $wpdb;
        $table = Helper::get_table_name();

        $params = $request->get_json_params();

        // Require entry ID and form ID, sanitize
        $id = isset($params['id']) ? absint($params['id']) : 0;
        $form_id = isset($params['form_id']) ? absint($params['form_id']) : 0;

        if (!$id || !$form_id) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Missing or invalid entry ID or form ID.', 'advanced-entries-manager-for-wpforms'),
            ], 400);
        }

        // Build update data only from present fields
        $data = [];
        $format = [];

        if (isset($params['entry']) && is_array($params['entry'])) {
            $data['entry'] = maybe_serialize($params['entry']);
            $format[] = '%s';
        }

        if (isset($params['status'])) {
            $data['status'] = sanitize_text_field($params['status']);
            $format[] = '%s';
        }

        if (isset($params['is_favorite'])) {
            $data['is_favorite'] = absint($params['is_favorite']);
            $format[] = '%d';
        }

        if (isset($params['note'])) {
            $raw_note = sanitize_textarea_field($params['note']);

            // Limit character length (hard limit for DB and performance)
            $max_length = 1000;
            $trimmed_note = mb_substr($raw_note, 0, $max_length);

            $data['note'] = $trimmed_note;
            $format[] = '%s';
        }

        if (isset($params['exported_to_csv'])) {
            $data['exported_to_csv'] = absint($params['exported_to_csv']);
            $format[] = '%d';
        }

        if (isset($params['synced_to_gsheet'])) {
            $data['synced_to_gsheet'] = absint($params['synced_to_gsheet']);
            $format[] = '%d';
        }

        if (isset($params['printed_at'])) {
            $data['printed_at'] = date('Y-m-d H:i:s', strtotime($params['printed_at']));
            $format[] = '%s';
        }

        if (isset($params['resent_at'])) {
            $data['resent_at'] = date('Y-m-d H:i:s', strtotime($params['resent_at']));
            $format[] = '%s';
        }

        // If no fields provided to update
        if (empty($data)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('No valid fields provided for update.', 'advanced-entries-manager-for-wpforms'),
            ], 400);
        }

        /**
         * Fires before an entry update is performed.
         *
         * @param int             $id      Entry ID.
         * @param array           $data    Data to update (column => value).
         * @param WP_REST_Request $request Full REST request object.
         */
        do_action('swpfe_before_entry_update', $id, $data, $request);

        // Perform DB update
        $updated = $wpdb->update(
            $table,
            $data,
            ['id' => $id],
            $format,
            ['%d']
        );

        if ($updated === false) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Database update failed.', 'advanced-entries-manager-for-wpforms'),
            ], 500);
        }

        /**
         * Fires after an entry has been successfully updated.
         *
         * @param int             $id      Entry ID.
         * @param array           $data    Data that was updated.
         * @param WP_REST_Request $request Full REST request object.
         */
        do_action('swpfe_after_entry_update', $id, $data, $request);

        return new WP_REST_Response([
            'success'        => true,
            'message'        => __('Entry updated successfully.', 'advanced-entries-manager-for-wpforms'),
            'updated_fields' => array_keys($data),
            'entry_id'       => $id,
        ], 200);
    }
}