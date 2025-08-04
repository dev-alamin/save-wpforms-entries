<?php

namespace App\AdvancedEntryManager\Api\Callback;

use App\AdvancedEntryManager\Api\Route;
use App\AdvancedEntryManager\Utility\Helper;
use ParagonIE\Sodium\Core\Curve25519\H;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Class Get_Form_Fields
 *
 * Handles the retrieval of forms from the custom database table.
 */
class Get_Form_Fields {
    /**
     * Retrieve a list of unique form field keys for a given WPForms form ID.
     *
     * This endpoint is used to dynamically fetch the field names from a sample
     * entry, typically to allow users to customize export settings (e.g., include/exclude columns).
     *
     * ## Example Request:
     * GET /wp-json/swpfe/v1/form-fields?form_id=123
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
                'swpfe_invalid_form_id',
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

        $fields = [];

        foreach ( $rows as $row ) {
            // Step 1: Add all top-level DB columns
            foreach ( array_keys( $row ) as $column ) {
                $fields[ $column ] = true;
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