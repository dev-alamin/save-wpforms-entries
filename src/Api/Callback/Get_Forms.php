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
        return rest_ensure_response(apply_filters('aemfw_get_forms', $forms));
    }

    /**
     * Retrieve a list of unique form field keys for a given WPForms form ID.
     *
     * This endpoint is used to dynamically fetch the field names from a sample
     * entry, typically to allow users to customize export settings (e.g., include/exclude columns).
     *
     * ## Example Request:
     * GET /wp-json/aemfw/v1/form-fields?form_id=123
     *
     * @param WP_REST_Request $request The REST request object containing 'form_id'.
     *
     * @return WP_REST_Response|WP_Error List of field keys or a WP_Error on failure.
     */
    public function get_form_fields( WP_REST_Request $request ) {
        global $wpdb;

        $form_id = isset( $request['form_id'] ) ? absint( $request['form_id'] ) : 0;

        if ( $form_id <= 0 ) {
            return new WP_Error(
                'aemfw_invalid_form_id',
                __( 'Invalid or missing form ID.', 'advanced-entries-manager-for-wpforms' ),
                [ 'status' => 400 ]
            );
        }

        $table = Helper::get_table_name();

        // Fetch a few rows to detect fields (faster than scanning all)
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE form_id = %d LIMIT 5",
                $form_id
            ),
            ARRAY_A
        );

        $allowed_fields = ['id', 'form_id', 'email', 'note', 'created_at', 'status', 'is_favorite'];
        $fields = [];

        foreach ( $rows as $row ) {
            // Step 1: Add only allowed top-level DB columns
            foreach ( $allowed_fields as $column ) {
                if ( array_key_exists( $column, $row ) ) {
                    $fields[ $column ] = true;
                }
            }

            // Step 2: Merge in keys from deserialized 'entry'
            if ( isset( $row['entry'] ) ) {
                $entry = maybe_unserialize( $row['entry'] );
                if ( is_array( $entry ) ) {
                    foreach ( array_keys( $entry ) as $field_key ) {
                        $fields[ $field_key ] = true;
                    }
                }
            }
        }

        return rest_ensure_response([
            'fields' => array_values( array_unique( array_keys( $fields ) ) )
        ]);
    }
}