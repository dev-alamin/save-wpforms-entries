<?php

namespace App\AdvancedEntryManager\Scheduler\Actions;

class Migrate_Batch {

    public function __construct() {
        // Initialize any required properties or dependencies here
        add_action( 'swpfe_migrate_batch', [ $this, 'migrate_from_wpformsdb_plugin' ], 10, 1 );
    }

    /**
     * Migrate entries from the WPFormsDB plugin to the new format.
     *
     * This method checks if the WPFormsDB plugin is active and migrates
     * entries from it to the new format used by the Advanced Entries Manager.
     */
    public function migrate_from_wpformsdb_plugin($args = []){
        global $wpdb;

        $last_id      = absint( get_option( 'swpfe_migration_last_id', 0 ) );
        $batch_size   = absint( $args['batch_size'] ?? 500 );
        $source_table = $wpdb->prefix . 'wpforms_db';
        $target_table = $wpdb->prefix . 'swpfe_entries';

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $source_table WHERE form_id > %d ORDER BY form_id ASC LIMIT %d",
                $last_id,
                $batch_size
            ), ARRAY_A
        );

        if ( empty( $rows ) ) {
            update_option( 'swpfe_migration_complete', true );
            return;
        }

        foreach ( $rows as $row ) {
            $wpdb->insert(
                $target_table,
                [
                    'form_id'      => absint( $row['form_post_id'] ),
                    'entry'        => maybe_serialize( $row['form_value'] ),
                    'submitted_at' => $row['form_date'],
                    'status'       => 'unread',
                    'is_favorite'  => 0,
                ],
                [ '%d', '%s', '%s', '%s', '%d' ]
            );

            $last_id = $row['form_id'];
        }

        // Store last migrated form_id for next batch
        update_option( 'swpfe_migration_last_id', $last_id );

        // Schedule next batch
        as_schedule_single_action( time() + 5, 'swpfe_migrate_batch', [ 'batch_size' => $batch_size ], 'swpfe_migration' );
    }

}