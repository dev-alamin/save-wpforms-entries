<?php
namespace SWPFE;

class Entry_Handler {
	public function __construct() {
		add_action( 'wpforms_process_entry_save', [ $this, 'save_entry' ], 10, 3 );
	}

	public function save_entry( $fields, $entry, $form_id ) {
		global $wpdb;
		$table = DB_Handler::table_name();

		$data = [];
		foreach ( $fields as $field ) {
			$value = is_array( $field['value'] ) ? implode(',', $field['value']) : $field['value'];
			$data[ $field['name'] ] = $value;
		}

		$wpdb->insert( $table, [
			'form_id'   => $form_id,
			'entry'     => maybe_serialize( $data ),
			'status'    => 'unread',
			'created_at'=> current_time( 'mysql' ),
		] );
	}
}

new Entry_Handler();
