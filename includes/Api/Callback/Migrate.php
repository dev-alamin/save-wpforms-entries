<?php

namespace App\AdvancedEntryManager\Api\Callback;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use App\AdvancedEntryManager\Utility\Helper;
use App\AdvancedEntryManager\Utility\DB;

class Migrate
{
    const AEM_PREFIX         = 'swpfe_';
    const SOURCE_TABLE       = self::AEM_PREFIX . 'wpforms_db';
    const TARGET_TABLE       = self::AEM_PREFIX . 'swpfe_entries';
    const OPTION_LAST_ID     = self::AEM_PREFIX . 'migration_last_id';
    const OPTION_COMPLETE    = self::AEM_PREFIX . 'migration_complete';
    const BATCH_SIZE         = 500;
    const ACTION_HOOK        = self::AEM_PREFIX . 'swpfe_migrate_batch';
    const SCHEDULE_GROUP     = self::AEM_PREFIX . 'swpfe_migration';

    /**
     * Trigger the migration process.
     *
     * @return array|WP_Error
     */
    public function trigger_migration()
    {
        if (! class_exists('ActionScheduler')) {
            return new WP_Error('missing_scheduler', __('Action Scheduler not available', 'advanced-entries-manager-for-wpforms'));
        }

        // Prevent triggering if migration already running and not complete
        if (Helper::get_option('swpfe_migration_started_at') && ! Helper::get_option(self::OPTION_COMPLETE)) {
            return new WP_Error('migration_already_running', __('Migration is already in progress.', 'advanced-entries-manager-for-wpforms'));
        }

        // Now safe to reset progress and start fresh
        Helper::update_option(self::OPTION_LAST_ID, 0);
        Helper::delete_option(self::OPTION_COMPLETE);

        if (! Helper::get_option('swpfe_migration_started_at')) {
            Helper::update_option('swpfe_migration_started_at', time());
        }

        // Clear any pending/reserved duplicate actions
        as_unschedule_all_actions(self::ACTION_HOOK, [], self::SCHEDULE_GROUP);

        // Schedule the first batch
        as_schedule_single_action(
            time(),
            self::ACTION_HOOK,
            ['batch_size' => self::BATCH_SIZE],
            self::SCHEDULE_GROUP
        );

        return rest_ensure_response([
            'success' => true,
            'message' => __('Migration started in background.', 'advanced-entries-manager-for-wpforms'),
            'code'    => 'swpfe_migration_started',
        ]);
    }

    /**
     * Process one batch of entries.
     *
     * @param int $batch_size
     * @return void
     */
    public function migrate_from_wpformsdb_plugin(int $batch_size = self::BATCH_SIZE): void
    {
        error_log('[SWPFE MIGRATION] migrate_from_wpformsdb_plugin called, batch size: ' . $batch_size);
        global $wpdb;

        $last_id = absint(Helper::get_option(self::OPTION_LAST_ID, 0));
        $source_table = $wpdb->prefix . self::SOURCE_TABLE;
        $target_table = $wpdb->prefix . self::TARGET_TABLE;

        if (! Helper::is_wpformsdb_table_exists($source_table)) {
            error_log('[SWPFE ERROR] Source table missing: ' . $source_table);
            return;
        }

        if (! Helper::is_wpformsdb_table_exists($target_table)) {
            error_log('[SWPFE ERROR] Target table missing: ' . $target_table);
            return;
        }


        $entries = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$source_table} WHERE form_id > %d ORDER BY form_id ASC LIMIT %d",
                $last_id,
                $batch_size
            ),
            ARRAY_A
        );

        $total_entries = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$source_table}");

        // Save total entries count to option for progress tracking
        Helper::update_option('migration_total_entries', $total_entries);

        if (empty($entries)) {
            Helper::update_option(self::OPTION_COMPLETE, true);
            return;
        }

        $new_last_id = $last_id;

        foreach ($entries as $entry) {
            $form_id     = absint($entry['form_post_id'] ?? 0);
            $form_value  = $entry['form_value'] ?? '';

            $data_array = maybe_unserialize($form_value);
            unset($data_array['WPFormsDB_status']);

            // Lowercase all keys and values
            $data_array = array_map('strtolower', $data_array);
            $data_array = array_change_key_case($data_array, CASE_LOWER);

            $email = isset($data_array['email']) ? sanitize_email($data_array['email']) : '';

            $form_date   = sanitize_text_field($entry['form_date'] ?? current_time('mysql'));

            $result = $wpdb->insert(
                $target_table,
                [
                    'form_id'     => $form_id,
                    'entry'       => $form_value,
                    'email'       => $email,
                    'created_at'  => $form_date,  // <-- use created_at instead of submitted_at
                    'status'      => 'unread',
                    'is_favorite' => 0,
                ],
                ['%d', '%s', '%s', '%s', '%s', '%d']
            );

            if ($result !== false) {
                $new_last_id = max($new_last_id, intval($entry['form_id']));
            }
        }

        Helper::update_option(self::OPTION_LAST_ID, $new_last_id);

        if (count($entries) === $batch_size) {
            as_schedule_single_action(
                time() + 5,
                self::ACTION_HOOK,
                ['batch_size' => $batch_size],
                self::SCHEDULE_GROUP
            );
        } else {
            Helper::update_option(self::OPTION_COMPLETE, true);
        }
    }

    public function wpformsdb_data(WP_REST_Request $request)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'wpforms_db';

        // Safety: check if table exists
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
            return new WP_Error('table_missing', 'Source table does not exist', ['status' => 404]);
        }

        // Query counts grouped by form_post_id
        $results = $wpdb->get_results("
            SELECT form_post_id AS form_id, COUNT(*) AS entry_count
            FROM {$table}
            GROUP BY form_post_id
            ORDER BY entry_count DESC
            LIMIT 100
        ", ARRAY_A);

        return rest_ensure_response($results);
    }

    public function get_migration_progress()
    {
        $total    = (int) Helper::get_option('migration_total_entries', 0);
        $migrated = (int) Helper::get_option('migration_last_id', 0); // Or count rows if needed

        if ($total === 0) {
            return rest_ensure_response([
                'progress' => 100,
                'complete' => true,
                'migrated' => $migrated,
                'total'    => $total,
                'eta'      => null,
            ]);
        }

        $progress = ($migrated / $total) * 100;
        $progress = min(100, round($progress, 2));

        $complete = (bool) Helper::get_option('migration_complete', false);

        $start = (int) Helper::get_option('swpfe_migration_started_at', 0);
        $eta   = null;

        if ($start > 0 && $migrated > 0 && !$complete) {
            $elapsed = time() - $start;
            $eta = (($total - $migrated) / $migrated) * $elapsed;
            $eta = max(0, (int) $eta); // ensure it's not negative
        }

        return rest_ensure_response([
            'progress' => $progress,
            'complete' => $complete,
            'migrated' => $migrated,
            'total'    => $total,
            'eta'      => $eta,
        ]);
    }
}
