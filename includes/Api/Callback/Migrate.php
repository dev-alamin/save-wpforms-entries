<?php

namespace App\AdvancedEntryManager\Api\Callback;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class Migrate {

    /**
     * Migrate entries from the WPFormsDB plugin to the new format.
     *
     * This method checks if the WPFormsDB plugin is active and migrates
     * entries from it to the new format used by the Advanced Entries Manager.
     */
    public function migrate_from_wpformsdb_plugin() {
        global $wpdb;

        $source_table = $wpdb->prefix . 'wpforms_db';
        $target_table = $wpdb->prefix . 'swpfe_entries';

        // Fetch all entries from wpforms_db
        $entries = $wpdb->get_results( "SELECT * FROM {$source_table}", ARRAY_A );

        return [
            'message' => 'Migration started. Check the logs for progress.',
            'status'  => 'success',
            'data'    => [],
        ];

        if ( empty( $entries ) ) {
            return new WP_REST_Response( [ 'message' => 'No entries found to migrate.' ], 200 );
        }

        $migrated = 0;

        foreach ( $entries as $entry ) {
            $form_id     = absint( $entry['form_post_id'] );
            $form_value  = maybe_serialize( $entry['form_value'] ); // or maybe JSON decode then re-encode if needed
            $form_date   = $entry['form_date'];

            // Insert into your table
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
                $migrated++;
            }
        }

        return new WP_REST_Response( [
            'message'  => 'Migration completed.',
            'migrated' => $migrated,
            'total'    => count( $entries ),
        ], 200 );
    }

    /**
     * Trigger the migration process.
     *
     * This method schedules a background task to migrate entries in batches.
     * It uses Action Scheduler to handle large datasets without blocking the request.
     *
     * @return WP_Error|array
     */
    public function trigger_migration() {
        if ( ! class_exists( 'ActionScheduler' ) ) {
            return new WP_Error( 'missing_scheduler', 'Action Scheduler not available' );
        }

        $batch_size = 500;

        // Save last migrated ID
        update_option( 'swpfe_migration_last_id', 0 );

        as_schedule_single_action( time(), 'swpfe_migrate_batch', [ 'batch_size' => $batch_size ], 'swpfe_migration' );

        return [ 'message' => 'Migration started in background.' ];
    }
}