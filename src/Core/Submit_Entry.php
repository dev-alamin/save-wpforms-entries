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
use WPCF7_Submission;
use App\AdvancedEntryManager\Logger\FileLogger;
use WPCF7_ContactForm;
use App\AdvancedEntryManager\Core\DB_Schema;

/**
 * Class Submit_Entry
 *
 * Handles saving form entries to custom database tables.
 */
class Submit_Entry {
    protected $logger;

    public function __construct() {
        $this->logger = new FileLogger( 'submit_entry.log' );
        // WPForms Hook
        add_action( 'wpforms_process_entry_save', array( $this, 'save_entry_from_wpforms' ), 10, 3 );
        // Contact Form 7 Hook
        if ( class_exists( 'WPCF7_Submission' ) ) {
            add_action( 'wpcf7_before_send_mail', array( $this, 'save_entry_from_cf7' ), 10, 1 );
        }
    }

    /**
     * Handles WPForms entries.
     *
     * @param array $fields The fields submitted in the form.
     * @param array $entry The entry data from WPForms.
     * @param int   $form_id The ID of the form being submitted.
     */
    public function save_entry_from_wpforms( $fields, $entry, $form_id ) {
        global $wpdb;

        $submissions_table = DB_Schema::submissions_table();
        $entries_table     = DB_Schema::entries_table();
        $name              = '';
        $email             = '';

        // Extract name and email for the submissions table.
        foreach ( $fields as $field_id => $field_data ) {
            if ( ! empty( $field_data['type'] ) ) {
                if ( $field_data['type'] === 'name' ) {
                    $first = $field_data['first'] ?? '';
                    $last  = $field_data['last'] ?? '';
                    $name  = trim( $first . ' ' . $last );
                } elseif ( $field_data['type'] === 'email' ) {
                    $email = $field_data['value'] ?? '';
                }
            }
        }

        // Insert into the submissions table first.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->insert(
            $submissions_table,
            array(
                'form_id'    => absint( $form_id ),
                'name'       => sanitize_text_field( $name ),
                'email'      => sanitize_email( $email ),
                'status'     => 'unread',
                'created_at' => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%s', '%s' )
        );

        $submission_id = $wpdb->insert_id;

        if ( ! $submission_id ) {
            $this->logger->log( 'Failed to insert into submissions table.', 'error' );
            return;
        }

        // Insert individual fields into the entries table.
        foreach ( $fields as $field_id => $field_data ) {
            $field_key   = isset( $field_data['name'] ) ? $field_data['name'] : 'field_' . $field_id;
            $field_value = is_array( $field_data['value'] ) ? implode( ', ', array_map( 'sanitize_text_field', $field_data['value'] ) ) : sanitize_text_field( $field_data['value'] );

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $wpdb->insert(
                $entries_table,
                array(
                    'submission_id' => $submission_id,
                    'field_key'     => $field_key,
                    'field_value'   => $field_value,
                    'created_at'    => current_time( 'mysql' ),
                ),
                array( '%d', '%s', '%s', '%s' )
            );
        }

        // Send data to Google Sheets if enabled.
        // $send_data = new Send_Data();
        // $send_data->process_single_entry( array( 'entry_id' => $submission_id ) );

        // Invalidate cached form fields and forms list.
        Helper::delete_option( 'forms_cache' );
    }

    /**
     * Handles CF7 entries.
     *
     * @param WPCF7_ContactForm $contact_form The CF7 form instance.
     */
    public function save_entry_from_cf7( WPCF7_ContactForm $contact_form ) {
        $this->logger->log( 'CF7 save_entry_from_cf7 hook triggered.', 'info' );
        global $wpdb;

        $submissions_table = DB_Schema::submissions_table();
        $entries_table     = DB_Schema::entries_table();
        $submission        = WPCF7_Submission::get_instance();

        if ( ! $submission ) {
            $this->logger->log( 'No submission instance found.', 'error' );
            return;
        }

        $posted_data = $submission->get_posted_data();

        // Security check.
        if ( ! isset( $posted_data['_wpcf7_unit_tag'] ) || ! wp_verify_nonce( $posted_data['_wpcf7_unit_tag'], 'wpcf7-form' ) ) {
            $this->logger->log( 'Invalid nonce in CF7 submission.', 'error' );
            // return; // Need to handle later
        }



        $form_id = absint( $contact_form->id() );
        $name    = '';
        $email   = '';

        // Extract name and email for the submissions table.
        foreach ( $contact_form->scan_form_tags() as $tag ) {
            if ( $tag->basetype === 'text' && str_contains( $tag->name, 'name' ) ) {
                $name = ! empty( $posted_data[ $tag->name ] ) ? sanitize_text_field( $posted_data[ $tag->name ] ) : '';
            }
            if ( $tag->basetype === 'email' ) {
                $email = ! empty( $posted_data[ $tag->name ] ) ? sanitize_email( $posted_data[ $tag->name ] ) : '';
            }
        }

        // Insert into the submissions table first.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->insert(
            $submissions_table,
            array(
                'form_id'    => $form_id,
                'name'       => $name,
                'email'      => $email,
                'status'     => 'unread',
                'created_at' => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%s', '%s' )
        );

        $submission_id = $wpdb->insert_id;

        if ( ! $submission_id ) {
            $this->logger->log( 'Failed to insert into submissions table.', 'error' );
            return;
        }

        $this->logger->log( print_r( $posted_data, true ), 'info' );

        // Insert individual fields into the entries table.
        foreach ( $posted_data as $key => $value ) {
            // Exclude system fields.
            if ( strpos( $key, '_wpcf7' ) === false ) {
                $field_value = is_array( $value ) ? implode( ', ', array_map( 'sanitize_text_field', $value ) ) : sanitize_text_field( $value );

                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
                $wpdb->insert(
                    $entries_table,
                    array(
                        'submission_id' => $submission_id,
                        'field_key'     => $key,
                        'field_value'   => $field_value,
                        'created_at'    => current_time( 'mysql' ),
                    ),
                    array( '%d', '%s', '%s', '%s' )
                );
            }
        }

        // Handle file uploads.
        $uploaded_files = $submission->uploaded_files();
        if ( ! empty( $uploaded_files ) ) {
            foreach ( $uploaded_files as $field_name => $file_paths ) {
                $file_paths = (array) $file_paths;
                $file_list  = [];
                foreach ( $file_paths as $file_path ) {
                    if ( file_exists( $file_path ) ) {
                        $upload_dir  = wp_upload_dir();
                        $private_dir = $upload_dir['basedir'] . '/fem-cf7-uploads';
                        if ( ! is_dir( $private_dir ) ) {
                            wp_mkdir_p( $private_dir );
                        }

                        $new_filename = sanitize_file_name( basename( $file_path ) );
                        $new_path     = trailingslashit( $private_dir ) . $new_filename;

                        $i = 1;
                        while ( file_exists( $new_path ) ) {
                            $path_info  = pathinfo( $new_filename );
                            $new_path   = trailingslashit( $private_dir ) . $path_info['filename'] . '-' . $i++ . '.' . $path_info['extension'];
                        }

                        if ( copy( $file_path, $new_path ) ) {
                            $file_list[] = basename( $new_path );
                        }
                    }
                }
                if ( ! empty( $file_list ) ) {
                    $file_value = implode( ', ', $file_list );
                    // Insert file information into entries table.
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
                    $wpdb->insert(
                        $entries_table,
                        array(
                            'submission_id' => $submission_id,
                            'field_key'     => $field_name,
                            'field_value'   => 'files: ' . $file_value, // Prefix to indicate it's a file.
                            'created_at'    => current_time( 'mysql' ),
                        ),
                        array( '%d', '%s', '%s', '%s' )
                    );
                }
            }
        }

        // Send data to Google Sheets if enabled.
        // $send_data = new Send_Data();
        // $send_data->process_single_entry( array( 'entry_id' => $submission_id ) );

        // Invalidate cached form fields and forms list.
        Helper::delete_option( 'forms_cache' );
    }
}