<?php

namespace App\AdvancedEntryManager\Scheduler\Actions;

defined( 'ABSPATH' ) || exit;

use App\AdvancedEntryManager\Api\Callback\Export_Entries;

class Export_Entries_Action {

	protected $export_entries;

	public function __construct( Export_Entries $export_entries ) {
		$this->export_entries = $export_entries;

		add_action( Export_Entries::BATCH_PROCESSING_HOOK, array( $this->export_entries, 'process_export_batch' ), 10, 1 );

		add_action( Export_Entries::FINALIZE_HOOK, array( $this->export_entries, 'finalize_export_file' ), 10, 1 );

		add_action( 'fem_daily_cleanup', array( $this, 'fem_clean_old_exports' ) );

		if ( ! wp_next_scheduled( 'fem_daily_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'fem_daily_cleanup' );
		}
	}

	/**
	 * Removes old CSV export files from the 'fem_exports' directory.
	 *
	 * This method scans the 'fem_exports' folder within the WordPress uploads directory
	 * and deletes any CSV files that are older than 2 days based on their last modification time.
	 *
	 * @return void
	 */
	public function fem_clean_old_exports() {
		$dir = wp_upload_dir()['basedir'] . '/forms-entries-manager-exports';
		foreach ( glob( $dir . '/*.csv' ) as $file ) {
			if ( filemtime( $file ) < strtotime( '-2 days' ) ) {
				wp_delete_file( $file );
			}
		}
	}
}
