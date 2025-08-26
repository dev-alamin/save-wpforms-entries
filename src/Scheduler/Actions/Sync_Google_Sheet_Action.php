<?php

namespace App\AdvancedEntryManager\Scheduler\Actions;

defined( 'ABSPATH' ) || exit;

use App\AdvancedEntryManager\GoogleSheet\Send_Data;

class Sync_Google_Sheet_Action {


	protected $send_data;

	public function __construct( Send_Data $send_data ) {
		$this->send_data = $send_data;

		// Corrected: Direct hook to the class method is the cleaner and intended way.
		// add_action('fem_process_gsheet_entry', [$this->send_data, 'process_single_entry']);
		add_action(
			'fem_process_gsheet_entry',
			function ( $entry_id ) {
				$this->send_data->process_single_entry( array( 'entry_id' => $entry_id ) );
			}
		);

		// Hook the task.
		add_action( 'fem_every_five_minute_sync', array( $this->send_data, 'enqueue_unsynced_entries' ) );
	}
}
