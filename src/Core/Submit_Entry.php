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
use App\AdvancedEntryManager\Logger\FileLogger;
use WPCF7_Submission;

/**
 * Class Submit_Entry
 *
 * Handles saving WPForms entries to a custom database table.
 */
class Submit_Entry {

	protected $logger;

	public function __construct() {
		// WPForms Hook
		$this->logger = new FileLogger();
		add_action( 'wpforms_process_entry_save', array( $this, 'save_entry_from_wpforms' ), 10, 3 );

		// Contact Form 7 Hook
		if ( class_exists( 'WPCF7_Submission' ) ) {
			add_action( 'wpcf7_before_send_mail', array( $this, 'save_entry_from_cf7' ), 10, 1 ); // In production, we will use 'wpcf7_mail_sent'
		}
	}

	/**
	 * Sends a completed WPForms entry to Google Sheets.
	 *
	 * @param array $fields Form fields and their values.
	 * @param array $entry_data Complete entry data.
	 * @param int   $entry_id ID of the entry saved in the database.
	 * @param int   $form_id ID of the form.
	 */
	public function send_entry_to_google_sheets( $entry_id ) {
		// Send data to Google Sheets if enabled
		$has_access_token = Helper::has_access_token();

		if ( $has_access_token ) {
			$send_data = new Send_Data();
			$send_data->process_single_entry( array( 'entry_id' => $entry_id ) );
		}
	}

	/**
	 * Handles WPForms entries by saving them to a relational database model.
	 *
	 * @param array $fields The fields submitted in the form.
	 * @param array $entry The entry data from WPForms.
	 * @param int   $form_id The ID of the form being submitted.
	 */
	public function save_entry_from_wpforms( $fields, $entry, $form_id ) {
		global $wpdb;

		// Table names must be defined or retrieved from a helper.
		$submissions_table = Helper::get_table_name();
		$data_table        = Helper::data_table();

		$name              = '';
		$email             = '';
		$additional_fields = array();

		foreach ( $fields as $field ) {
			// Find and separate the 'name' and 'email' fields.
			if ( ! empty( $field['type'] ) && $field['type'] === 'name' ) {
				$first = $field['first'] ?? '';
				$last  = $field['last'] ?? '';
				$name  = trim( $first . ' ' . $last );
			} elseif ( ! empty( $field['type'] ) && $field['type'] === 'email' ) {
				$email = $field['value'] ?? '';
			} else {
				// Collect all other fields for the separate data table.
				$additional_fields[ $field['name'] ] = is_array( $field['value'] ) ? implode( ',', $field['value'] ) : $field['value'];
			}
		}

		// Step 1: Insert core submission data into the main table.
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

		// Get the ID of the newly created submission.
		$submission_id = $wpdb->insert_id;

		// Step 2: Loop through remaining fields and insert each into the data table.
		if ( $submission_id && ! empty( $additional_fields ) ) {
			foreach ( $additional_fields as $field_key => $field_value ) {
				$wpdb->insert(
					$data_table,
					array(
						'submission_id' => $submission_id,
						'field_key'     => $field_key,
						'field_value'   => sanitize_text_field( $field_value ),
					),
					array( '%d', '%s', '%s' )
				);
			}
		}

		// Send data to Google Sheets if enabled.
		$this->send_entry_to_google_sheets( $submission_id );
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
			$this->logger->log( 'CF7 Submission instance not found.' );
			return;
		}
		$posted_data = $submission->get_posted_data();
		if ( ! isset( $posted_data['_wpcf7_unit_tag'] ) || ! wp_verify_nonce( $posted_data['_wpcf7_unit_tag'], 'wpcf7-form' ) ) {
			$this->logger->log( 'CF7 Nonce verification failed.' );
			// return;
		}
		$form_id    = absint( $contact_form->id() );
		$name       = '';
		$email      = '';
		$entry_data = array();

		// Dynamically build a list of keys to exclude.
		$excluded_keys = array();

		// First, find and store the name and email fields.
		$form_tags = $contact_form->scan_form_tags();
		foreach ( $form_tags as $tag ) {
			// Find name field using autocomplete attribute.
			if ( isset( $tag['options'][0] ) && strpos( $tag['options'][0], 'autocomplete:name' ) !== false ) {
				if ( isset( $posted_data[ $tag['name'] ] ) ) {
					$name            = sanitize_text_field( $posted_data[ $tag['name'] ] );
					$excluded_keys[] = $tag['name'];
				}
			}

			// Find email field using autocomplete attribute.
			if ( isset( $tag['options'][0] ) && strpos( $tag['options'][0], 'autocomplete:email' ) !== false ) {
				if ( isset( $posted_data[ $tag['name'] ] ) ) {
					$email           = sanitize_email( $posted_data[ $tag['name'] ] );
					$excluded_keys[] = $tag['name'];
				}
			}
		}

		// Add standard CF7 system fields to the exclusion list.
		$excluded_keys = array_merge(
			$excluded_keys,
			array(
				'_wpcf7_unit_tag',
				'_wpcf7_form_id',
				'_wpcf7_container_post',
				'_wpcf7_posted_data_hash',
				'_wpcf7_locale',
				'_wpcf7_akismet_comment_author',
				'_wpcf7_akismet_comment_author_email',
				'_wpcf7_akismet_comment_author_url',
			)
		);

		// Process the remaining fields.
		foreach ( $posted_data as $key => $value ) {
			if ( ! in_array( $key, $excluded_keys ) ) {
				if ( is_array( $value ) ) {
					$entry_data[ $key ] = array_map( 'sanitize_text_field', $value );
				} else {
					$entry_data[ $key ] = sanitize_text_field( $value );
				}
			}
		}

		// Handle uploaded files.
		$uploaded_files = $submission->uploaded_files();
		$this->upload_file( $uploaded_files, $entry_data );

		$wpdb->insert(
			Helper::get_table_name(), // Assumes you have a method `table()` that returns the submissions table name.
			array(
				'form_id'    => $form_id,
				'name'       => $name,
				'email'      => $email,
				'status'     => 'unread',
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s' )
		);

		// Get the ID of the new submission.
		$submission_id = $wpdb->insert_id;

		// Now loop through all form data and save it to the data table.
		$data_table = Helper::data_table();

		foreach ( $posted_data as $key => $value ) {
			if ( ! in_array( $key, $excluded_keys ) ) {
				// Sanitize the value for database insertion.
				$sanitized_value = is_array( $value ) ? wp_json_encode( array_map( 'sanitize_text_field', $value ) ) : sanitize_text_field( $value );

				// Insert each field as a new row.
				$wpdb->insert(
					$data_table,
					array(
						'submission_id' => $submission_id,
						'field_key'     => $key,
						'field_value'   => $sanitized_value,
					),
					array( '%d', '%s', '%s' )
				);
			}
		}

		// After a successful insert, trigger the Google Sheets sync.
		// $this->send_entry_to_google_sheets( $wpdb->insert_id );
	}

	private function upload_file( $uploaded_files, &$entry_data ) {
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
						$private_dir = $upload_dir['basedir'] . '/fem-cf7-uploads';
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
	}
}
