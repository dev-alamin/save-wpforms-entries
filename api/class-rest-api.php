<?php

namespace SWPFE;

use WP_REST_Server;

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
                'route' => '/data',
                'data'  => [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_forms'],
                    'permission_callback' => [$this, 'can_view_forms'],   // ✅ custom permission
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

}
