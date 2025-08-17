<?php

namespace App\AdvancedEntryManager\Api\Callback;

use App\AdvancedEntryManager\Api\Route;
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
     * Retrieves all entries from the aemfw table.
     *
     * Fetches all rows from the custom entries table, decodes the entry data,
     * and groups entries by form ID.
     *
     * @return \WP_REST_Response List of decoded entries as a REST response.
     */
   public function get_entries(WP_REST_Request $request)
    {
        global $wpdb;

        $table       = Helper::get_table_name();
        $form_id     = absint($request->get_param('form_id'));
        $status      = $request->get_param('status');
        $search      = sanitize_text_field($_GET['search'] ?? '');
        $search_type = sanitize_text_field($_GET['search_type'] ?? 'email');
        $per_page    = absint($request->get_param('per_page')) ?: 50;
        $page        = absint($request->get_param('page')) ?: 1;
        $date_from   = $request->get_param('date_from');
        $date_to     = $request->get_param('date_to');

        // --- Start of Hybrid Pagination Logic ---
        $offset      = ($page - 1) * $per_page;
        $start_id    = null;

        // Construct WHERE clauses and parameters
        $where_clauses = [];
        $params = [];

        // Base condition to ensure a valid WHERE clause
        $where_clauses[] = '1=1';

        if ($form_id) {
            $where_clauses[] = 'form_id = %d';
            $params[] = $form_id;
        }

        if ($status === 'read' || $status === 'unread') {
            $where_clauses[] = 'status = %s';
            $params[] = $status;
        }

        if ($search) {
            switch ($search_type) {
                case 'email':
                    $where_clauses[] = 'email = %s';
                    $params[] = $search;
                    break;
                case 'id':
                    $where_clauses[] = 'id = %d';
                    $params[] = (int) $search;
                    break;
                case 'name':
                    $where_clauses[] = 'name = %s';
                    $params[] = $search;
                    break;
                default:
                    $where_clauses[] = '(name LIKE %s OR entry LIKE %s)';
                    $params[] = '%' . $wpdb->esc_like($search) . '%';
                    $params[] = '%' . $wpdb->esc_like($search) . '%';
                    break;
            }
        }

        if ($date_from) {
            $where_clauses[] = 'created_at >= %s';
            $params[] = $date_from . ' 00:00:00';
        }

        if ($date_to) {
            $where_clauses[] = 'created_at <= %s';
            $params[] = $date_to . ' 23:59:59';
        }

        $where = 'WHERE ' . implode(' AND ', $where_clauses);
        $where = apply_filters('aemfw_get_entries_where', $where, $params, $request);

        // First, get the total count. This query is still needed for pagination button rendering.
        $count_sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM $table $where",
            ...$params
        );
        $total_count = (int) $wpdb->get_var($count_sql);

        // Step 1: Find the ID of the first entry for the requested page.
        // This is the optimized 'offset' query.
        if ($page > 1) {
            $get_id_sql = $wpdb->prepare(
                "SELECT id FROM $table $where ORDER BY created_at DESC LIMIT 1 OFFSET %d",
                ...array_merge($params, [$offset])
            );
            $start_id = $wpdb->get_var($get_id_sql);
        }
        
        // Step 2: Fetch the data using the cursor/start ID.
        // This is the main data query that avoids a large OFFSET.
        $data_sql = "SELECT * FROM $table $where ";
        $data_params = $params;

        if ($page > 1 && $start_id) {
            // Use the found ID as a cursor for the main query.
            // This is the fast part.
            $data_sql .= " AND id <= %d ORDER BY created_at DESC LIMIT %d";
            $data_params[] = $start_id;
            $data_params[] = $per_page;
        } else {
            // For the first page or if start_id is not found, use a simple LIMIT.
            $data_sql .= " ORDER BY created_at DESC LIMIT %d";
            $data_params[] = $per_page;
        }
        
        $sql = $wpdb->prepare($data_sql, ...$data_params);
        $results = $wpdb->get_results($sql);

        // --- The rest of the code remains the same ---
        $data = [];
        foreach ($results as $row) {
            $entry_raw = maybe_unserialize($row->entry);
            $entry_normalized = [];

            if (is_array($entry_raw)) {
                foreach ($entry_raw as $key => $value) {
                    $entry_normalized[ucwords(strtolower($key))] = $value;
                }
            }

            $data[] = [
                'id'          => (int) $row->id,
                'form_title'  => get_the_title($row->form_id),
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
            ];
        }

        $data = apply_filters('aemfw_get_entries_data', $data, $results, $request);

        // Return the response with the total count and cursors for the next page
        $next_last_id = !empty($results) ? end($results)->id : null;
        $next_last_date = !empty($results) ? end($results)->created_at : null;

        /**
         * Action hook to perform cache invalidation after the total count is retrieved.
         * This allows external functions to check for data changes and clear caches.
         *
         * @param int            $total_count  The total number of entries found.
         * @param WP_REST_Request $request      The current REST API request object.
         */
        do_action('aemfw_after_get_total_count', $total_count, $request);

        $this->clear_cache( $total_count, $request );

        $response = rest_ensure_response([
            'entries'    => $data,
            'total'      => $total_count,
            'page'       => $page,
            'per_page'   => $per_page,
            'last_id'    => $next_last_id,
            'last_date'  => $next_last_date,
        ]);

        return apply_filters('aemfw_get_entries_response', $response, $request);
    }


    function clear_cache($total_count, $request) {
        // Generate a unique cache key based on the request parameters.
        // This ensures a different cache is used for each unique query (e.g., different form IDs or filters).
        $cache_key = 'aemfw_entry_count_' . md5(serialize($request->get_params()));
        $cached_count = get_transient($cache_key);

        // If no cached count exists, this is the first time the query is run.
        if ($cached_count === false) {
            // Store the current total count in the cache for future comparison.
            // We use a long expiration time since it's only meant to be cleared manually.
            set_transient($cache_key, $total_count, WEEK_IN_SECONDS);
            return;
        }

        // Compare the current total count with the cached count.
        if ($total_count !== (int)$cached_count) {
            // The counts do not match, which means entries have been added or deleted.
            // We must now clear all related pagination caches to avoid inconsistent results.
            $this->clear_all_pagination_caches();

            // After clearing the caches, update the stored total count.
            set_transient($cache_key, $total_count, WEEK_IN_SECONDS);
        }else{
            Helper::set_error_log( 'The total count is same' );
        }
    }

    function clear_all_pagination_caches() {
        global $wpdb;
        
        // Deletes all pagination cursor transients.
        $delete_cursor_cache = $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_pagination_cursor_%' OR option_name LIKE '_transient_timeout_pagination_cursor_%'");

        // It's also a good practice to clear the count cache you discussed.
        $delete_total_count = $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_aemfw_entry_count_%' OR option_name LIKE '_transient_timeout_aemfw_entry_count_%'");

        if ( $delete_cursor_cache === false ) {
            Helper::set_error_log( 'Cache clear query failed' );
        } elseif ( $delete_cursor_cache === 0 ) {
            Helper::set_error_log( 'No cache found to clear' );
        } else {
            Helper::set_error_log( "Cache cleared, deleted {$delete_cursor_cache} rows" );
        }
    }
}