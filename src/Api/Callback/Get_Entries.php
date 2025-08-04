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
        $offset      = ($page - 1) * $per_page;

        $where_clauses = [];
        $params = [];

        // Base condition
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

        $where = apply_filters('swpfe_get_entries_where', $where, $params, $request);

        $sql = $wpdb->prepare(
            "SELECT * FROM $table $where ORDER BY created_at DESC LIMIT %d OFFSET %d",
            ...array_merge($params, [$per_page, $offset])
        );

        $results = $wpdb->get_results($sql);

        $count_sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM $table $where",
            ...$params
        );
        $total_count = (int) $wpdb->get_var($count_sql);

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

        $data = apply_filters('swpfe_get_entries_data', $data, $results, $request);

        $response = rest_ensure_response([
            'entries' => $data,
            'total'   => $total_count,
            'page'    => $page,
            'per_page' => $per_page,
        ]);

        return apply_filters('swpfe_get_entries_response', $response, $request);
    }
}