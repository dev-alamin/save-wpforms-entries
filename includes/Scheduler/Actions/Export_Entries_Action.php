<?php

namespace App\AdvancedEntryManager\Scheduler\Actions;

use App\AdvancedEntryManager\Scheduler\Scheduler;

class Export_Entries_Action {

    public function __construct() {
        add_action('swpfe_export_csv_batch', [$this, 'schedule_export'], 10, 5);
    }

    /**
     * Schedule export entries action.
     *
     * @param int    $form_id        The ID of the form to export entries from.
     * @param string $date_from      Optional start date for filtering entries.
     * @param string $date_to        Optional end date for filtering entries.
     * @param array  $exclude_fields Fields to exclude from the export.
     * @param int    $batch_size     Number of entries to process in each batch.
     */
    public static function schedule_export($form_id, $date_from = '', $date_to = '', $exclude_fields = [], $batch_size = 500) {
        if (empty($form_id) || !is_numeric($form_id)) {
            error_log('[AEM][Export_Entries_Action] Invalid form ID provided for export.');
            return;
        }

        if (!is_array($exclude_fields)) {
            $exclude_fields = [];
        }

        error_log(sprintf(
            '[AEM][Export_Entries_Action] Queuing export for form_id: %d | From: %s | To: %s | Excluded Fields: %s | Batch Size: %d',
            $form_id,
            $date_from ?: 'N/A',
            $date_to ?: 'N/A',
            implode(', ', $exclude_fields),
            $batch_size
        ));

        try {
            Scheduler::queue_export_batches($form_id, $date_from, $date_to, $exclude_fields, $batch_size);
            error_log("[AEM][Export_Entries_Action] Export queue triggered successfully for form_id: $form_id");
        } catch (\Exception $e) {
            error_log('[AEM][Export_Entries_Action][Error] ' . $e->getMessage());
        }
    }
}
