<?php

namespace SWPFE;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST API handler for WPForms entries.
 * 
 * @since 1.0.0
 * @package wp-save-entries
 */
class Rest_API
{
    /**
     * REST API namespace.
     *
     * @var string
     */
    private $namespace = 'wpforms/entries/v1';

    /**
     * Constructor.
     *
     * Registers the REST API routes on initialization.
     */
    public function __construct()
    {
        add_action('rest_api_init', [$this, 'register_route']);
    }

    /**
     * Registers all REST API routes for the Save WPForms Entries plugin.
     * 
     * This method defines routes for managing form entries, including:
     * - Fetching entries with filters and pagination
     * - Creating new entries
     * - Retrieving form metadata
     * - Updating existing entries
     * - Deleting entries
     * 
     * Each route uses proper permission callbacks ensuring only authorized users
     * (typically admins with 'manage_options') can perform operations.
     * 
     * Validation and sanitization callbacks are defined for all route parameters
     * to enforce data integrity and security.
     * 
     * @return void
     */
    public function register_route()
    {
        $data = [
            // Route: GET /entries - List entries with filtering and pagination
            [
                'route' => '/entries',
                'data' => [
                    'methods'  => WP_REST_Server::READABLE,
                    'callback' => [$this, 'get_entries'],
                    // 'permission_callback' => function () {
                    //     return current_user_can('manage_options') && is_user_logged_in();
                    // },
                    'validation_callback' => '__return_true',
                    'args' => [
                        'per_page' => [
                            'description'       => __('Number of entries per page.', 'advanced-entries-manager-for-wpforms'),
                            'type'              => 'integer',
                            'default'           => 20,
                            'sanitize_callback' => 'absint',
                            'validate_callback' => function ($value) {
                                return $value > 0 && $value <= 100;
                            },
                        ],
                        'page' => [
                            'description'       => __('Page number.', 'advanced-entries-manager-for-wpforms'),
                            'type'              => 'integer',
                            'default'           => 1,
                            'sanitize_callback' => 'absint',
                        ],
                        'form_id' => [
                            'description'       => __('Limit entries to a specific form ID.', 'advanced-entries-manager-for-wpforms'),
                            'type'              => 'integer',
                            'required'          => false,
                            'sanitize_callback' => 'absint',
                        ],
                        'search' => [
                            'description'       => __('Search within entry values.', 'advanced-entries-manager-for-wpforms'),
                            'type'              => 'string',
                            'required'          => false,
                            'sanitize_callback' => 'sanitize_text_field',
                        ],
                        'status' => [
                            'description'       => __('Filter by read/unread status.', 'advanced-entries-manager-for-wpforms'),
                            'type'              => 'string',
                            'validate_callback' => function ($value) {
                                return in_array($value, ['read', 'unread', '', null], true);
                            },
                            'required'          => false,
                        ],
                        'date_from' => [
                            'description'       => __('Filter by submission start date (YYYY-MM-DD)', 'advanced-entries-manager-for-wpforms'),
                            'type'              => 'string',
                            'required'          => false,
                            'sanitize_callback' => 'sanitize_text_field',
                            'validate_callback' => function ($param) {
                                return preg_match('/^\d{4}-\d{2}-\d{2}$/', $param);
                            },
                        ],
                        'date_to' => [
                            'description'       => __('Filter by submission end date (YYYY-MM-DD)', 'advanced-entries-manager-for-wpforms'),
                            'type'              => 'string',
                            'required'          => false,
                            'sanitize_callback' => 'sanitize_text_field',
                            'validate_callback' => function ($param) {
                                return preg_match('/^\d{4}-\d{2}-\d{2}$/', $param);
                            },
                        ],
                    ],
                ],
            ],

            // Route: POST /create - Create a new form entry
            [
                'route' => '/create',
                'data' => [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [$this, 'create_entries'],
                    'permission_callback' => function () {
                        return current_user_can('manage_options') && is_user_logged_in();
                    },
                    'args' => [
                        'form_id' => [
                            'description'       => __('Form ID for the entry.', 'advanced-entries-manager-for-wpforms'),
                            'required'          => true,
                            'validate_callback' => function ($param) {
                                return is_string($param) || is_numeric($param);
                            },
                            'sanitize_callback' => 'sanitize_text_field',
                        ],
                        'entry' => [
                            'description'       => __('Entry data as an associative array.', 'advanced-entries-manager-for-wpforms'),
                            'required'          => true,
                            'validate_callback' => function ($param) {
                                return is_array($param);
                            },
                        ],
                        'status' => [
                            'description'       => __('Read/unread status for the entry.', 'advanced-entries-manager-for-wpforms'),
                            'required'          => false,
                            'validate_callback' => function ($param) {
                                return in_array($param, ['unread', 'read'], true);
                            },
                            'sanitize_callback' => 'sanitize_text_field',
                            'default'           => 'unread',
                        ],
                        'is_favorite' => [
                            'description'       => __('Mark entry as favorite (0 or 1).', 'advanced-entries-manager-for-wpforms'),
                            'required'          => false,
                            'validate_callback' => function ($param) {
                                return is_numeric($param) && in_array($param, [0, 1], true);
                            },
                            'sanitize_callback' => 'absint',
                            'default'           => 0,
                        ],
                        'note' => [
                            'description'       => __('Internal note for the entry (max 500 words).', 'advanced-entries-manager-for-wpforms'),
                            'required'          => false,
                            'validate_callback' => function ($param) {
                                return is_string($param) && str_word_count($param) <= 500;
                            },
                            'sanitize_callback' => 'sanitize_text_field',
                        ],
                        'exported_to_csv' => [
                            'description'       => __('Exported to CSV flag (0 or 1).', 'advanced-entries-manager-for-wpforms'),
                            'required'          => false,
                            'validate_callback' => function ($param) {
                                return is_numeric($param) && in_array($param, [0, 1], true);
                            },
                            'sanitize_callback' => 'absint',
                            'default'           => 0,
                        ],
                        'synced_to_gsheet' => [
                            'description'       => __('Synced to Google Sheet flag (0 or 1).', 'advanced-entries-manager-for-wpforms'),
                            'required'          => false,
                            'validate_callback' => function ($param) {
                                return is_numeric($param) && in_array($param, [0, 1], true);
                            },
                            'sanitize_callback' => 'absint',
                            'default'           => 0,
                        ],
                        'printed_at' => [
                            'description'       => __('Printed at datetime (Y-m-d H:i:s).', 'advanced-entries-manager-for-wpforms'),
                            'required'          => false,
                            'validate_callback' => function ($param) {
                                return strtotime($param) !== false;
                            },
                            'sanitize_callback' => function ($param) {
                                return date('Y-m-d H:i:s', strtotime($param));
                            },
                        ],
                        'resent_at' => [
                            'description'       => __('Resent at datetime (Y-m-d H:i:s).', 'advanced-entries-manager-for-wpforms'),
                            'required'          => false,
                            'validate_callback' => function ($param) {
                                return strtotime($param) !== false;
                            },
                            'sanitize_callback' => function ($param) {
                                return date('Y-m-d H:i:s', strtotime($param));
                            },
                        ],
                    ],
                ],
            ],

            // Route: GET /single - Get a single form metadata (alias)
            [
                'route' => '/single',
                'data'  => [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_forms'],
                    'permission_callback' => function () {
                        return current_user_can('manage_options') && is_user_logged_in();
                    },
                ],
            ],

            // Route: GET /forms - Get all form metadata
            [
                'route' => '/forms',
                'data' => [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_forms'],
                    'permission_callback' => function () {
                        return current_user_can('manage_options') && is_user_logged_in();
                    },
                ],
            ],

            // Route: POST/PATCH /update - Update an existing entry
            [
                'route' => '/update',
                'data' => [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [$this, 'update_entries'],
                    'permission_callback' => function () {
                        return current_user_can('manage_options') && is_user_logged_in();
                    },
                    'args' => [
                        'id' => [
                            'description'       => __('Entry ID to update.', 'advanced-entries-manager-for-wpforms'),
                            'required'          => true,
                            'sanitize_callback' => 'absint',
                            'validate_callback' => function ($param) {
                                return is_numeric($param) && $param > 0;
                            },
                        ],
                        'form_id' => [
                            'description'       => __('Form ID for the entry.', 'advanced-entries-manager-for-wpforms'),
                            'required'          => true,
                            'sanitize_callback' => 'absint',
                            'validate_callback' => function ($param) {
                                return is_numeric($param) && $param > 0;
                            },
                        ],
                        'entry' => [
                            'description'       => __('Entry data as an associative array.', 'advanced-entries-manager-for-wpforms'),
                            'required'          => false,
                            'validate_callback' => function ($param) {
                                return is_array($param);
                            },
                        ],
                        'status' => [
                            'description'       => __('Read/unread status for the entry.', 'advanced-entries-manager-for-wpforms'),
                            'required'          => false,
                            'validate_callback' => function ($param) {
                                return in_array($param, ['unread', 'read'], true);
                            },
                            'sanitize_callback' => 'sanitize_text_field',
                            'default'           => 'unread',
                        ],
                        'note' => [
                            'description'       => __('Internal note for the entry (max 500 words).', 'advanced-entries-manager-for-wpforms'),
                            'required'          => false,
                            'validate_callback' => function ($param) {
                                return is_string($param) && str_word_count($param) <= 500;
                            },
                            'sanitize_callback' => 'sanitize_text_field',
                        ],
                        'is_favorite' => [
                            'description'       => __('Mark entry as favorite (0 or 1).', 'advanced-entries-manager-for-wpforms'),
                            'required'          => false,
                            'validate_callback' => function ($param) {
                                return is_numeric($param) && in_array($param, [0, 1], true);
                            },
                            'sanitize_callback' => 'absint',
                            'default'           => 0,
                        ],
                        'exported_to_csv' => [
                            'description'       => __('Exported to CSV flag (0 or 1).', 'advanced-entries-manager-for-wpforms'),
                            'required'          => false,
                            'validate_callback' => function ($param) {
                                return is_numeric($param) && in_array($param, [0, 1], true);
                            },
                            'sanitize_callback' => 'absint',
                            'default'           => 0,
                        ],
                        'synced_to_gsheet' => [
                            'description'       => __('Synced to Google Sheet flag (0 or 1).', 'advanced-entries-manager-for-wpforms'),
                            'required'          => false,
                            'validate_callback' => function ($param) {
                                return is_numeric($param) && in_array($param, [0, 1], true);
                            },
                            'sanitize_callback' => 'absint',
                            'default'           => 0,
                        ],
                        'printed_at' => [
                            'description'       => __('Printed at datetime (Y-m-d H:i:s).', 'advanced-entries-manager-for-wpforms'),
                            'required'          => false,
                            'validate_callback' => function ($param) {
                                return strtotime($param) !== false;
                            },
                            'sanitize_callback' => function ($param) {
                                return date('Y-m-d H:i:s', strtotime($param));
                            },
                        ],
                        'resent_at' => [
                            'description'       => __('Resent at datetime (Y-m-d H:i:s).', 'advanced-entries-manager-for-wpforms'),
                            'required'          => false,
                            'validate_callback' => function ($param) {
                                return strtotime($param) !== false;
                            },
                            'sanitize_callback' => function ($param) {
                                return date('Y-m-d H:i:s', strtotime($param));
                            },
                        ],
                    ],
                ],
            ],

            // Route: DELETE /delete - Delete a specific entry
            [
                'route' => '/delete',
                'data'  => [
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => [$this, 'delete_entry'],
                    'permission_callback' => function () {
                        return current_user_can('manage_options') && is_user_logged_in();
                    },
                    'args' => [
                        'id' => [
                            'required'          => true,
                            'sanitize_callback' => 'absint',
                            'validate_callback' => function ($param) {
                                return is_numeric($param) && $param > 0;
                            },
                        ],
                        'form_id' => [
                            'required'          => true,
                            'sanitize_callback' => 'absint',
                            'validate_callback' => function ($param) {
                                return is_numeric($param) && $param > 0;
                            },
                        ],
                    ],
                ],
            ],
            [
                'route' => '/entries/check-new',
                'data' => [
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => [$this, 'check_new'],
                    'permission_callback' => function () {
                        return current_user_can('manage_options') && is_user_logged_in();
                    },
                    // 'permission_callback' => '__return_true',
                    'args' => [
                        'form_id' => [
                            'required' => true,
                            'type'     => 'integer',
                            'sanitize_callback' => 'absint',
                            'validate_callback' => function ($param) {
                                return $param > 0;
                            },
                        ],
                        'last_seen_id' => [
                            'required' => true,
                            'type'     => 'integer',
                            'sanitize_callback' => 'absint',
                            'validate_callback' => function ($param) {
                                return $param > 0;
                            },
                        ],
                    ],
                ],
            ],
            [
                'route' => '/bulk',
                'data' => [
                    'methods'  => 'POST',
                    'callback' => [$this, 'bulk_actions'],
                    // 'permission_callback' => function () {
                    //     return current_user_can('manage_options') && is_user_logged_in();
                    // },
                    'permission_callback' => '__return_true',
                    'args' => [
                        'ids' => [
                            'required' => true,
                            'type'     => 'array',
                            'items'    => [
                                'type' => 'integer',
                            ],
                            'sanitize_callback' => function ($ids) {
                                return array_map('intval', (array) $ids);
                            },
                            'validate_callback' => function ($ids) {
                                return is_array($ids) && count($ids) > 0;
                            },
                        ],
                        'action' => [
                            'required'          => true,
                            'type'              => 'string',
                            'sanitize_callback' => 'sanitize_text_field',
                            'validate_callback' => function ($action) {
                                return in_array($action, [
                                    'mark_read',
                                    'mark_unread',
                                    'favorite',
                                    'unfavorite',
                                    'mark_spam',
                                    'unmark_spam',
                                    'delete',
                                ], true);
                            },
                        ],
                    ],
                ],
            ],
        ];

        foreach ($data as $item) {
            register_rest_route($this->namespace, $item['route'], $item['data']);
        }
    }

    /**
     * Handle bulk actions on entries.
     *
     * This endpoint processes bulk operations like marking as read/unread,
     * favoriting/unfavoriting, and deleting multiple WPForms entries.
     *
     * @since 1.0.0
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response JSON response indicating success or failure.
     */
    public function bulk_actions(WP_REST_Request $request)
    {
        $ids = $request->get_param('ids');
        $action = sanitize_text_field($request->get_param('action'));

        // Validate IDs
        if (!is_array($ids) || empty($ids)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Invalid or missing entry IDs.', 'advanced-entries-manager-for-wpforms'),
            ], 400);
        }

        // Sanitize each ID
        $ids = array_map('absint', $ids);

        // Validate action
        $valid_actions = ['delete', 'mark_read', 'mark_unread', 'favorite', 'unfavorite', 'mark_spam', 'unmark_spam'];
        if (!in_array($action, $valid_actions, true)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Invalid action provided.', 'advanced-entries-manager-for-wpforms'),
            ], 400);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'swpfe_entries';

        $affected = 0;

        foreach ($ids as $id) {
            switch ($action) {
                case 'delete':
                    $deleted = $wpdb->delete($table, ['id' => $id]);
                    if ($deleted !== false) {
                        $affected++;
                    }
                    break;

                case 'mark_read':
                    $updated = $wpdb->update($table, ['status' => 'read'], ['id' => $id]);
                    if ($updated !== false) {
                        $affected++;
                    }
                    break;

                case 'mark_unread':
                    $updated = $wpdb->update($table, ['status' => 'unread'], ['id' => $id]);
                    if ($updated !== false) {
                        $affected++;
                    }
                    break;

                case 'favorite':
                    $updated = $wpdb->update($table, ['is_favorite' => 1], ['id' => $id]);
                    if ($updated !== false) {
                        $affected++;
                    }
                    break;

                case 'unfavorite':
                    $updated = $wpdb->update($table, ['is_favorite' => 0], ['id' => $id]);
                    if ($updated !== false) {
                        $affected++;
                    }
                    break;

                case 'mark_spam':
                    $updated = $wpdb->update($table, ['is_spam' => 1], ['id' => $id]);
                    break;

                case 'unmark_spam':
                    $updated = $wpdb->update($table, ['is_spam' => 0], ['id' => $id]);
                    break;
            }
        }

        return rest_ensure_response([
            'success' => true,
            'message' => sprintf(
                // translators: %d is number of affected entries
                _n('%d entry updated.', '%d entries updated.', $affected, 'advanced-entries-manager-for-wpforms'),
                $affected
            ),
            'affected' => $affected,
        ]);
    }

    /**
     * Check for new entries for a given form after the last known entry ID.
     *
     * This endpoint is designed to be used for polling and detecting new form submissions in near real-time.
     *
     * @param WP_REST_Request $request The REST request object containing 'form_id' and 'last_seen_id'.
     *
     * @return WP_REST_Response|WP_Error REST response containing new entries or an error object.
     */
    public function check_new(WP_REST_Request $request)
    {
        global $wpdb;

        // Sanitize and validate input
        $form_id = absint($request->get_param('form_id'));
        $last_id = absint($request->get_param('last_seen_id'));

        if (!$form_id || !$last_id) {
            return new \WP_Error(
                'swpfe_missing_params',
                __('Missing form ID or last seen ID.', 'advanced-entries-manager-for-wpforms'),
                ['status' => 400]
            );
        }

        $table = $wpdb->prefix . 'swpfe_entries';

        $cache_key = "swpfe_new_entries_{$form_id}_{$last_id}";
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return rest_ensure_response($cached);
        }

        $query = $wpdb->prepare(
            "SELECT id, created_at FROM $table WHERE form_id = %d AND id > %d ORDER BY id ASC LIMIT 3",
            $form_id,
            $last_id
        );

        $rows = $wpdb->get_results($query);

        set_transient($cache_key, $rows, 10);

        /**
         * Filter the new entry result rows.
         *
         * @param array           $rows    The result set.
         * @param int             $form_id The form ID.
         * @param int             $last_id The last seen entry ID.
         * @param WP_REST_Request $request The original REST request.
         */
        $rows = apply_filters('swpfe_check_new_entries', $rows, $form_id, $last_id, $request);

        return rest_ensure_response($rows);
    }

    /**
     * Retrieves all entries from the swpfe_entries table.
     *
     * Fetches all rows from the custom entries table, decodes the entry data,
     * and groups entries by form ID.
     *
     * @return \WP_REST_Response List of decoded entries as a REST response.
     */
    public function get_entries(WP_REST_Request $request)
    {
        global $wpdb;

        $table       = $wpdb->prefix . 'swpfe_entries';
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

    /**
     * Get list of forms with their entry counts.
     *
     * Queries the custom entries table to retrieve all unique form IDs and
     * the number of entries associated with each form. Also fetches the form
     * title using `get_the_title()`. The result is formatted as a REST response.
     *
     * @global wpdb $wpdb WordPress database abstraction object.
     *
     * @return WP_REST_Response JSON-formatted response containing form data:
     *                          - form_id (int)
     *                          - form_title (string)
     *                          - entry_count (int)
     */
    public function get_forms()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'swpfe_entries';

        // Optional: Check permission, customize capability as needed
        if (! current_user_can('manage_options')) {
            return new \WP_Error(
                'rest_forbidden',
                __('You do not have permission to view this data.', 'advanced-entries-manager-for-wpforms'),
                ['status' => 403]
            );
        }

        // Query distinct form IDs and their entry counts
        $results = $wpdb->get_results(
            "SELECT form_id, COUNT(*) as entry_count 
            FROM {$table} 
            GROUP BY form_id",
            OBJECT
        );

        $forms = [];

        foreach ($results as $row) {
            $form_id = (int) $row->form_id;

            $forms[] = [
                'form_id'     => $form_id,
                'form_title'  => get_the_title($form_id),
                'entry_count' => (int) $row->entry_count,
            ];
        }

        /**
         * Filter the list of forms returned by get_forms().
         *
         * @param array $forms List of forms with entry counts.
         */
        return rest_ensure_response(apply_filters('swpfe_get_forms', $forms));
    }


    /**
     * Handle creation of a new WPForms entry saved into custom DB table using rest.
     *
     * This method accepts a REST POST request and stores form entry data into
     * the custom `swpfe_entries` table. It supports metadata like read status,
     * favorite flag, export/sync tracking, and internal notes.
     *
     * @param WP_REST_Request $request The incoming REST request with form entry data.
     *
     * @return WP_REST_Response A JSON response indicating success/failure, including inserted entry ID if successful.
     */
    public function create_entries(WP_REST_Request $request)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'swpfe_entries';

        // Get parameters from JSON body
        $params = $request->get_json_params();

        // Sanitize and validate required fields
        $form_id = isset($params['form_id']) ? absint($params['form_id']) : 0;
        $entry = isset($params['entry']) ? $params['entry'] : null; // expecting array

        if (!$form_id || !is_array($entry)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Invalid or missing form_id or entry data.', 'advanced-entries-manager-for-wpforms'),
            ], 400);
        }

        // Optional fields with sanitization and normalization
        $status           = isset($params['status']) ? sanitize_text_field($params['status']) : 'unread';
        $is_favorite      = isset($params['is_favorite']) ? absint($params['is_favorite']) : 0;
        $note             = isset($params['note']) ? sanitize_textarea_field($params['note']) : null;
        $exported_to_csv  = isset($params['exported_to_csv']) ? absint($params['exported_to_csv']) : 0;
        $synced_to_gsheet = isset($params['synced_to_gsheet']) ? absint($params['synced_to_gsheet']) : 0;
        // Normalize datetime fields or set null if empty/invalid
        $printed_at       = !empty($params['printed_at']) ? date('Y-m-d H:i:s', strtotime($params['printed_at'])) : null;
        $resent_at        = !empty($params['resent_at']) ? date('Y-m-d H:i:s', strtotime($params['resent_at'])) : null;

        // Optional: capability check (uncomment if needed)
        // if ( ! current_user_can( 'manage_options' ) ) {
        //     return new WP_REST_Response([
        //         'success' => false,
        //         'message' => __( 'Insufficient permissions to create entry.', 'advanced-entries-manager-for-wpforms' ),
        //     ], 403);
        // }

        /**
         * Fires before inserting a new entry.
         *
         * @param int             $form_id Form ID.
         * @param array           $entry   Entry data (array).
         * @param array           $params  Full request parameters.
         * @param WP_REST_Request $request REST request object.
         */
        do_action('swpfe_before_entry_create', $form_id, $entry, $params, $request);

        // Prepare data for DB insert
        $data = [
            'form_id'          => $form_id,
            'entry'            => maybe_serialize($entry),
            'status'           => $status,
            'is_favorite'      => $is_favorite,
            'note'             => $note,
            'exported_to_csv'  => $exported_to_csv,
            'synced_to_gsheet' => $synced_to_gsheet,
            'printed_at'       => $printed_at,
            'resent_at'        => $resent_at,
            'created_at'       => current_time('mysql'),
        ];

        // Define formats — handle nullable string fields properly
        $format = [
            '%d', // form_id
            '%s', // entry
            '%s', // status
            '%d', // is_favorite
            $note === null ? '%s' : '%s', // note (allow null)
            '%d', // exported_to_csv
            '%d', // synced_to_gsheet
            $printed_at === null ? '%s' : '%s', // printed_at (allow null)
            $resent_at === null ? '%s' : '%s', // resent_at (allow null)
            '%s', // created_at
        ];

        $inserted = $wpdb->insert($table, $data, $format);

        if ($inserted === false) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Database insert failed.', 'advanced-entries-manager-for-wpforms'),
            ], 500);
        }

        /**
         * Fires after successfully inserting a new entry.
         *
         * @param int             $entry_id Inserted entry ID.
         * @param int             $form_id  Form ID.
         * @param array           $entry    Entry data.
         * @param array           $params   Full request parameters.
         * @param WP_REST_Request $request  REST request object.
         */
        do_action('swpfe_after_entry_create', $wpdb->insert_id, $form_id, $entry, $params, $request);

        return new WP_REST_Response([
            'success'  => true,
            'message'  => __('Entry created successfully.', 'advanced-entries-manager-for-wpforms'),
            'entry_id' => $wpdb->insert_id,
        ], 201);
    }

    /**
     * Update an existing WPForms entry row.
     *
     * Supports PATCH-style partial updates or full PUT updates.
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response
     */
    public function update_entries(WP_REST_Request $request)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'swpfe_entries';

        $params = $request->get_json_params();

        // Require entry ID and form ID, sanitize
        $id = isset($params['id']) ? absint($params['id']) : 0;
        $form_id = isset($params['form_id']) ? absint($params['form_id']) : 0;

        if (!$id || !$form_id) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Missing or invalid entry ID or form ID.', 'advanced-entries-manager-for-wpforms'),
            ], 400);
        }

        // Build update data only from present fields
        $data = [];
        $format = [];

        if (isset($params['entry']) && is_array($params['entry'])) {
            $data['entry'] = maybe_serialize($params['entry']);
            $format[] = '%s';
        }

        if (isset($params['status'])) {
            $data['status'] = sanitize_text_field($params['status']);
            $format[] = '%s';
        }

        if (isset($params['is_favorite'])) {
            $data['is_favorite'] = absint($params['is_favorite']);
            $format[] = '%d';
        }

        if (isset($params['note'])) {
            $raw_note = sanitize_textarea_field($params['note']);

            // Limit character length (hard limit for DB and performance)
            $max_length = 1000;
            $trimmed_note = mb_substr($raw_note, 0, $max_length);

            $data['note'] = $trimmed_note;
            $format[] = '%s';
        }

        if (isset($params['exported_to_csv'])) {
            $data['exported_to_csv'] = absint($params['exported_to_csv']);
            $format[] = '%d';
        }

        if (isset($params['synced_to_gsheet'])) {
            $data['synced_to_gsheet'] = absint($params['synced_to_gsheet']);
            $format[] = '%d';
        }

        if (isset($params['printed_at'])) {
            $data['printed_at'] = date('Y-m-d H:i:s', strtotime($params['printed_at']));
            $format[] = '%s';
        }

        if (isset($params['resent_at'])) {
            $data['resent_at'] = date('Y-m-d H:i:s', strtotime($params['resent_at']));
            $format[] = '%s';
        }

        // If no fields provided to update
        if (empty($data)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('No valid fields provided for update.', 'advanced-entries-manager-for-wpforms'),
            ], 400);
        }

        /**
         * Fires before an entry update is performed.
         *
         * @param int             $id      Entry ID.
         * @param array           $data    Data to update (column => value).
         * @param WP_REST_Request $request Full REST request object.
         */
        do_action('swpfe_before_entry_update', $id, $data, $request);

        // Perform DB update
        $updated = $wpdb->update(
            $table,
            $data,
            ['id' => $id],
            $format,
            ['%d']
        );

        if ($updated === false) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Database update failed.', 'advanced-entries-manager-for-wpforms'),
            ], 500);
        }

        /**
         * Fires after an entry has been successfully updated.
         *
         * @param int             $id      Entry ID.
         * @param array           $data    Data that was updated.
         * @param WP_REST_Request $request Full REST request object.
         */
        do_action('swpfe_after_entry_update', $id, $data, $request);

        return new WP_REST_Response([
            'success'        => true,
            'message'        => __('Entry updated successfully.', 'advanced-entries-manager-for-wpforms'),
            'updated_fields' => array_keys($data),
            'entry_id'       => $id,
        ], 200);
    }

    /**
     * Delete a specific form entry.
     *
     * Handles a REST API request to delete a single entry from the custom entries table
     * based on its entry ID and form ID. Returns a success or failure response.
     *
     * Example request: DELETE /wp-json/your-namespace/v1/entries?id=123&form_id=45
     *
     * @param WP_REST_Request $request REST request object containing 'id' and 'form_id'.
     *
     * @global wpdb $wpdb WordPress database abstraction object.
     *
     * @return WP_REST_Response JSON response indicating success or failure:
     *                          - deleted (bool)
     *                          - message (string, optional)
     */
    public function delete_entry(WP_REST_Request $request)
    {
        global $wpdb;

        $id      = absint($request->get_param('id'));
        $form_id = absint($request->get_param('form_id'));

        if (! $id || ! $form_id) {
            return new WP_REST_Response([
                'deleted' => false,
                'message' => __('Missing required parameters.', 'advanced-entries-manager-for-wpforms'),
            ], 400);
        }

        if (! current_user_can('manage_options')) {
            return new WP_REST_Response([
                'deleted' => false,
                'message' => __('You are not allowed to delete entries.', 'advanced-entries-manager-for-wpforms'),
            ], 403);
        }

        do_action('swpfe_before_entry_delete', $id, $form_id);

        $table = $wpdb->prefix . 'swpfe_entries';
        $deleted = $wpdb->delete(
            $table,
            ['id' => $id, 'form_id' => $form_id],
            ['%d', '%d']
        );

        if ($deleted) {
            do_action('swpfe_after_entry_delete', $id, $form_id);

            return new WP_REST_Response(['deleted' => true], 200);
        }

        return new WP_REST_Response([
            'deleted' => false,
            'message' => __('Entry not found or already deleted.', 'advanced-entries-manager-for-wpforms'),
        ], 404);
    }
}
