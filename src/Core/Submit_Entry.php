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

/**
 * Class Submit_Entry
 *
 * Handles saving WPForms entries to a custom database table.
 */
class Submit_Entry {
	public function __construct() {
		// WPForms Hook
		add_action( 'wpforms_process_entry_save', array( $this, 'save_entry_from_wpforms' ), 10, 3 );

		// Contact Form 7 Hook
		if ( class_exists( 'WPCF7_Submission' ) ) {
			add_action( 'wpcf7_mail_sent', array( $this, 'save_entry_from_cf7' ), 10, 1 );
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
		$table = Helper::get_table_name();

		$name            = '';
		$email           = '';
		$serialized_data = array();

		foreach ( $fields as $field ) {
			if ( ! empty( $field['type'] ) && $field['type'] === 'name' ) {
				$first = $field['first'] ?? '';
				$last  = $field['last'] ?? '';
				$name  = trim( $first . ' ' . $last );
			}

			if ( ! empty( $field['type'] ) && $field['type'] === 'email' ) {
				$email = $field['value'] ?? '';
			}

			$value                             = is_array( $field['value'] ) ? implode( ',', $field['value'] ) : $field['value'];
			$serialized_data[ $field['name'] ] = $value;
		}

		$wpdb->insert(
			$table,
			array(
				'form_id'    => absint( $form_id ),
				'name'       => sanitize_text_field( $name ),
				'email'      => sanitize_email( $email ),
				'entry'      => maybe_serialize( $serialized_data ),
				'status'     => 'unread',
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Handles CF7 entries
	 *
	 * @param WPCF7_ContactForm $contact_form The CF7 form instance.
	 */
	public function save_entry_from_cf7( $contact_form ) {
		global $wpdb;
		$table      = Helper::get_table_name();
		$submission = \WPCF7_Submission::get_instance();

		if ( ! $submission ) {
			// Exit if submission object is not available
			return;
		}

		$posted_data    = $submission->get_posted_data();
		$uploaded_files = $submission->uploaded_files();

		// Security: Check for a valid CF7 nonce before proceeding.
		// This is a crucial line for security.
		if ( ! isset( $posted_data['_wpcf7_unit_tag'] ) || ! wp_verify_nonce( $posted_data['_wpcf7_unit_tag'], 'wpcf7-form' ) ) {
			return;
		}

		$form_id    = absint( $contact_form->id() );
		$name       = '';
		$email      = '';
		$entry_data = array();

		// Sanitize and map data.
		foreach ( $posted_data as $key => $value ) {
			if ( $key === 'your-name' ) {
				$name = sanitize_text_field( $value );
			} elseif ( $key === 'your-email' ) {
				$email = sanitize_email( $value );
			}

			// Exclude system fields from entry data
			if ( strpos( $key, '_wpcf7' ) === false ) {
				if ( is_array( $value ) ) {
					$entry_data[ $key ] = array_map( 'sanitize_text_field', $value );
				} else {
					$entry_data[ $key ] = sanitize_text_field( $value );
				}
			}
		}

		// Handle file uploads separately and securely.
		if ( ! empty( $uploaded_files ) ) {
			foreach ( $uploaded_files as $field_name => $file_paths ) {
				// Ensure field_name exists in entry data for consistency.
				if ( ! isset( $entry_data[ $field_name ] ) ) {
					$entry_data[ $field_name ] = array();
				}

				$file_paths = (array) $file_paths;
				foreach ( $file_paths as $file_path ) {
					if ( file_exists( $file_path ) ) {
						// Securely copy the file to a private directory.
						$upload_dir  = wp_upload_dir();
						$private_dir = $upload_dir['basedir'] . '/aem-cf7-uploads';
						if ( ! is_dir( $private_dir ) ) {
							wp_mkdir_p( $private_dir );
						}

						$new_filename = sanitize_file_name( basename( $file_path ) );
						$new_path     = trailingslashit( $private_dir ) . $new_filename;

						// Prevent filename collisions
						$i = 1;
						while ( file_exists( $new_path ) ) {
							$path_info = pathinfo( $new_filename );
							$new_path  = trailingslashit( $private_dir ) . $path_info['filename'] . '-' . $i++ . '.' . $path_info['extension'];
						}

						// Copy the file
						if ( copy( $file_path, $new_path ) ) {
							$entry_data[ $field_name ][] = array(
								'filename' => basename( $new_path ),
								'path'     => $new_path,
							);
						}
					}
				}
			}
		}

		// Insert into database
		$wpdb->insert(
			$table,
			array(
				'form_id'    => $form_id,
				'name'       => $name,
				'email'      => $email,
				'entry'      => maybe_serialize( $entry_data ),
				'status'     => 'unread',
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s' )
		);
	}
}
