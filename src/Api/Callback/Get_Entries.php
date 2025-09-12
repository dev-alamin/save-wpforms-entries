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
 * Handles retrieving entries from the new submissions and entries tables.
 */
class Get_Entries {

    /**
     * Retrieves a list of entries for a given form.
     *
     * @param WP_REST_Request $request The REST API request.
     * @return \WP_REST_Response A REST response with the list of entries and a data schema.
     */
    public function get_entries( WP_REST_Request $request ) {
        global $wpdb;

        // Get table names from our central schema class
        $submissions_table = Helper::get_submission_table();
        $entries_table     = Helper::get_data_table();

        // 1. Prepare query parameters.
        $params = $this->prepare_query_params( $request );

        // 2. Build the WHERE clause.
        list($where_clause, $query_params) = $this->build_where_clause($params);

        // 3. Get the total count of matching submissions.
        $total_count = $this->get_total_count($submissions_table, $where_clause, $query_params);

        // 4. Fetch submissions with pagination.
        $submissions = $this->get_paginated_submissions($submissions_table, $where_clause, $query_params, $request);

        // 5. Fetch all related entry fields in a single query.
        $entries = $this->get_entries_for_submissions($entries_table, $submissions);
        
        // 6. Process the raw data into the final structured format for the UI.
        list($formatted_entries, $entry_schema) = $this->process_entries($submissions, $entries);
        
        // 7. Build and return the final REST response.
        return $this->build_response($formatted_entries, $entry_schema, $total_count, $params);
    }
    
    /**
     * Prepare query parameters from the REST request.
     *
     * @param WP_REST_Request $request The REST API request.
     * @return array
     */
    private function prepare_query_params( WP_REST_Request $request ) {
        return [
            'form_id'     => $request->get_param( 'form_id' ),
            'status'      => $request->get_param( 'status' ),
            'search'      => $request->get_param( 'search' ),
            'per_page'    => $request->get_param( 'per_page' ) ?? 10,
            'page'        => $request->get_param( 'page' ) ?? 1,
            'date_from'   => $request->get_param( 'date_from' ),
            'date_to'     => $request->get_param( 'date_to' ),
            'search_type' => $request->get_param( 'search_type' ) ?? 'default',
        ];
    }
    
    /**
     * Builds the WHERE clause for the main query.
     *
     * @param array $params Query parameters.
     * @return array An array containing the WHERE clause and its parameters.
     */
    private function build_where_clause( array $params ) {
        global $wpdb;
        $where_clauses = ['1=1'];
        $query_params  = [];

        if ( $params['form_id'] ) {
            $where_clauses[] = 'form_id = %d';
            $query_params[]  = (int) $params['form_id'];
        }
        if ( $params['status'] === 'read' || $params['status'] === 'unread' ) {
            $where_clauses[] = 'status = %s';
            $query_params[]  = $params['status'];
        }
        if ( $params['search'] ) {
            switch ( $params['search_type'] ) {
                case 'email':
                    $where_clauses[] = 'email = %s';
                    $query_params[]  = (string) $params['search'];
                    break;
                case 'id':
                    $where_clauses[] = 'id = %d';
                    $query_params[]  = (int) $params['search'];
                    break;
                case 'name':
                    $where_clauses[] = 'name = %s';
                    $query_params[]  = (string) $params['search'];
                    break;
                default:
                    // Default search on both name and email for efficiency.
                    $where_clauses[] = '(name LIKE %s OR email LIKE %s)';
                    $query_params[]  = '%' . $wpdb->esc_like( $params['search'] ) . '%';
                    $query_params[]  = '%' . $wpdb->esc_like( $params['search'] ) . '%';
                    break;
            }
        }
        if ( $params['date_from'] ) {
            $where_clauses[] = 'created_at >= %s';
            $query_params[]  = $params['date_from'] . ' 00:00:00';
        }
        if ( $params['date_to'] ) {
            $where_clauses[] = 'created_at <= %s';
            $query_params[]  = $params['date_to'] . ' 23:59:59';
        }

        $where_clause = 'WHERE ' . implode( ' AND ', $where_clauses );
        return [$where_clause, $query_params];
    }
    
    /**
     * Gets the total count of submissions matching the WHERE clause.
     *
     * @param string $submissions_table The submissions table name.
     * @param string $where_clause      The WHERE clause.
     * @param array  $query_params      Parameters for the query.
     * @return int The total count.
     */
    private function get_total_count( $submissions_table, $where_clause, $query_params ) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
        $sql = $wpdb->prepare("SELECT COUNT(*) FROM $submissions_table $where_clause", ...$query_params);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        return (int) $wpdb->get_var($sql);
    }
    
    /**
     * Fetches paginated submissions.
     *
     * @param string $submissions_table The submissions table name.
     * @param string $where_clause      The WHERE clause.
     * @param array  $query_params      Parameters for the query.
     * @param WP_REST_Request $request  The REST API request.
     * @return array An array of submission objects.
     */
    private function get_paginated_submissions( $submissions_table, $where_clause, $query_params, WP_REST_Request $request ) {
        global $wpdb;

        $page     = $request->get_param( 'page' ) ?? 1;
        $per_page = $request->get_param( 'per_page' ) ?? 10;
        $offset   = ( $page - 1 ) * $per_page;

        // Note: The hybrid pagination logic is no longer needed with the two-table structure.
        // We can use a simple OFFSET/LIMIT query as it is now performing on a smaller,
        // more optimized table (submissions_table).
        
        $sql = "SELECT * FROM $submissions_table $where_clause ORDER BY created_at DESC LIMIT %d OFFSET %d";

        $params = $this->build_where_clause( $request->get_params() )[1]; // Get params again.
        $params[] = (int) $per_page;
        $params[] = (int) $offset;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
        $sql = $wpdb->prepare($sql, ...$params);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        return $wpdb->get_results($sql);
    }

    /**
     * Fetches all related entry key/value pairs for the fetched submissions.
     *
     * @param string $entries_table The entries table name.
     * @param array $submissions    An array of submission objects.
     * @return array An array of entry field objects.
     */
    private function get_entries_for_submissions( $entries_table, $submissions ) {
        if ( empty( $submissions ) ) {
            return [];
        }

        global $wpdb;
        $submission_ids = wp_list_pluck( $submissions, 'id' );
        $ids_placeholder = implode( ',', array_fill( 0, count( $submission_ids ), '%d' ) );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
        $sql = $wpdb->prepare("SELECT * FROM $entries_table WHERE submission_id IN ($ids_placeholder)", ...$submission_ids);
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        return $wpdb->get_results($sql);
    }

    /**
     * Processes raw data and formats it for the UI.
     *
     * @param array $submissions An array of submission objects.
     * @param array $entries     An array of entry field objects.
     * @return array An array containing the formatted entries and a schema.
     */
    private function process_entries( $submissions, $entries ) {
        $formatted_entries = [];
        $entry_schema      = [];
        $all_entry_keys    = [];
        
        // Group all entry fields by their submission ID for easier lookup.
        $entries_by_submission = [];
        foreach ( $entries as $entry ) {
            $entries_by_submission[ $entry->submission_id ][] = $entry;
        }

        // Iterate through submissions and build the final data structure.
        foreach ( $submissions as $submission ) {
            $normalized_entry = [];
            if ( isset( $entries_by_submission[ $submission->id ] ) ) {
                foreach ( $entries_by_submission[ $submission->id ] as $entry_field ) {
                    $key   = $entry_field->field_key;
                    $value = $entry_field->field_value;
                    
                    $normalized_key = $key; // or just use $key directly
                    
                    $normalized_entry[ $normalized_key ] = $value;
                    
                    if ( ! in_array( $normalized_key, $all_entry_keys ) ) {
                        $all_entry_keys[] = $normalized_key;
                    }
                }
            }
            // The rest of your code remains the same.
            $formatted_entries[] = [
                'id'          => (int) $submission->id,
                'form_title'  => get_the_title( $submission->form_id ),
                'entry'       => $normalized_entry,
                'name'        => $submission->name,
                'email'       => $submission->email,
                'status'      => $submission->status,
                'date'        => $submission->created_at,
                'note'        => $submission->note,
                'is_favorite' => (bool) $submission->is_favorite,
                'exported'    => (bool) $submission->exported_to_csv,
                'synced'      => (bool) $submission->synced_to_gsheet,
                'printed_at'  => $submission->printed_at,
                'resent_at'   => $submission->resent_at,
                'form_id'     => (int) $submission->form_id,
                'is_spam'     => (int) $submission->is_spam,
            ];
        }
        
        // Build the dynamic fields schema.
        foreach ( $all_entry_keys as $key ) {
            if ( $this->is_system_key($key) ) {
                continue;
            }
            $entry_schema[] = [ 'key' => $key, 'label' => $key ];
        }
        
        return [$formatted_entries, $entry_schema];
    }
    
    /**
     * Checks if a key is a system key that should be skipped in the schema.
     *
     * @param string $key The field key to check.
     * @return bool
     */
    private function is_system_key( $key ) {
        $key = strtolower( $key );
        return strpos( $key, 'g-recaptcha-response' ) !== false || strpos( $key, 'file' ) !== false;
    }

    /**
     * Builds the final REST response.
     *
     * @param array $formatted_entries The processed entry data.
     * @param array $entry_schema      The data schema.
     * @param int   $total_count       The total number of entries.
     * @param array $params            The request parameters.
     * @return \WP_REST_Response
     */
    private function build_response( $formatted_entries, $entry_schema, $total_count, $params ) {
        $response = rest_ensure_response(
            [
                'entries'      => $formatted_entries,
                'entry_schema' => $entry_schema,
                'total'        => $total_count,
                'page'         => (int) $params['page'],
                'per_page'     => (int) $params['per_page'],
            ]
        );

        return apply_filters( 'fem_get_entries_response', $response, null );
    }
}