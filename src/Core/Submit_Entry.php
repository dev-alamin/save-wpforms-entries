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

/**
 * Class Submit_Entry
 *
 * Handles saving WPForms entries to a custom database table.
 */
class Submit_Entry {
	public function __construct() {
		add_action( 'wpforms_process_entry_save', [ $this, 'save_entry' ], 10, 3 );
		add_action( 'wpforms_process_complete', [ $this, 'update_meta_fields' ], 10, 4 );
	}

    /**
     * Save the entry data into the custom table.
     *
     * @param array $fields The fields submitted in the form.
     * @param array $entry The entry data from WPForms.
     * @param int $form_id The ID of the form being submitted.
     */
	public function save_entry( $fields, $entry, $form_id ) {
		global $wpdb;

		$table = Helper::get_table_name(); // e.g., 'AEMFW' table

		$data = [];
		foreach ( $fields as $field ) {
			$value = is_array( $field['value'] ) ? implode(',', $field['value']) : $field['value'];
			$data[ $field['name'] ] = $value;
		}

		$wpdb->insert( $table, [
			'form_id'    => $form_id,
			'entry'      => maybe_serialize( $data ),
			'status'     => 'unread',
			'created_at' => current_time( 'mysql' ),
		] );

		// Store entry ID in wp_options to update later
		update_option( "swpfe_last_entry_id_{$form_id}", $wpdb->insert_id );
	}

	public function update_meta_fields( $fields, $entry, $form_data, $entry_id ) {
		global $wpdb;

		$table = Helper::get_table_name();

		$name  = '';
		$email = '';

		foreach ( $fields as $field ) {
			if ( $field['type'] === 'name' ) {
				$first = $field['first'] ?? '';
				$last  = $field['last'] ?? '';
				$name  = trim( $first . ' ' . $last );
			}
            
			if ( $field['type'] === 'email' ) {
				$email = $field['value'];
			}
		}

		$last_id = get_option( "swpfe_last_entry_id_{$form_data['id']}" );

		if ( $last_id ) {
			$wpdb->update(
				$table,
				[ 'name' => $name, 'email' => $email ],
				[ 'id' => $last_id ]
			);
		}

		//error_log( "[Entry $entry_id] Updated: Name: $name | Email: $email | Last ID: $last_id" );
	}
}
