<?php

namespace App\AdvancedEntryManager\Scheduler\Actions;
use App\AdvancedEntryManager\Api\Callback\Export_Entries;

class Export_Entries_Action {

    protected $export_entries;

    public function __construct() {
        $this->export_entries = new Export_Entries();

        // In Export_Entries_Action::__construct()
        add_action(Export_Entries::BATCH_PROCESSING_HOOK, [$this->export_entries, 'process_export_batch'], 10, 1);

        add_action(Export_Entries::FINALIZE_HOOK, [$this->export_entries, 'finalize_export_file'], 10, 1);

        add_action('swpfe_daily_cleanup', [ $this, 'swpfe_clean_old_exports' ]);

        if ( ! wp_next_scheduled( 'swpfe_daily_cleanup' ) ) {
            wp_schedule_event( time(), 'daily', 'swpfe_daily_cleanup' );
        }
    }

    /**
     * Removes old CSV export files from the 'swpfe_exports' directory.
     *
     * This method scans the 'swpfe_exports' folder within the WordPress uploads directory
     * and deletes any CSV files that are older than 2 days based on their last modification time.
     *
     * @return void
     */
    public function swpfe_clean_old_exports() {
        $dir = wp_upload_dir()['basedir'] . '/swpfe_exports';
        foreach (glob($dir . '/*.csv') as $file) {
            if (filemtime($file) < strtotime('-2 days')) {
                unlink($file);
            }
        }
    }
}
