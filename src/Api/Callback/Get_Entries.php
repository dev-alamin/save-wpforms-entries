<?php

namespace App\AdvancedEntryManager\Api\Callback;

defined( 'ABSPATH' ) || exit;

use App\AdvancedEntryManager\Utility\Helper;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Class Get_Entries
 *
 * Handles the retrieval of entries from the custom database table.
 */
class Get_Entries {

	/**
	 * Retrieves all entries from the fem table.
	 *
	 * Fetches all rows from the custom entries table, decodes the entry data,
	 * and groups entries by form ID.
	 *
	 * @return \WP_REST_Response List of decoded entries as a REST response.
	 */
	public function get_entries( WP_REST_Request $request ) {
		global $wpdb;

		$table     = Helper::get_table_name();
		$form_id   = $request->get_param( 'form_id' );
		$status    = $request->get_param( 'status' );
		$search    = $request->get_param( 'search' );
		$per_page  = $request->get_param( 'per_page' );
		$page      = $request->get_param( 'page' );
		$date_from = $request->get_param( 'date_from' );
		$date_to   = $request->get_param( 'date_to' );

		// Default search type if not provided, assuming we have a valid `search` parameter.
		$search_type = $request->get_param( 'search_type' ) ?? 'default';

		// --- Start of Hybrid Pagination Logic ---
		$offset   = ( $page - 1 ) * $per_page;
		$start_id = null;

		// Construct WHERE clauses and parameters
		$where_clauses = array();
		$params        = array();

		// Base condition to ensure a valid WHERE clause
		$where_clauses[] = '1=1';

		if ( $form_id ) {
			$where_clauses[] = 'form_id = %d';
			$params[]        = $form_id;
		}

		if ( $status === 'read' || $status === 'unread' ) {
			$where_clauses[] = 'status = %s';
			$params[]        = $status;
		}

		if ( $search ) {
			switch ( $search_type ) {
				case 'email':
					$where_clauses[] = 'email = %s';
					$params[]        = (string) $search;
					break;
				case 'id':
					// The validate_callback for 'id' should ensure this is an integer.
					$where_clauses[] = 'id = %d';
					$params[]        = (int) $search;
					break;
				case 'name':
					$where_clauses[] = 'name LIKE %s';
					$params[]        = '%' . $wpdb->esc_like( $search ) . '%';
					break;
				default:
					// Default search is now 'name' or 'entry'
					$where_clauses[] = '(name LIKE %s OR entry LIKE %s)';
					$params[]        = '%' . $wpdb->esc_like( $search ) . '%';
					$params[]        = '%' . $wpdb->esc_like( $search ) . '%';
					break;
			}
		}

		if ( $date_from ) {
			$where_clauses[] = 'created_at >= %s';
			$params[]        = $date_from . ' 00:00:00';
		}

		if ( $date_to ) {
			$where_clauses[] = 'created_at <= %s';
			$params[]        = $date_to . ' 23:59:59';
		}

		$where = 'WHERE ' . implode( ' AND ', $where_clauses );
		$where = apply_filters( 'femget_entries_where', $where, $params, $request );

		// First, get the total count. This query is still needed for pagination button rendering.
		$count_sql   = $wpdb->prepare(
			"SELECT COUNT(*) FROM $table $where",
			...$params
		);
		$total_count = (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		// Step 1: Find the ID of the first entry for the requested page.
		if ( $page > 1 ) {
			$get_id_sql = $wpdb->prepare(
				"SELECT id FROM $table $where ORDER BY created_at DESC LIMIT 1 OFFSET %d",
				...array_merge( $params, array( $offset ) )
			);
			$start_id   = $wpdb->get_var( $get_id_sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}

		// Step 2: Fetch the data using the cursor/start ID.
		$data_sql    = "SELECT * FROM $table $where ";
		$data_params = $params;

		if ( $page > 1 && $start_id ) {
			$data_sql     .= ' AND id <= %d ORDER BY created_at DESC LIMIT %d';
			$data_params[] = $start_id;
			$data_params[] = $per_page;
		} else {
			$data_sql     .= ' ORDER BY created_at DESC LIMIT %d';
			$data_params[] = $per_page;
		}

		$sql     = $wpdb->prepare( $data_sql, ...$data_params );
		$results = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		// --- The rest of the code remains the same ---
		// Initialize the final data array
		$data = array();
		// Initialize a unique set of all keys found across all entries
		$all_entry_keys = array();

		foreach ( $results as $row ) {
			$entry_raw        = maybe_unserialize( $row->entry );
			$entry_normalized = array();

			if ( is_array( $entry_raw ) ) {
				foreach ( $entry_raw as $key => $value ) {
					// Use ucwords() on keys for display purposes
					$normalized_key                      = ucwords( strtolower( $key ) );
					$entry_normalized[ $normalized_key ] = $value;
					// Collect all unique normalized keys
					$all_entry_keys[] = $normalized_key;
				}
			}

			$data[] = array(
				'id'          => (int) $row->id,
				'form_title'  => get_the_title( $row->form_id ),
				'entry'       => $entry_normalized, // Keep the normalized entry data
				'name'        => $row->name,
				'email'       => $row->email,
				'status'      => $row->status,
				'date'        => $row->created_at,
				'note'        => $row->note,
				'is_favorite' => (bool) $row->is_favorite,
				'exported'    => (bool) $row->exported_to_csv,
				'synced'      => (bool) $row->synced_to_gsheet,
				'printed_at'  => $row->printed_at,
				'resent_at'   => $row->resent_at,
				'form_id'     => (int) $row->form_id,
				'is_spam'     => (int) $row->is_spam,
			);
		}

		// Ensure unique keys and sort them for consistency
		$unique_entry_keys = array_values( array_unique( $all_entry_keys ) );

		// Build the final fields schema array
		$entry_schema = array();

		// Add the dynamic fields from the `entry` object
		foreach ( $unique_entry_keys as $key ) {

            if( 
            strtolower( $key ) == 'name' 
            || strtolower( $key ) == 'email' 
            || strtolower( $key ) == 'your-name'
            || strtolower( $key ) == 'your-email'
            || strpos( strtolower( $key ), 'g-recaptcha-response' ) !== false
            || strpos( strtolower( $key ), 'file' ) !== false
            ) {
                continue;
            }

            $entry_schema[] = array(
                'key'      =>  $key,
                'label'    => $key,
            );
		}

		$data = apply_filters( 'femget_entries_data', $data, $results, $request );

		do_action( 'femafter_get_total_count', $total_count, $request );

		$response = rest_ensure_response(
			array(
				'entries'       => $data,
				'entry_schema' => $entry_schema, // Add the schema to the final response
				'total'         => $total_count,
				'page'          => $page,
				'per_page'      => $per_page,
			)
		);

		return apply_filters( 'femget_entries_response', $response, $request );
	}
}
