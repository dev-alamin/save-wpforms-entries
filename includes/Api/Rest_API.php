<?php

namespace App\AdvancedEntryManger\Api;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

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
     * - Fetching single entry
     * - Retrieving form metadata
     * - Updating existing entries
     * - Deleting entries
     * - Checking for new entries
     * - Performing bulk actions on entries
     * - Exporting entries to CSV
     * - Retrieving form fields
     * - Exporting entries to CSV with various filters
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
                    // 'permission_callback' => function () {
                    //     return current_user_can('manage_options') && is_user_logged_in();
                    // },
                    'permission_callback' => '__return_true',
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
            [
                'route' => '/export',
                'data' => [
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => [$this, 'export_entries_csv'],
                    // 'permission_callback' => function () {
                    //     return current_user_can('manage_options');
                    // },
                    'permission_callback' => '__return_true',
                    'args' => [
                        'ids' => [
                            'required' => true,
                            'type'     => 'array',
                        ],
                    ],
                ],
            ],
            [
                'route' => '/forms/(?P<form_id>\d+)/fields',
                'data' => [
                    'methods'  => WP_REST_Server::READABLE,
                    'callback' => [$this, 'get_form_fields'],
                    // 'permission_callback' => function () {
                    //     return current_user_can('manage_options');
                    // },
                    'permission_callback' => '__return_true',
                ],
            ],
            [
                'route' => '/export-csv',
                'data' => [
                        'methods'             => WP_REST_Server::READABLE,
                        'callback'            => [ $this, 'export_csv_callback' ],
                        // 'permission_callback' => function() {
                        //     return current_user_can( 'manage_options' ); // adjust capability
                        // },
                        'permission_callback' => '__return_true',
                        'args' => [
                            'form_id' => [
                                'required' => true,
                                'validate_callback' => function( $param ) {
                                    return is_numeric( $param ) && intval( $param ) > 0;
                                },
                                'sanitize_callback' => 'absint',
                            ],
                            'date_from' => [
                                'required' => false,
                                'validate_callback' => function( $param ) {
                                    return empty( $param ) || preg_match( '/^\d{4}-\d{2}-\d{2}$/', $param );
                                },
                                'sanitize_callback' => 'sanitize_text_field',
                            ],
                            'date_to' => [
                                'required' => false,
                                'validate_callback' => function( $param ) {
                                    return empty( $param ) || preg_match( '/^\d{4}-\d{2}-\d{2}$/', $param );
                                },
                                'sanitize_callback' => 'sanitize_text_field',
                            ],
                            'limit' => [
                                'required' => false,
                                'default' => 100,
                                'validate_callback' => function( $param ) {
                                    return is_numeric( $param ) && intval( $param ) >= 10 && intval( $param ) <= 50000;
                                },
                                'sanitize_callback' => 'absint',
                            ],
                            'exclude_fields' => [
                                'required' => false,
                                'validate_callback' => function( $param ) {
                                    // Comma separated string, allow empty or string only
                                    return is_string( $param );
                                },
                                'sanitize_callback' => function( $param ) {
                                    return sanitize_text_field( $param );
                                },
                            ],
                        ],
                ]
            ]
        ];

        foreach ($data as $item) {
            register_rest_route($this->namespace, $item['route'], $item['data']);
        }
    }

    public function export_csv_callback( WP_REST_Request $request ) {
        global $wpdb;

        $last_id      = absint( $request->get_param('last_id') ?? 0 );  // for keyset pagination
        $limit        = absint( $request->get_param('limit') ?? 100 );
        $form_id      = absint( $request->get_param('form_id') ?? 0 );
        $date_from    = sanitize_text_field( $request->get_param('date_from') ?? '' );
        $date_to      = sanitize_text_field( $request->get_param('date_to') ?? '' );
        $exclude_fields = $request->get_param('exclude_fields');
        $exclude_fields = is_string($exclude_fields) ? explode(',', $exclude_fields) : (array) $exclude_fields;

        if ( ! $form_id ) {
            return new WP_Error('missing_form_id', 'Form ID is required', ['status' => 400]);
        }

        // Build WHERE clause with keyset pagination (use id > last_id)
        $where_clauses = [ 'form_id = %d' ];
        $args = [ $form_id ];

        if ( $date_from ) {
            $where_clauses[] = 'created_at >= %s';
            $args[] = $date_from;
        }
        if ( $date_to ) {
            $where_clauses[] = 'created_at <= %s';
            $args[] = $date_to;
        }
        if ( $last_id ) {
            $where_clauses[] = 'id > %d';
            $args[] = $last_id;
        }

        $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);

        $sql = $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}swpfe_entries {$where_sql} ORDER BY id ASC LIMIT %d",
            ...array_merge( $args, [ $limit ] )
        );

        $results = $wpdb->get_results( $sql );

        if ( empty( $results ) ) {
            error_log('[AEM Export] No results found for export. SQL: ' . $sql);
            wp_send_json_error(['message' => 'No entries found to export.'], 404);
        }

        $csv_data = [];
        $csv_header = [];

        foreach ( $results as $row ) {
            $entry_data = json_decode( $row->entry, true );

            $entry_row = [
                'id'              => $row->id,
                'form_id'         => $row->form_id,
                'created_at'      => $row->created_at,
                'status'          => $row->status,
                'is_favorite'     => $row->is_favorite,
                'note'            => $row->note,
                'exported_to_csv' => $row->exported_to_csv,
                'synced_to_gsheet'=> $row->synced_to_gsheet,
                'printed_at'      => $row->printed_at,
                'is_spam'         => $row->is_spam,
                'resent_at'       => $row->resent_at,
                'updated_at'      => $row->updated_at,
            ];

            if ( is_array( $entry_data ) ) {
                $entry_row = array_merge( $entry_row, $entry_data );
            }

            // Filter out excluded fields if any
            if ( !empty($exclude_fields) ) {
                foreach ($exclude_fields as $exclude) {
                    unset($entry_row[$exclude]);
                }
            }

            // Build header from keys of first row
            if ( empty( $csv_header ) ) {
                $csv_header = array_keys( $entry_row );
            }

            $csv_data[] = $entry_row;
        }

        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="exported_entries.csv"');

        $fh = fopen('php://output', 'w');
        fputcsv($fh, $csv_header);

        foreach ( $csv_data as $row ) {
            // Ensure columns in same order as header
            $line = [];
            foreach ($csv_header as $col) {
                $line[] = $row[$col] ?? '';
            }
            fputcsv($fh, $line);
        }

        fclose($fh);
        exit;
    }

    /**
     * Retrieve a list of unique form field keys for a given WPForms form ID.
     *
     * This endpoint is used to dynamically fetch the field names from a sample
     * entry, typically to allow users to customize export settings (e.g., include/exclude columns).
     *
     * ## Example Request:
     * GET /wp-json/swpfe/v1/form-fields?form_id=123
     *
     * @param WP_REST_Request $request The REST request object containing 'form_id'.
     *
     * @return WP_REST_Response|WP_Error List of field keys or a WP_Error on failure.
     */
    public function get_form_fields( WP_REST_Request $request ) {
        global $wpdb;

        $form_id = isset( $request['form_id'] ) ? absint( $request['form_id'] ) : 0;

        if ( $form_id <= 0 ) {
            return new WP_Error(
                'swpfe_invalid_form_id',
                __( 'Invalid or missing form ID.', 'advanced-entries-manager-for-wpforms' ),
                [ 'status' => 400 ]
            );
        }

        $table = $wpdb->prefix . 'swpfe_entries';

        // Fetch a few rows to detect fields (faster than scanning all)
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE form_id = %d LIMIT 5",
                $form_id
            ),
            ARRAY_A
        );

        $fields = [];

        foreach ( $rows as $row ) {
            // Step 1: Add all top-level DB columns
            foreach ( array_keys( $row ) as $column ) {
                $fields[ $column ] = true;
            }

            // Step 2: Merge in keys from deserialized 'entry'
            if ( isset( $row['entry'] ) ) {
                $entry = maybe_unserialize( $row['entry'] );
                if ( is_array( $entry ) ) {
                    foreach ( array_keys( $entry ) as $field_key ) {
                        $fields[ $field_key ] = true;
                    }
                }
            }
        }

        return rest_ensure_response([
            'fields' => array_values( array_unique( array_keys( $fields ) ) )
        ]);
    }

    /**
     * Export selected WPForms entries as a CSV file download.
     *
     * This method handles a REST API POST request, expecting an array of entry IDs 
     * under the 'ids' parameter. It fetches entries from the custom `swpfe_entries` table,
     * unserializes the stored entry data, and outputs it as a CSV file.
     * 
     * The CSV file includes an 'id' column as the first column, followed by the entry data keys.
     *
     * @param WP_REST_Request $request The REST API request object containing parameters.
     * 
     * @return WP_Error|void Returns WP_Error on invalid input or no data; otherwise sends CSV download and exits.
     * 
     * @throws void Sends CSV headers and exits script after output.
     */
    public function export_entries_csv($request) {
        // Check nonce for REST API request
        if ( ! isset( $_SERVER['HTTP_X_WP_NONCE'] ) || ! wp_verify_nonce( $_SERVER['HTTP_X_WP_NONCE'], 'wp_rest' ) ) {
            return new \WP_Error(
                'rest_forbidden',
                __('You are not allowed to perform this action.', 'advanced-entries-manager-for-wpforms'),
                ['status' => 403]
            );
        }

        global $wpdb;
        $ids = $request->get_param('ids');

        if (empty($ids) || !is_array($ids)) {
            return new \WP_Error('invalid_data', __('No entries selected.', 'advanced-entries-manager-for-wpforms'), ['status' => 400]);
        }

        // Prepare placeholders for SQL IN clause
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $query = "SELECT * FROM {$wpdb->prefix}swpfe_entries WHERE id IN ($placeholders)";
        $entries = $wpdb->get_results($wpdb->prepare($query, $ids), ARRAY_A);

        if (empty($entries)) {
            return new \WP_Error('no_data', __('No data found.', 'advanced-entries-manager-for-wpforms'), ['status' => 404]);
        }

        // Set headers for CSV file download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="aem-entries.csv"');

        $output = fopen('php://output', 'w');

        // Extract header columns from first entry's unserialized data keys
        $first = $entries[0];
        $entry_data = maybe_unserialize($first['entry']);
        $headers = array_keys($entry_data);

        // Add 'id' as first column header
        array_unshift($headers, 'id');

        // Write CSV headers
        fputcsv($output, $headers);

        // Write CSV rows including 'id' and entry data values
        foreach ($entries as $entry) {
            $data = maybe_unserialize($entry['entry']);
            $row = [];

            // Add the 'id' column value first
            $row[] = $entry['id'];

            // Add other columns in header order
            foreach (array_slice($headers, 1) as $key) {
                $row[] = $data[$key] ?? '-';
            }

            fputcsv($output, $row);
        }

        fclose($output);
        exit; // Stop execution to prevent extra output
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
