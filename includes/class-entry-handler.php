<?php
/**
 * Entry_Handler Class
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

namespace SWPFE;

class Entry_Handler {
	/**
	 * Constructor.
	 *
	 * Hooks into WPForms entry save process to capture form submissions.
	 */
	public function __construct() {
		add_action( 'wpforms_process_entry_save', [ $this, 'save_entry' ], 10, 3 );
	}

	/**
	 * Save form entry to custom database table.
	 *
	 * Called after WPForms saves the entry. This stores the entry data
	 * in a custom table defined by DB_Handler::table_name().
	 *
	 * @param array $fields   Submitted form fields.
	 * @param array $entry    Entry meta data.
	 * @param int   $form_id  ID of the submitted form.
	 *
	 * @return void
	 */
	public function save_entry( $fields, $entry, $form_id ) {
		global $wpdb;

		$table = DB_Handler::table_name();

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
	}
}

new Entry_Handler();
