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
 */class Submit_Entry {
    public function __construct() {
        // WPForms
        add_action( 'wpforms_process_entry_save', [ $this, 'save_entry_from_wpforms' ], 10, 3 );

        // Contact Form 7
        add_action( 'wpcf7_before_send_mail', [ $this, 'save_entry_from_cf7' ], 10, 3 );
    }

    /**
     * Handles WPForms entries
     */
    public function save_entry_from_wpforms( $fields, $entry, $form_id ) {
        global $wpdb;
        $table = Helper::get_table_name();

        $name  = '';
        $email = '';
        $serialized_data = [];

        foreach ( $fields as $field ) {
            if ( $field['type'] === 'name' ) {
                $first = $field['first'] ?? '';
                $last  = $field['last'] ?? '';
                $name  = trim( $first . ' ' . $last );
            }

            if ( $field['type'] === 'email' ) {
                $email = $field['value'] ?? '';
            }

            $value = is_array( $field['value'] ) ? implode(',', $field['value']) : $field['value'];
            $serialized_data[ $field['name'] ] = $value;
        }

        $wpdb->insert( $table, [
            'form_id'    => $form_id,
            'name'       => $name,
            'email'      => $email,
            'entry'      => maybe_serialize( $serialized_data ),
            'status'     => 'unread',
            'created_at' => current_time( 'mysql' ),
        ] );
    }

    /**
     * Handles CF7 entries
     */
    public function save_entry_from_cf7( $contact_form, &$abort, $submission ) {
        global $wpdb;
        $table = Helper::get_table_name();

        $submission = \WPCF7_Submission::get_instance();
        if ( ! $submission ) {
            return;
        }

        $posted_data    = $submission->get_posted_data();
        $uploaded_files = $submission->uploaded_files();

        $form_id = absint( $contact_form->id() );

        // Sanitize specific known fields (fallback if not present)
        $name  = isset( $posted_data['your-name'] ) ? sanitize_text_field( $posted_data['your-name'] ) : '';
        $email = isset( $posted_data['your-email'] ) ? sanitize_email( $posted_data['your-email'] ) : '';

        // Sanitize all posted fields
        $serialized_data = [];
        foreach ( $posted_data as $key => $value ) {
            $clean_key = sanitize_key( $key );

            if ( is_array( $value ) ) {
                $clean_value = array_map( 'sanitize_text_field', $value );
                $serialized_data[ $clean_key ] = implode( ',', $clean_value );
            } else {
                $serialized_data[ $clean_key ] = sanitize_text_field( $value );
            }
        }

        if ( ! empty( $uploaded_files ) ) {
            foreach ( $uploaded_files as $file_key => $file_paths ) {

                // Always treat as array for consistency
                $file_paths = (array) $file_paths;

                foreach ( $file_paths as $file_path ) {
                    if ( ! empty( $file_path ) && is_string( $file_path ) ) {

                        // Move file into Media Library
                        $file_id = media_handle_sideload(
                            [
                                'name'     => basename( $file_path ),
                                'tmp_name' => $file_path,
                            ],
                            0
                        );

                        if ( ! isset( $serialized_data[ $file_key ] ) || ! is_array( $serialized_data[ $file_key ] ) ) {
                            $serialized_data[ $file_key ] = [];
                        }

                        $serialized_data[ $file_key ][] = [
                            'id'  => $file_id,
                            'url' => wp_get_attachment_url( $file_id ),
                        ];

                    }
                }
            }
        }

        // Insert into DB
        $wpdb->insert(
            $table,
            [
                'form_id'    => $form_id,
                'name'       => $name,
                'email'      => $email,
                'entry'      => maybe_serialize( $serialized_data ),
                'status'     => 'unread',
                'created_at' => current_time( 'mysql' ),
            ],
            [ '%d', '%s', '%s', '%s', '%s', '%s' ]
        );

        // Log the new entry ID
        $last_inserted_id = $wpdb->insert_id;
        Helper::set_error_log( 'CF7 Entry saved with ID: ' . $last_inserted_id );

        // Helper::set_error_log( print_r( $posted_data, true ) );
    }
}
