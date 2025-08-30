<?php

namespace App\AdvancedEntryManager\Api\Callback;

defined( 'ABSPATH' ) || exit;

use App\AdvancedEntryManager\Utility\Helper;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WPForms\Admin\Builder\Help;

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

        $submissions_table = Helper::get_table_name();
        $data_table        = Helper::data_table();

        $form_id   = $request->get_param( 'form_id' );
        $status    = $request->get_param( 'status' );
        $search    = $request->get_param( 'search' );
        $per_page  = $request->get_param( 'per_page' );
        $page      = $request->get_param( 'page' );
        $date_from = $request->get_param( 'date_from' );
        $date_to   = $request->get_param( 'date_to' );
        $search_type = $request->get_param( 'search_type' ) ?? 'default';

        $offset   = ( $page - 1 ) * $per_page;

        // Construct WHERE clauses and parameters
        $where_clauses = array();
        $params        = array();
        $join_clause   = '';

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
                    $where_clauses[] = 'id = %d';
                    $params[]        = (int) $search;
                    break;
                case 'name':
                    $where_clauses[] = 'name LIKE %s';
                    $params[]        = '%' . $wpdb->esc_like( $search ) . '%';
                    break;
                default:
                    $join_clause     = "JOIN $data_table ON $submissions_table.id = $data_table.submission_id";
                    $where_clauses[] = "($submissions_table.name LIKE %s OR $data_table.field_value LIKE %s)";
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
        $where = apply_filters( 'fem_get_entries_where', $where, $params, $request );

        // Get the total count for pagination
        $count_sql = $wpdb->prepare(
            "SELECT COUNT(DISTINCT $submissions_table.id) FROM $submissions_table $join_clause $where",
            ...$params
        );
        $total_count = (int) $wpdb->get_var( $count_sql );

        // Fetch the main submission records.
        $sql = $wpdb->prepare(
            "SELECT DISTINCT $submissions_table.* FROM $submissions_table $join_clause $where ORDER BY $submissions_table.created_at DESC LIMIT %d OFFSET %d",
            ...array_merge( $params, [ $per_page, $offset ] )
        );
        $results = $wpdb->get_results( $sql );

        if ( empty( $results ) ) {
            return rest_ensure_response( [
                'entries'      => [],
                'entry_schema' => [],
                'total'        => 0,
                'page'         => $page,
                'per_page'     => $per_page,
            ] );
        }

        // Get the IDs of the fetched submissions.
        $submission_ids = array_column( $results, 'id' );

        // Fetch all field data for the fetched submissions in a single query.
        $placeholders = implode( ',', array_fill( 0, count( $submission_ids ), '%d' ) );
        $field_data_sql = $wpdb->prepare(
            "SELECT submission_id, field_key, field_value FROM $data_table WHERE submission_id IN ($placeholders)",
            ...$submission_ids
        );
        $field_data = $wpdb->get_results( $field_data_sql );

        // Map field data to a nested array for easier access.
        $mapped_field_data = [];
        foreach ( $field_data as $field_row ) {
            $mapped_field_data[ $field_row->submission_id ][ $field_row->field_key ] = $field_row->field_value;
        }

        $data           = [];
        $all_entry_keys = [];

        foreach ( $results as $row ) {
            $row_id = (int) $row->id;
            $entry_normalized = [];

            // Reconstruct the entry data from the fetched fields.
            if ( isset( $mapped_field_data[ $row_id ] ) ) {
                foreach ( $mapped_field_data[ $row_id ] as $key => $value ) {
                    $entry_normalized[ $key ] = maybe_unserialize( $value );
                    $all_entry_keys[] = $key;
                }
            }
            
            // Re-organize the data into the final array format.
            $data[] = array(
                'id'          => $row_id,
                'form_title'  => get_the_title( $row->form_id ),
                'entry'       => $entry_normalized,
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

        // Build the final fields schema array
        $entry_schema = [];
        $unique_entry_keys = array_values( array_unique( $all_entry_keys ) );
        
        foreach ( $unique_entry_keys as $key ) {
            // Exclude system fields
            if ( strpos( strtolower( $key ), '_wpcf7' ) !== false || strpos( strtolower( $key ), 'g-recaptcha-response' ) !== false ) {
                continue;
            }
            $entry_schema[] = [
                'key'   => $key,
                'label' => ucwords( str_replace( '-', ' ', $key ) ), // Make labels more readable.
            ];
        }

        $data = apply_filters( 'fem_get_entries_data', $data, $results, $request );
        do_action( 'fem_after_get_total_count', $total_count, $request );

        $response = rest_ensure_response(
            array(
                'entries'      => $data,
                'entry_schema' => $entry_schema,
                'total'        => $total_count,
                'page'         => $page,
                'per_page'     => $per_page,
            )
        );

        return apply_filters( 'fem_get_entries_response', $response, $request );
    }
}
