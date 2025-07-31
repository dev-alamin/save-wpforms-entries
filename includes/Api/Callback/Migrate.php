<?php

namespace App\AdvancedEntryManager\Api\Callback;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use App\AdvancedEntryManager\Utility\Helper;
use App\AdvancedEntryManager\Utility\DB;
class Migrate {

    const SOURCE_TABLE       = 'wpforms_db';
    const TARGET_TABLE       = 'swpfe_entries';
    const OPTION_LAST_ID     = 'migration_last_id';
    const OPTION_COMPLETE    = 'migration_complete';
    const BATCH_SIZE         = 500;
    const ACTION_HOOK        = 'swpfe_migrate_batch';
    const SCHEDULE_GROUP     = 'swpfe_migration';

    /**
     * Trigger the migration process.
     *
     * @return array|WP_Error
     */
    public function trigger_migration() {
        if ( ! class_exists( 'ActionScheduler' ) ) {
            return new WP_Error( 'missing_scheduler', __( 'Action Scheduler not available', 'save-wpf-entries' ) );
        }

        // Reset progress state
        Helper::update_option( self::OPTION_LAST_ID, 0 );
        Helper::delete_option( self::OPTION_COMPLETE );

        // Clear any pending/reserved duplicate actions
        as_unschedule_all_actions( self::ACTION_HOOK, [], self::SCHEDULE_GROUP );

        // Schedule the first batch
        as_schedule_single_action(
            time(),
            self::ACTION_HOOK,
            [ 'batch_size' => self::BATCH_SIZE ],
            self::SCHEDULE_GROUP
        );

        return [ 'message' => __( 'Migration started in background.', 'save-wpf-entries' ) ];
    }

    /**
     * Process one batch of entries.
     *
     * @param int $batch_size
     * @return void
     */
    public static function migrate_from_wpformsdb_plugin( int $batch_size = self::BATCH_SIZE ): void {
         error_log('[SWPFE MIGRATION] migrate_from_wpformsdb_plugin called, batch size: ' . $batch_size);
        global $wpdb;

        $last_id      = absint( get_option( self::OPTION_LAST_ID, 0 ) );
        $source_table = $wpdb->prefix . self::SOURCE_TABLE;
        $target_table = $wpdb->prefix . self::TARGET_TABLE;

        if ( ! DB::table_exists( $source_table ) || ! DB::table_exists( $target_table ) ) {
            return;
        }

        $entries = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$source_table} WHERE id > %d ORDER BY id ASC LIMIT %d",
                $last_id,
                $batch_size
            ),
            ARRAY_A
        );

        if ( empty( $entries ) ) {
            Helper::update_option( self::OPTION_COMPLETE, true );
            return;
        }

        $new_last_id = $last_id;

        foreach ( $entries as $entry ) {
            $form_id     = absint( $entry['form_post_id'] ?? 0 );
            $form_value  = maybe_serialize( $entry['form_value'] ?? '' );
            $form_date   = sanitize_text_field( $entry['form_date'] ?? current_time( 'mysql' ) );

            $result = $wpdb->insert(
                $target_table,
                [
                    'form_id'      => $form_id,
                    'entry'        => $form_value,
                    'submitted_at' => $form_date,
                    'status'       => 'unread',
                    'is_favorite'  => 0,
                ],
                [ '%d', '%s', '%s', '%s', '%d' ]
            );

            if ( $result !== false ) {
                $new_last_id = max( $new_last_id, intval( $entry['id'] ) );
            }
        }

        update_option( self::OPTION_LAST_ID, $new_last_id );

        if ( count( $entries ) === $batch_size ) {
            as_schedule_single_action(
                time() + 5,
                self::ACTION_HOOK,
                [ 'batch_size' => $batch_size ],
                self::SCHEDULE_GROUP
            );
        } else {
            update_option( self::OPTION_COMPLETE, true );
        }
    }

    public function wpformsdb_data(WP_REST_Request $request) {
        global $wpdb;

        $table = $wpdb->prefix . 'wpforms_db';

        // Safety: check if table exists
        if ( $wpdb->get_var( $wpdb->prepare("SHOW TABLES LIKE %s", $table) ) !== $table ) {
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
}
