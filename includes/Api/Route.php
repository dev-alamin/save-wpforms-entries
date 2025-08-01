<?php

namespace App\AdvancedEntryManager\Api;

use WP_REST_Server;
use App\AdvancedEntryManager\Api\Callback\Bulk_Action;
use App\AdvancedEntryManager\Api\Callback\Get_Entries;
use App\AdvancedEntryManager\Api\Callback\Get_Forms;
use App\AdvancedEntryManager\Api\Callback\Update_Entries;
use App\AdvancedEntryManager\Api\Callback\Create_Entries;
use App\AdvancedEntryManager\Api\Callback\Export_Entries;
use App\AdvancedEntryManager\Api\Callback\Get_Form_Fields;
use App\AdvancedEntryManager\Api\Callback\Delete_Single_Entry;
use App\AdvancedEntryManager\Api\Callback\Migrate;
use App\AdvancedEntryManager\Utility\Helper;

/**
 * Class Route
 * 
 * Handles the registration of REST API routes for managing WPForms entries.
 * This class defines various endpoints for creating, retrieving, updating,
 * deleting, and exporting entries, as well as performing bulk actions.
 * Each route is associated with a specific callback method
 * to handle the request and return a response.
 * * The routes are registered during the 'rest_api_init' action,
 * allowing WordPress to recognize them
 * and process requests accordingly.
 * * @package App\AdvancedEntryManager\Api
 * * @since 1.0.0
 * * @see https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/
 * * @see https://developer.wordpress.org/rest-api/reference/
 * * This class is responsible for defining the API routes and their corresponding callbacks.
 */
class Route
{

    /**
     * Callback instances for handling various API routes.
     *
     * @var Bulk_Action
     */
    protected $bulk_action;
    /**
     * Callback instances for handling various API routes.
     *
     * @var Get_Entries
     */
    protected $get_entries;
    /**
     * Callback instances for handling various API routes.
     *
     * @var Get_Forms
     */
    protected $get_forms;
    /**
     * Callback instances for handling various API routes.
     *
     * @var Update_Entries
     */
    protected $update_entries;
    /**
     * Callback instances for handling various API routes.
     *
     * @var Create_Entries
     */
    protected $create_entries;
    /**
     * Callback instances for handling various API routes.
     *
     * @var Export_Entries
     */
    protected $export_entries;
    /**
     * Callback instances for handling various API routes.
     *
     * @var Get_Form_Fields
     */
    protected $get_form_fields;
    /**
     * Callback instances for handling various API routes.
     *
     * @var Delete_Single_Entry
     */
    protected $delete_single_entry;
    /**
     * Callback instances for handling migration from WPFormsDB plugin.
     *
     * @var Migrate
     */
    protected $migrate;

    /**
     * REST API namespace.
     *
     * @var string
     */
    private $namespace = 'aem/entries/v1';

    /**
     * Constructor.
     *
     * Registers the REST API routes on initialization.
     */
    public function __construct()
    {
        add_action('rest_api_init', [$this, 'register_route']);

        $this->bulk_action         = new Bulk_Action();
        $this->get_entries         = new Get_Entries();
        $this->get_forms           = new Get_Forms();
        $this->update_entries      = new Update_Entries();
        $this->create_entries      = new Create_Entries();
        $this->export_entries      = new Export_Entries();
        $this->get_form_fields     = new Get_Form_Fields();
        $this->delete_single_entry = new Delete_Single_Entry();
        $this->migrate             = new Migrate();
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
                    'callback' => [$this->get_entries, 'get_entries'],
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
                    'callback'            => [$this->create_entries, 'create_entries'],
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
                    'callback'            => [$this->get_forms, 'get_forms'],
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
                    'callback'            => [$this->get_forms, 'get_forms'],
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
                    'callback'            => [$this->update_entries, 'update_entries'],
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
                                if (empty($param) || !is_string($param)) {
                                    return null; // or a default date/time string
                                }
                                $timestamp = strtotime($param);
                                if ($timestamp === false) {
                                    return null; // or a fallback date/time string
                                }
                                return date('Y-m-d H:i:s', $timestamp);
                            },
                        ],
                        'resent_at' => [
                            'description'       => __('Resent at datetime (Y-m-d H:i:s).', 'advanced-entries-manager-for-wpforms'),
                            'required'          => false,
                            'validate_callback' => function ($param) {
                                return strtotime($param) !== false;
                            },
                            'sanitize_callback' => function ($param) {
                                if (empty($param) || !is_string($param)) {
                                    return null; // or return a default safe value like current date/time: date('Y-m-d H:i:s')
                                }
                                $timestamp = strtotime($param);
                                if ($timestamp === false) {
                                    return null; // or a fallback date/time
                                }
                                return date('Y-m-d H:i:s', $timestamp);
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
                    'callback'            => [$this->delete_single_entry, 'delete_entry'],
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
            // [
            //     'route' => '/entries/check-new',
            //     'data' => [
            //         'methods' => WP_REST_Server::READABLE,
            //         'callback' => [$this, 'check_new'],
            //         'permission_callback' => function () {
            //             return current_user_can('manage_options') && is_user_logged_in();
            //         },
            //         // 'permission_callback' => '__return_true',
            //         'args' => [
            //             'form_id' => [
            //                 'required' => true,
            //                 'type'     => 'integer',
            //                 'sanitize_callback' => 'absint',
            //                 'validate_callback' => function ($param) {
            //                     return $param > 0;
            //                 },
            //             ],
            //             'last_seen_id' => [
            //                 'required' => true,
            //                 'type'     => 'integer',
            //                 'sanitize_callback' => 'absint',
            //                 'validate_callback' => function ($param) {
            //                     return $param > 0;
            //                 },
            //             ],
            //         ],
            //     ],
            // ],
            [
                'route' => '/bulk',
                'data' => [
                    'methods'  => WP_REST_Server::EDITABLE,
                    'callback' => [$this->bulk_action, 'bulk_actions'],
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
                    'callback' => [$this->export_entries, 'export_entries_csv'],
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
                    'callback' => [$this->get_form_fields, 'get_form_fields'],
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
                    'callback'            => [$this->export_entries, 'export_csv_callback'],
                    // 'permission_callback' => function() {
                    //     return current_user_can( 'manage_options' ); // adjust capability
                    // },
                    'permission_callback' => '__return_true',
                    'args' => [
                        'form_id' => [
                            'required' => true,
                            'validate_callback' => function ($param) {
                                return is_numeric($param) && intval($param) > 0;
                            },
                            'sanitize_callback' => 'absint',
                        ],
                        'date_from' => [
                            'required' => false,
                            'validate_callback' => function ($param) {
                                return empty($param) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $param);
                            },
                            'sanitize_callback' => 'sanitize_text_field',
                        ],
                        'date_to' => [
                            'required' => false,
                            'validate_callback' => function ($param) {
                                return empty($param) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $param);
                            },
                            'sanitize_callback' => 'sanitize_text_field',
                        ],
                        'limit' => [
                            'required' => false,
                            'default' => 100,
                            'validate_callback' => function ($param) {
                                return is_numeric($param) && intval($param) >= 10 && intval($param) <= 50000;
                            },
                            'sanitize_callback' => 'absint',
                        ],
                        'exclude_fields' => [
                            'required' => false,
                            'validate_callback' => function ($param) {
                                // Comma separated string, allow empty or string only
                                return is_string($param);
                            },
                            'sanitize_callback' => function ($param) {
                                return sanitize_text_field($param);
                            },
                        ],
                    ],
                ]
            ],

            [
                'route' => '/wpformsdb-source-entries-count',
                'data' => [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this->migrate, 'wpformsdb_data'],
                    // 'permission_callback' => function () {
                    //     return current_user_can('manage_options') && is_user_logged_in();
                    // },
                    'permission_callback' => '__return_true',
                ],
            ],
            [
                'route' => '/trigger',
                'data' => [
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => function (\WP_REST_Request $request) {
                        $migrate = new \App\AdvancedEntryManager\Api\Callback\Migrate();
                        return $migrate->trigger_migration();
                    },
                    // 'permission_callback' => function() {
                    //     return current_user_can( 'manage_options' );
                    // },
                    'permission_callback' => '__return_true',
                ]
            ],
            [
                'route' => '/progress',
                'data' => [
                    'methods'  => 'GET',
'callback' => function () {
    $total    = (int) Helper::get_option('migration_total_entries', 0);
    $migrated = (int) Helper::get_option('migration_last_id', 0); // Or count rows if needed

    if ($total === 0) {
        return rest_ensure_response([
            'progress' => 100,
            'complete' => true,
            'migrated' => $migrated,
            'total'    => $total,
            'eta'      => null,
        ]);
    }

    $progress = ($migrated / $total) * 100;
    $progress = min(100, round($progress, 2));

    $complete = (bool) Helper::get_option('migration_complete', false);

    $start = (int) Helper::get_option('swpfe_migration_started_at', 0);
    $eta   = null;

    if ($start > 0 && $migrated > 0 && !$complete) {
        $elapsed = time() - $start;
        $eta = ( ( $total - $migrated ) / $migrated ) * $elapsed;
        $eta = max(0, (int) $eta); // ensure it's not negative
    }

    return rest_ensure_response([
        'progress' => $progress,
        'complete' => $complete,
        'migrated' => $migrated,
        'total'    => $total,
        'eta'      => $eta,
    ]);
},

                    // 'permission_callback' => function () {
                    //     return current_user_can('manage_options');
                    // },
                    'permission_callback' => '__return_true',
                ],
            ],
        ];

        foreach ($data as $item) {
            register_rest_route($this->namespace, $item['route'], $item['data']);
        }
    }
}
