<?php

namespace SWPFE;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;

class Rest_API
{
    private $namespace = 'wpforms/entries/v1';

    public function __construct()
    {
        add_action('rest_api_init', [$this, 'register_route']);
    }

    public function register_route() {
        $data = [
            [
                'route' => '/entries',
                'data'  => [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_entries'],
                    'permission_callback' => [$this, 'can_view_entries'], // ✅ custom permission
                ],
            ],
            [
                'route' => '/create',
                'data'  => [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [$this, 'create_entries'],
                    'permission_callback' => '__return_true', // or your custom callback

                    'args' => [
                        'form_id' => [
                            'required' => true,
                            'validate_callback' => function($param) {
                                return is_string($param) || is_numeric($param);
                            },
                            'sanitize_callback' => 'sanitize_text_field',
                        ],
                        'entry' => [
                            'required' => true,
                            'validate_callback' => function($param) {
                                return is_array($param);
                            },
                        ],
                        'status' => [
                            'required' => false,
                            'validate_callback' => function($param) {
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
                    'permission_callback' => [$this, 'can_view_forms'],   // reuse the same permission
                ],
            ]
        ];

        foreach ($data as $item) {
            register_rest_route($this->namespace, $item['route'], $item['data']);
        }
    }

    /**
     * Permission to view form entries (admins or editors).
     */
    public function can_view_entries() {
        // return current_user_can('edit_others_posts') || current_user_can('manage_options');
        return true;
    }

    /**
     * Permission to view form structure (can be public or restricted).
     */
    public function can_view_forms() {
        // Change to `true` only if you're sure it's not sensitive
        return current_user_can('read'); // basic subscriber-level
    }

   /**
     * Retrieves all entries from the swpfe_entries table.
     *
     * @return \WP_REST_Response List of decoded entries as a REST response.
    */
    public function get_entries()
    {
        global $wpdb;

        $table = $wpdb->prefix . 'swpfe_entries';

        // Fetch all rows from the table
        $results = $wpdb->get_results("SELECT * FROM $table");

        $data = [];

        foreach ($results as $row) {
            // Decode the JSON 'entry' column and include ID or other fields if needed
            $entry = maybe_unserialize($row->entry); // decode as assoc array

            // Include metadata like ID or read status (optional)
            $data[$row->form_id][] = [
                'id'    => $row->id,
                // 'formid' => $row->form_id,
                'form_title' => get_the_title( $row->form_id ),
                'entry' => $entry,
                'read'  => $row->is_read,
                'date'  => $row->created_at,
            ];
        }

        return rest_ensure_response($data);
    }

    public function create_entries(WP_REST_Request $request) {
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
