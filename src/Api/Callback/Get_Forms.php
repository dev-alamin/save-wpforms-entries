<?php

namespace App\AdvancedEntryManager\Api\Callback;

use App\AdvancedEntryManager\Api\Route;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use App\AdvancedEntryManager\Utility\Helper;

/**
 * Class Get_Forms
 *
 * Handles the retrieval of forms from the custom database table.
 */
class Get_Forms {
    /**
     * Get list of forms with their entry counts.
     *
     * Queries the custom entries table to retrieve all unique form IDs and
     * the number of entries associated with each form. Also fetches the form
     * title using `get_the_title()`. The result is formatted as a REST response.
     *
     * @global wpdb $wpdb WordPress database abstraction object.
     *
     * @return WP_REST_Response JSON-formatted response containing form data:
     *                          - form_id (int)
     *                          - form_title (string)
     *                          - entry_count (int)
     */
    public function get_forms()
    {
        global $wpdb;
        $table = Helper::get_table_name(); // e.g., 'aemfw_entries_manager'

        // Query distinct form IDs and their entry counts
        $results = $wpdb->get_results(
            "SELECT form_id, COUNT(*) as entry_count 
            FROM {$table} 
            GROUP BY form_id",
            OBJECT
        );

        $forms = [];

        foreach ($results as $row) {
            $form_id = (int) $row->form_id;

            $forms[] = [
                'form_id'     => $form_id,
                'form_title'  => get_the_title($form_id),
                'entry_count' => (int) $row->entry_count,
            ];
        }

        /**
         * Filter the list of forms returned by get_forms().
         *
         * @param array $forms List of forms with entry counts.
         */
        return rest_ensure_response(apply_filters('swpfe_get_forms', $forms));
    }
}