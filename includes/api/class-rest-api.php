<?php

/**
 * Class Rest_API
 *
 * Handles custom REST API endpoints for managing WPForms entries in WordPress.
 *
 * Registers REST routes for:
 *   - Retrieving all entries grouped by form.
 *   - Creating new entries for a form.
 *   - Fetching single form details (placeholder).
 *
 * Endpoints:
 *   - GET /wpforms/entries/v1/entries: Retrieve all entries from the custom table, grouped by form.
 *   - POST /wpforms/entries/v1/create: Create a new entry for a given form.
 *   - GET /wpforms/entries/v1/single: (Currently mapped to get_forms, implementation not shown.)
 *
 * Permissions:
 *   - Viewing entries requires the 'can_view_wpf_entries' capability.
 *   - Creating entries requires the 'can_create_wpf_entries' capability.
 *
 * Methods:
 *   - __construct(): Registers the REST API routes on initialization.
 *   - register_route(): Defines and registers the custom REST API routes.
 *   - get_entries(): Retrieves all entries from the 'swpfe_entries' table, decoding and grouping them by form.
 *   - create_entries(WP_REST_Request $request): Validates and inserts a new entry into the database from REST request data.
 *
 * @package SWPFE
 */

namespace SWPFE;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST API handler for WPForms entries.
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
     * Registers custom REST API routes for WPForms entries.
     *
     * Defines endpoints for retrieving all entries, creating entries, and fetching single form details.
     *
     * @return void
     */
    public function register_route()
    {
        $data = [
            [
                'route' => '/entries',
                'data' => [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_entries'],
                    'permission_callback' => '__return_true', // Use custom permission check later
                    'args'                => [
                        'per_page' => [
                            'description'       => 'Number of entries per page.',
                            'type'              => 'integer',
                            'default'           => 50,
                            'sanitize_callback' => 'absint',
                            'validate_callback' => function ($value) {
                                return $value > 0 && $value <= 100;
                            },
                        ],
                        'page' => [
                            'description'       => 'Page number.',
                            'type'              => 'integer',
                            'default'           => 1,
                            'sanitize_callback' => 'absint',
                        ],
                        'form_id' => [
                            'description'       => 'Limit entries to a specific form ID.',
                            'type'              => 'integer',
                            'required'          => false,
                            'sanitize_callback' => 'absint',
                        ],
                        'search' => [
                            'description'       => 'Search within entry values.',
                            'type'              => 'string',
                            'required'          => false,
                            'sanitize_callback' => 'sanitize_text_field',
                        ],
                        'status' => [
                            'description'       => 'Filter by read/unread status.',
                            'type'              => 'string',
                            'enum'              => ['read', 'unread'],
                            'required'          => false,
                        ],
                    ],
                ],

            ],
            [
                'route' => '/create',
                'data'  => [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [$this, 'create_entries'],
                    'permission_callback' => current_user_can('can_create_wpf_entries'),
                    'args' => [
                        'form_id' => [
                            'required' => true,
                            'validate_callback' => function ($param) {
                                return is_string($param) || is_numeric($param);
                            },
                            'sanitize_callback' => 'sanitize_text_field',
                        ],
                        'entry' => [
                            'required' => true,
                            'validate_callback' => function ($param) {
                                return is_array($param);
                            },
                        ],
                        'status' => [
                            'required' => false,
                            'validate_callback' => function ($param) {
                                return in_array($param, ['unread', 'read'], true);
                            },
                            'sanitize_callback' => 'sanitize_text_field',
                            'default' => 'unread',
                        ],
                    ],
                ],
            ],

            [
                'route' => '/single',
                'data'  => [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_forms'],
                    'permission_callback' => current_user_can('can_view_wpf_entries'),
                ],
            ]
        ];

        foreach ($data as $item) {
            register_rest_route($this->namespace, $item['route'], $item['data']);
        }
    }

    /**
     * Retrieves all entries from the swpfe_entries table.
     *
     * Fetches all rows from the custom entries table, decodes the entry data,
     * and groups entries by form ID.
     *
     * @return \WP_REST_Response List of decoded entries as a REST response.
     */
    public function get_entries( WP_REST_Request $request ) {
        global $wpdb;

        $table = $wpdb->prefix . 'swpfe_entries';

        // Get query args from REST request
        $per_page = absint( $request->get_param( 'per_page' ) ) ?: 50;
        $page     = absint( $request->get_param( 'page' ) ) ?: 1;
        $form_id  = absint( $request->get_param( 'form_id' ) );
        $status   = $request->get_param( 'status' );
        $search   = sanitize_text_field( $request->get_param( 'search' ) );

        $offset = ( $page - 1 ) * $per_page;

        // Start building SQL with WHERE clause
        // Prepare WHERE + params
        $where = 'WHERE 1=1';
        $params = [];

        if ( $form_id ) {
            $where .= ' AND form_id = %d';
            $params[] = $form_id;
        }

        if ( $status === 'read' || $status === 'unread' ) {
            $where .= ' AND status = %s';
            $params[] = $status;
        }

        if ( $search ) {
            $where .= ' AND entry LIKE %s';
            $params[] = '%' . $wpdb->esc_like( $search ) . '%';
        }

        // Append pagination to params
        $params[] = $per_page;
        $params[] = $offset;

        // Final SQL
        $sql = $wpdb->prepare(
            "SELECT * FROM $table $where ORDER BY created_at DESC LIMIT %d OFFSET %d",
            ...$params
        );

        $results = $wpdb->get_results( $sql );

        // Group by form_id
        $data = [];

        foreach ( $results as $row ) {
            $entry = maybe_unserialize( $row->entry );

            $data[ $row->form_id ][] = [
                'id'         => $row->id,
                'form_title' => get_the_title( $row->form_id ),
                'entry'      => $entry,
                'read'       => $row->status,
                'date'       => $row->created_at,
                'note'       => $row->note,
                'favorite'   => (bool) $row->is_favorite,
                'exported'   => (bool) $row->exported_to_csv,
                'synced'     => (bool) $row->synced_to_gsheet,
                'printed_at' => $row->printed_at,
                'resent_at'  => $row->resent_at,
            ];
        }

        return rest_ensure_response( $data );
    }


    /**
     * Creates a new entry in the swpfe_entries table.
     *
     * Validates and inserts a new entry into the database using data from the REST request.
     *
     * @param WP_REST_Request $request The REST API request object.
     * @return WP_REST_Response Response indicating success or failure, with entry ID if successful.
     */
    public function create_entries(WP_REST_Request $request)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'swpfe_entries';

        // Get parameters from request JSON body
        $params = $request->get_json_params();

        $form_id = isset($params['form_id']) ? sanitize_text_field($params['form_id']) : null;
        $entry = isset($params['entry']) ? $params['entry'] : null; // associative array expected

        if (!$form_id || !is_array($entry)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Invalid or missing form_id or entry data.'
            ], 400);
        }

        // Prepare data to insert
        $data = [
            'form_id'    => $form_id,
            'entry'      => maybe_serialize($entry), // serialize array to store as text
            'status'     => isset($params['status']) ? sanitize_text_field($params['status']) : 'unread',
        ];

        // Insert into database
        $inserted = $wpdb->insert(
            $table,
            $data,
            [
                '%s',  // form_id as string
                '%s',  // serialized entry
                '%s',  // status
            ]
        );

        if ($inserted === false) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Database insert failed.'
            ], 500);
        }

        // Return success with inserted ID
        return new WP_REST_Response([
            'success' => true,
            'message' => 'Entry created successfully.',
            'entry_id' => $wpdb->insert_id,
        ], 201);
    }
}
