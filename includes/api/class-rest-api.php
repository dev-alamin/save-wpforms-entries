<?php

namespace SWPFE;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST API handler for WPForms entries.
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
                    'permission_callback' => function(){
                        return current_user_can( 'manage_options' )  && is_user_logged_in();
                    },
                    'args'                => [
                        'per_page' => [
                            'description'       => __( 'Number of entries per page.', 'save-wpf-entries' ),
                            'type'              => 'integer',
                            'default'           => 50,
                            'sanitize_callback' => 'absint',
                            'validate_callback' => function ($value) {
                                return $value > 0 && $value <= 100;
                            },
                        ],
                        'page' => [
                            'description'       => __('Page number.', 'save-wpf-entries'),
                            'type'              => 'integer',
                            'default'           => 1,
                            'sanitize_callback' => 'absint',
                        ],
                        'form_id' => [
                            'description'       => __('Limit entries to a specific form ID.', 'save-wpf-entries'),
                            'type'              => 'integer',
                            'required'          => false,
                            'sanitize_callback' => 'absint',
                        ],
                        'search' => [
                            'description'       => __('Search within entry values.', 'save-wpf-entries'),
                            'type'              => 'string',
                            'required'          => false,
                            'sanitize_callback' => 'sanitize_text_field',
                        ],
                        'status' => [
                            'description'       => __('Filter by read/unread status.', 'save-wpf-entries'),
                            'type'              => 'string',
                            'validate_callback' => function($value) {
                                return in_array($value, ['read', 'unread', '', null], true);
                            },
                            'required'          => false,
                        ],
                        'date_from' => [
                            'description'       => __('Filter by submission start date (YYYY-MM-DD)', 'save-wpf-entries'),
                            'type'              => 'string',
                            'required'          => false,
                            'sanitize_callback' => 'sanitize_text_field',
                            'validate_callback' => function( $param ) {
                                return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $param );
                            },
                        ],
                        'date_to' => [
                            'description'       => __('Filter by submission end date (YYYY-MM-DD)', 'save-wpf-entries'),
                            'type'              => 'string',
                            'required'          => false,
                            'sanitize_callback' => 'sanitize_text_field',
                            'validate_callback' => function( $param ) {
                                return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $param );
                            },
                        ],
                    ],
                ],
            ],
            [
                'route' => '/create',
                'data'  => [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [$this, 'create_entries'],
                    // 'permission_callback' => current_user_can('can_create_wpf_entries'),
                    'permission_callback' => function(){
                        return current_user_can( 'manage_options' )  && is_user_logged_in();
                    },
                    'args' => [
                        'form_id' => [
                            'description' => __('Form ID for the entry.', 'save-wpf-entries'),
                            'required' => true,
                            'validate_callback' => function ($param) {
                                return is_string($param) || is_numeric($param);
                            },
                            'sanitize_callback' => 'sanitize_text_field',
                        ],
                        'entry' => [
                            'description' => __('Entry data as an associative array.', 'save-wpf-entries'),
                            'required' => true,
                            'validate_callback' => function ($param) {
                                return is_array($param);
                            },
                        ],
                        'status' => [
                            'description' => __('Read/unread status for the entry.', 'save-wpf-entries'),
                            'required' => false,
                            'validate_callback' => function ($param) {
                                return in_array($param, ['unread', 'read'], true);
                            },
                            'sanitize_callback' => 'sanitize_text_field',
                            'default' => 'unread',
                        ],
                        'is_favorite' => [
                            'description' => __('Mark entry as favorite (0 or 1).', 'save-wpf-entries'),
                            'required' => false,
                            'validate_callback' => function ($param) {
                                return is_numeric($param) && in_array($param, [0, 1], true);
                            },
                            'sanitize_callback' => 'absint',
                            'default' => '0'
                        ],
                        'note' => [
                            'description' => __('Internal note for the entry (max 500 words).', 'save-wpf-entries'),
                            'required' => false,
                            'validate_callback' => function ($param) {
                                return is_string($param) && str_word_count($param) <= 500;
                            },
                            'sanitize_callback' => 'sanitize_text_field',
                        ],
                        'exported_to_csv' => [
                            'description' => __('Exported to CSV flag (0 or 1).', 'save-wpf-entries'),
                            'required' => false,
                            'validate_callback' => function ($param) {
                                return is_numeric($param) && in_array($param, [0, 1], true);
                            },
                            'sanitize_callback' => 'absint',
                            'default' => 0
                        ],
                        'synced_to_gsheet' => [
                            'description' => __('Synced to Google Sheet flag (0 or 1).', 'save-wpf-entries'),
                            'required' => false,
                            'validate_callback' => function ($param) {
                                return is_numeric($param) && in_array($param, [0, 1], true);
                            },
                            'sanitize_callback' => 'absint',
                            'default' => 0
                        ],
                        'printed_at' => [
                            'description' => __('Printed at datetime (Y-m-d H:i:s).', 'save-wpf-entries'),
                            'required' => false,
                            'validate_callback' => function ($param) {
                                return strtotime($param) !== false;
                            },
                            'sanitize_callback' => function ($param) {
                                return date('Y-m-d H:i:s', strtotime($param));
                            },
                        ],
                        'resent_at' => [
                            'description' => __('Resent at datetime (Y-m-d H:i:s).', 'save-wpf-entries'),
                            'required' => false,
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
            [
                'route' => '/single',
                'data'  => [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_forms'],
                    'permission_callback' => function(){
                        return current_user_can( 'manage_options' )  && is_user_logged_in();
                    },
                ],
            ],
            [
                'route' => '/forms',
                'data' => [
                    'methods'  => WP_REST_Server::READABLE,
                    'callback' => [$this, 'get_forms'],
                    'permission_callback' => function(){
                        return current_user_can( 'manage_options' )  && is_user_logged_in();
                    },
                ],
            ],
            [
                'route' => '/update',
                'data' => [
                    'methods'  => WP_REST_Server::EDITABLE,
                    'callback' => [$this, 'update_entries'],
                    'permission_callback' => function(){
                        return current_user_can( 'manage_options' )  && is_user_logged_in();
                    },
                    'args' => [
                        'id' => [
                            'description' => __('Entry ID to update.', 'save-wpf-entries'),
                            'required' => true,
                            'sanitize_callback' => 'absint',
                            'validate_callback' => function ($param) {
                                return is_numeric($param) && $param > 0;
                            },
                        ],
                        'form_id' => [
                            'description' => __('Form ID for the entry.', 'save-wpf-entries'),
                            'required' => true,
                            'sanitize_callback' => 'absint',
                            'validate_callback' => function ($param) {
                                return is_numeric($param) && $param > 0;
                            },
                        ],
                        'entry' => [
                            'description' => __('Entry data as an associative array.', 'save-wpf-entries'),
                            'required' => false,
                            'validate_callback' => function ($param) {
                                return is_array($param);
                            },
                        ],
                        'status' => [
                            'description' => __('Read/unread status for the entry.', 'save-wpf-entries'),
                            'required' => false,
                            'validate_callback' => function ($param) {
                                return in_array($param, ['unread', 'read'], true);
                            },
                            'sanitize_callback' => 'sanitize_text_field',
                            'default' => 'unread',
                        ],
                        'note' => [
                            'description' => __('Internal note for the entry (max 500 words).', 'save-wpf-entries'),
                            'required' => false,
                            'validate_callback' => function ($param) {
                                return is_string($param) && str_word_count($param) <= 500;
                            },
                            'sanitize_callback' => 'sanitize_text_field',
                        ],
                        'is_favorite' => [
                            'description' => __('Mark entry as favorite (0 or 1).', 'save-wpf-entries'),
                            'required' => false,
                            'validate_callback' => function ($param) {
                                return is_numeric($param) && in_array($param, [0, 1], true);
                            },
                            'sanitize_callback' => 'absint',
                            'default' => 0
                        ],
                        'exported_to_csv' => [
                            'description' => __('Exported to CSV flag (0 or 1).', 'save-wpf-entries'),
                            'required' => false,
                            'validate_callback' => function ($param) {
                                return is_numeric($param) && in_array($param, [0, 1], true);
                            },
                            'sanitize_callback' => 'absint',
                            'default' => 0
                        ],
                        'synced_to_gsheet' => [
                            'description' => __('Synced to Google Sheet flag (0 or 1).', 'save-wpf-entries'),
                            'required' => false,
                            'validate_callback' => function ($param) {
                                return is_numeric($param) && in_array($param, [0, 1], true);
                            },
                            'sanitize_callback' => 'absint',
                            'default' => 0
                        ],
                        'printed_at' => [
                            'description' => __('Printed at datetime (Y-m-d H:i:s).', 'save-wpf-entries'),
                            'required' => false,
                            'validate_callback' => function ($param) {
                                return strtotime($param) !== false;
                            },
                            'sanitize_callback' => function ($param) {
                                return date('Y-m-d H:i:s', strtotime($param));
                            },
                        ],
                        'resent_at' => [
                            'description' => __('Resent at datetime (Y-m-d H:i:s).', 'save-wpf-entries'),
                            'required' => false,
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
            [
                'route' => '/delete',
                'data'  => [
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => [$this, 'delete_entry'],
                    'permission_callback' => function(){
                        return current_user_can( 'manage_options' )  && is_user_logged_in();
                    },
                    'args'                => [
                        'id' => [
                            'required'          => true,
                            'sanitize_callback' => 'absint',
                            'validate_callback' => function ($param) {
                                return is_numeric($param) && $param > 0;
                            },
                        ],
                        'form_id' => [
                            'required'          => true, // optional, based on use
                            'sanitize_callback' => 'absint',
                            'validate_callback' => function ($param) {
                                return is_numeric($param) && $param > 0;
                            },
                        ],
                    ],
                ],
            ],
            [
                'route' => '/oauth/callback',
                'data' => [
                    'method' => WP_REST_Server::READABLE,
                    'callback' => [ $this, 'oauth_callback' ],
                    'permission_callback' => '__return_true',
                    'args' => [
                        'code' => [
                            'required' => true,
                            'type' => 'string',
                        ],
                    ],
                ],
            ],
        ];

        foreach ($data as $item) {
            register_rest_route($this->namespace, $item['route'], $item['data']);
        }
    }

    public function oauth_callback( WP_REST_Request $request ) {
        $code = $request->get_param('code');

        if (!$code) {
            return new WP_REST_Response(['error' => 'Missing code'], 400);
        }

        $client_id     = get_option('swpfe_google_client_id');
        $client_secret = get_option('swpfe_google_client_secret');
        $redirect_uri  = 'https://shaliktheme.com/wp-json/wpforms/entries/v1/oauth/callback';

        $response = wp_remote_post('https://oauth2.googleapis.com/token', [
            'body' => [
                'code'          => $code,
                'client_id'     => $client_id,
                'client_secret' => $client_secret,
                'redirect_uri'  => $redirect_uri,
                'grant_type'    => 'authorization_code',
            ],
        ]);

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!empty($body['access_token'])) {
            update_option('swpfe_google_access_token', $body['access_token']);
            update_option('swpfe_google_refresh_token', $body['refresh_token']);
            update_option('swpfe_google_token_expires', time() + $body['expires_in']);

            // Optional: redirect back to admin
            wp_redirect(admin_url('admin.php?page=swpfe-settings&connected=true'));
            exit;
        }

        return new WP_REST_Response(['error' => $body], 400);
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

        $table     = $wpdb->prefix . 'swpfe_entries';
        $form_id   = absint($request->get_param('form_id'));
        $status    = $request->get_param('status');
        $search    = sanitize_text_field($request->get_param('search'));
        $per_page  = absint($request->get_param('per_page')) ?: 50;
        $page      = absint($request->get_param('page')) ?: 1;
        $date_from = $request->get_param('date_from');
        $date_to   = $request->get_param('date_to');
        $offset    = ($page - 1) * $per_page;

        $where  = 'WHERE 1=1';
        $params = [];

        if ($form_id) {
            $where    .= ' AND form_id = %d';
            $params[] = $form_id;
        }

        if ($status === 'read' || $status === 'unread') {
            $where    .= ' AND status = %s';
            $params[] = $status;
        }

        if ($search) {
            $where    .= ' AND entry LIKE %s';
            $params[] = '%' . $wpdb->esc_like($search) . '%';
        }

        if ($date_from) {
            $where    .= ' AND created_at >= %s';
            $params[] = $date_from . ' 00:00:00';
        }

        if ($date_to) {
            $where    .= ' AND created_at <= %s';
            $params[] = $date_to . ' 23:59:59';
        }

        // Clone params to use in COUNT query
        $count_params = $params;

        // Add LIMIT + OFFSET
        $params[] = $per_page;
        $params[] = $offset;

        $sql = $wpdb->prepare(
            "SELECT * FROM $table $where ORDER BY created_at DESC LIMIT %d OFFSET %d",
            ...$params
        );

        $results = $wpdb->get_results($sql);
        $data    = [];

        // Total count (no LIMIT)
        $count_sql = $wpdb->prepare("SELECT COUNT(*) FROM $table $where", ...$count_params);
        $total_count = (int) $wpdb->get_var($count_sql);

        foreach ($results as $row) {
            $entry_raw = maybe_unserialize($row->entry);
            $entry_normalized = [];

            foreach ($entry_raw as $key => $value) {
                $entry_normalized[ucwords(strtolower($key))] = $value;
            }

            $data[] = [
                'id'            => $row->id,
                'form_title'    => get_the_title($row->form_id),
                'entry'         => $entry_normalized,
                'status'        => $row->status,
                'date'          => $row->created_at,
                'note'          => $row->note,
                'is_favorite'   => (bool) $row->is_favorite,
                'exported'      => (bool) $row->exported_to_csv,
                'synced'        => (bool) $row->synced_to_gsheet,
                'printed_at'    => $row->printed_at,
                'resent_at'     => $row->resent_at,
                'form_id'       => $row->form_id,
            ];
        }

        return rest_ensure_response([
            'entries' => $data,           // ✅ Flat array now
            'total'   => $total_count     // ✅ Real total count
        ]);
    }

    public function get_forms()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'swpfe_entries';

        $results = $wpdb->get_results("
            SELECT form_id, COUNT(*) as entry_count 
            FROM $table 
            GROUP BY form_id
        ");

        $forms = [];

        foreach ($results as $row) {
            $forms[] = [
                'form_id'     => (int) $row->form_id,
                'form_title'  => get_the_title($row->form_id),
                'entry_count' => (int) $row->entry_count,
            ];
        }

        return rest_ensure_response($forms);
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

        // Get parameters from request JSON body
        $params = $request->get_json_params();

        /* ============== DB STRUCTURE TO FOLLOW ====================
         --id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
         --form_id BIGINT UNSIGNED NOT NULL,
         --entry LONGTEXT NOT NULL,
         --status VARCHAR(20) DEFAULT 'unread',
         --is_favorite TINYINT(1) DEFAULT 0,              -- marked favorite
         --note TEXT DEFAULT NULL,                        -- internal comment
         --exported_to_csv TINYINT(1) DEFAULT 0,          -- 0 = no, 1 = exported
         --synced_to_gsheet TINYINT(1) DEFAULT 0,         -- synced flag
         --printed_at DATETIME DEFAULT NULL,              -- print log
         --resent_at DATETIME DEFAULT NULL,               -- last resend time
         --created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        */

        $form_id    = isset($params['form_id']) ? sanitize_text_field($params['form_id']) : null;
        $entry      = isset($params['entry']) ? $params['entry'] : null; // associative array expected
        $status     = isset($params['status']) ? sanitize_text_field($params['status']) : 'unread';
        $is_fav     = isset($params['is_favorite']) ? absint($params['is_favorite']) : 0;
        $note       = isset($params['note']) ? sanitize_textarea_field($params['note']) : null;
        $exported   = isset($params['exported_to_csv']) ? absint($params['exported_to_csv']) : 0;
        $synced     = isset($params['synced_to_gsheet']) ? absint($params['synced_to_gsheet']) : 0;
        $printed_at = isset($params['printed_at']) ? sanitize_text_field($params['printed_at']) : null;
        $resent_at  = isset($params['resent_at']) ? sanitize_text_field($params['resent_at']) : null;

        if (!$form_id || !is_array($entry)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Invalid or missing form_id or entry data.'
            ], 400);
        }

        // Prepare data to insert
        $data = [
            'form_id'          => $form_id,
            'entry'            => maybe_serialize($entry), // serialize array to store as text
            'status'           => $status,
            'is_favorite'      => $is_fav,
            'note'             => $note,
            'exported_to_csv'  => $exported,
            'synced_to_gsheet' => $synced,
            'printed_at'       => $printed_at,
            'resent_at'        => $resent_at
        ];

        // Insert into database
        $inserted = $wpdb->insert(
            $table,
            $data,
            [
                '%d', // form_id
                '%s', // serialized entry
                '%s', // status
                '%d', // is_favorite
                '%s', // note
                '%d', // exported_to_csv
                '%d', // synced_to_gsheet
                '%s', // printed_at
                '%s', // resent_at
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

        // Require entry ID
        $id = isset($params['id']) ? absint($params['id']) : 0;
        $form_id = isset($params['form_id']) ? absint($params['form_id']) : 0;

        if (! $id || ! $form_id) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Missing or invalid entry ID or form ID.'
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
            $data['note'] = sanitize_textarea_field($params['note']);
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
                'message' => __('No valid fields provided for update.', 'save-wpf-entries')
            ], 400);
        }

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
                'message' => __('Database update failed.', 'save-wpf-entries')
            ], 500);
        }

        return new WP_REST_Response([
            'success'        => true,
            'message'        => __('Entry updated successfully.', 'save-wpf-entries'),
            'updated_fields' => array_keys($data),
            'entry_id'       => $id,
        ], 200);
    }

    public function delete_entry( WP_REST_Request $request ) {
        global $wpdb;

        $id = $request->get_param( 'id' );
        $form_id = $request->get_param( 'form_id' );

        $table = $wpdb->prefix . 'swpfe_entries';

        $deleted = $wpdb->delete(
            $table,
            [ 'id' => $id, 'form_id' => $form_id ],
            [ '%d', '%d' ]
        );

        if ( $deleted ) {
            return new WP_REST_Response( [ 'deleted' => true ], 200 );
        }

        return new WP_REST_Response( [ 'deleted' => false, 'message' => 'Entry not found or already deleted.' ], 404 );
    }
}
