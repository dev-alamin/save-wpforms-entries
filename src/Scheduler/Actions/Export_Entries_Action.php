<?php

namespace App\AdvancedEntryManager\Scheduler\Actions;
use App\AdvancedEntryManager\Api\Callback\Export_Entries;

class Export_Entries_Action {

    protected $export_entries;

    public function __construct( Export_Entries $export_entries ) {
        $this->export_entries = $export_entries;

        add_action(Export_Entries::BATCH_PROCESSING_HOOK, [$this->export_entries, 'process_export_batch'], 10, 1);

        add_action(Export_Entries::FINALIZE_HOOK, [$this->export_entries, 'finalize_export_file'], 10, 1);

        add_action('aemfw_daily_cleanup', [ $this, 'aemfw_clean_old_exports' ]);

        if ( ! wp_next_scheduled( 'aemfw_daily_cleanup' ) ) {
            wp_schedule_event( time(), 'daily', 'aemfw_daily_cleanup' );
        }
    }

    /**
     * Removes old CSV export files from the 'aemfw_exports' directory.
     *
     * This method scans the 'aemfw_exports' folder within the WordPress uploads directory
     * and deletes any CSV files that are older than 2 days based on their last modification time.
     *
     * @return void
     */
    public function aemfw_clean_old_exports() {
        $dir = wp_upload_dir()['basedir'] . '/aemfw_exports';
        foreach (glob($dir . '/*.csv') as $file) {
            if (filemtime($file) < strtotime('-2 days')) {
                unlink($file);
            }
        }
    }
}
