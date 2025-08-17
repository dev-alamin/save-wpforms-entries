<?php
/**
 * Submit_Entry Class
 *
 * Captures and saves WPForms form entries into a custom database table
 * for further processing, display, or external integration.
 *
 * This class hooks into the `wpforms_process_entry_save` action to extract
 * submitted form data, serialize it, and insert it into a custom table managed
 * by the plugin.
 *
 * @package    Save_WPForms_Entries
 * @subpackage Entry_Storage
 * @author     Al Amin
 * @since      1.0.0
 */

namespace App\AdvancedEntryManager\Core;

use App\AdvancedEntryManager\Utility\Helper;
use App\AdvancedEntryManager\GoogleSheet\Send_Data;

/**
 * Class Submit_Entry
 *
 * Handles saving WPForms entries to a custom database table.
 */
class Submit_Entry {
    public function __construct() {
        add_action( 'wpforms_process_entry_save', [ $this, 'save_entry_to_custom_table' ], 10, 3 );
    }

    /**
     * Extracts all data, including name and email, and saves the complete
     * entry into the custom table in a single operation.
     *
     * @param array $fields The fields submitted in the form.
     * @param array $entry The entry data from WPForms.
     * @param int   $form_id The ID of the form being submitted.
     */
    public function save_entry_to_custom_table( $fields, $entry, $form_id ) {
        global $wpdb;

        $table = Helper::get_table_name();

        $name  = '';
        $email = '';
        $serialized_data = [];

        foreach ( $fields as $field ) {
            // --- Logic to extract Name and Email ---
            if ( ! empty( $field['type'] ) && $field['type'] === 'name' ) {
                $first = $field['first'] ?? '';
                $last  = $field['last'] ?? '';
                $name  = trim( $first . ' ' . $last );
            }
            
            if ( ! empty( $field['type'] ) && $field['type'] === 'email' ) {
                $email = $field['value'] ?? '';
            }

            // --- Logic to build the serialized data array ---
            $value = is_array( $field['value'] ) ? implode(',', $field['value']) : $field['value'];
            $serialized_data[ $field['name'] ] = $value;
        }

        // Insert all data into the custom table in one query
        $wpdb->insert( $table, [
            'form_id'    => $form_id,
            'name'       => $name,
            'email'      => $email,
            'entry'      => maybe_serialize( $serialized_data ),
            'status'     => 'unread',
            'created_at' => current_time( 'mysql' ),
        ] );
        
        $last_inserted_id = $wpdb->insert_id;

        Helper::set_error_log( $fields );
    }
}