<?php

namespace App\AdvancedEntryManager\Scheduler;

defined( 'ABSPATH' ) || exit;

use App\AdvancedEntryManager\Utility\Helper;

class Scheduler {

	/**
	 * Schedule batch export jobs via Action Scheduler.
	 *
	 * @param string $hook Hook name to trigger.
	 * @param array  $args Arguments for the job callback.
	 * @param int    $total Total items to process.
	 * @param int    $batch_size Size of each batch.
	 * @param int    $delay Delay seconds between batches.
	 * @return void
	 */
	public static function queue_export_batches( $form_id, $date_from, $date_to, $exclude_fields, $batch_size ) {
		// Calculate total entries to export
		global $wpdb;

		$where_clauses = array( 'form_id = %d' );
		$args          = array( $form_id );

		if ( $date_from ) {
			$where_clauses[] = 'created_at >= %s';
			$args[]          = $date_from;
		}

		if ( $date_to ) {
			$where_clauses[] = 'created_at <= %s';
			$args[]          = $date_to;
		}

		$where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );

		$table_name = Helper::get_table_name();

		$count_sql = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table_name} {$where_sql}",
			...$args
		);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total_entries = (int) $wpdb->get_var( $count_sql );

		$batches = (int) ceil( $total_entries / $batch_size );

		for ( $i = 0; $i < $batches; $i++ ) {
			// Schedule each batch with Action Scheduler
			$args = array(
				'form_id'        => $form_id,
				'date_from'      => $date_from,
				'date_to'        => $date_to,
				'exclude_fields' => $exclude_fields,
				'batch_size'     => $batch_size,
				'batch_number'   => $i + 1,
				'offset'         => $i * $batch_size,
			);

			if ( ! as_next_scheduled_action( 'femexport_csv_batch', array( $args ) ) ) {
				as_schedule_single_action( time() + ( $i * 15 ), 'femexport_csv_batch', array( $args ), 'femexport_csv_group' );
			}
		}
	}
}
