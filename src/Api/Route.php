<?php

namespace App\AdvancedEntryManager\Api;

use WP_REST_Server;
use App\AdvancedEntryManager\Api\Callback\Bulk_Action;
use App\AdvancedEntryManager\Api\Callback\Get_Entries;
use App\AdvancedEntryManager\Api\Callback\Get_Forms;
use App\AdvancedEntryManager\Api\Callback\Update_Entries;
use App\AdvancedEntryManager\Api\Callback\Create_Entries;
use App\AdvancedEntryManager\Api\Callback\Export_Entries;
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
	private $namespace = 'aem/v1';

	/**
	 * Constructor.
	 *
	 * Registers the REST API routes on initialization.
	 */
	public function __construct(
        Bulk_Action $bulk_action,
        Get_Entries $get_entries,
        Get_Forms $get_forms,
        Update_Entries $update_entries,
        Create_Entries $create_entries,
        Export_Entries $export_entries,
        Delete_Single_Entry $delete_single_entry,
        Migrate $migrate
    )
	{
		add_action('rest_api_init', [$this, 'register_routes']);

		$this->bulk_action         = $bulk_action;
		$this->get_entries         = $get_entries;
		$this->get_forms           = $get_forms;
		$this->update_entries      = $update_entries;
		$this->create_entries      = $create_entries;
		$this->export_entries      = $export_entries;
		$this->delete_single_entry = $delete_single_entry;
		$this->migrate             = $migrate;
	}

	/**
	 * Registers all REST API routes for the Save WPForms Entries plugin.
	 * 
	 * This method defines routes for managing form entries, including:
	 * 
	 * - Fetching entries with filters and pagination       (/entries)
	 * - Creating new entries                               (/entries)
	 * - Fetching single entry                              (/entries/(?P<id>\d+))
	 * - Retrieving form metadata                           (/forms)
	 * - Updating existing entries                          (/entries/(?P<id>\d+))
	 * - Deleting entries                                   (/entries/(?P<id>\d+))
	 * - Performing bulk actions on entries                 (/entries/bulk)
	 * - Exporting entries to CSV                           (/export/bulk)
	 * - Retrieving form fields                             (/forms/(?P<form_id>\d+)/fields)
	 * - Exporting full dataset                             (/entries/export/full)
	 * - Migrating data from WPFormsDB plugin               (/legacy/source/count)
	 * - Starting data migration                            (/migration/trigger)
	 * - Checking migration progress                        (/migration/progress)
	 * - Starting export jobs                               (/export/start)
	 * - Downloading export files                           (/download-csv)
	 * - Monitoring export progress                         (/export/progress)
	 * - Downloading export result                          (/export/download)
	 * - Deleting export files                              (/export/delete)
	 * 
	 * Each route uses proper permission callbacks ensuring only authorized users
	 * (typically admins with 'manage_options') can perform operations.
	 * 
	 * Validation and sanitization callbacks are defined for all route parameters
	 * to enforce data integrity and security.
	 * 
	 * @return void
	 */
	private function get_all_routes()
	{
		$data = [
			// Route: GET /entries - List entries with filtering and pagination
			[
				'route' => '/entries',
				'data' => [
					'methods'  => WP_REST_Server::READABLE,
					'callback' => [$this->get_entries, 'get_entries'],
					'permission_callback' => $this->permission_callback_by_method( WP_REST_Server::READABLE ),
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
				'route' => '/entries',
				'data' => [
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [$this->create_entries, 'create_entries'],
					'permission_callback' => $this->permission_callback_by_method( WP_REST_Server::CREATABLE ),
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
				'route' => '/entries/(?P<id>\d+)',
				'data'  => [
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [$this->get_forms, 'get_forms'],
					'permission_callback' => $this->permission_callback_by_method( WP_REST_Server::READABLE),
				],
			],

			// Route: GET /forms - Get all form metadata
			[
				'route' => '/forms',
				'data' => [
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [$this->get_forms, 'get_forms'],
					'permission_callback' => $this->permission_callback_by_method(WP_REST_Server::READABLE),
				],
			],

			// Route: POST/PATCH /update - Update an existing entry
			[
				'route' => '/entries/(?P<id>\d+)',
				'data' => [
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [$this->update_entries, 'update_entry'],
					'permission_callback' => $this->permission_callback_by_method( WP_REST_Server::EDITABLE ),
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
				'route' => '/entries/(?P<id>\d+)',
				'data'  => [
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [$this->delete_single_entry, 'delete_entry'],
					'permission_callback' => $this->permission_callback_by_method( WP_REST_Server::DELETABLE ),
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
            // Route for bulk actions
			[
				'route' => '/entries/bulk',
				'data' => [
					'methods'  => WP_REST_Server::EDITABLE,
					'callback' => [$this->bulk_action, 'bulk_actions'],
					'permission_callback' => $this->permission_callback_by_method( WP_REST_Server::EDITABLE ),
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
				'route' => '/export/bulk',
				'data' => [
					'methods' => WP_REST_Server::CREATABLE,
					'callback' => [$this->bulk_action, 'export_entries_csv_bulk'],
					'permission_callback' => $this->permission_callback_by_method( WP_REST_Server::CREATABLE ),
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
					'callback' => [$this->get_forms, 'get_form_fields'],
					'permission_callback' => $this->permission_callback_by_method( WP_REST_Server::READABLE ),
				],
			],
			[
				'route' => '/entries/export/full',
				'data' => [
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [$this->export_entries, 'export_csv_full_now'],
					'permission_callback' => $this->permission_callback_by_method( WP_REST_Server::READABLE ),
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
						'batch_size' => [
							'required' => false,
							'default' => 500,
							'validate_callback' => function ($param) {
								return is_numeric($param) && intval($param) >= 100 && intval($param) <= 5000;
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
				'route' => '/legacy/source/count',
				'data' => [
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [$this->migrate, 'wpformsdb_data'],
					'permission_callback' => $this->permission_callback_by_method( WP_REST_Server::READABLE ),
				],
			],
			[
				'route' => '/migration/trigger',
				'data' => [
					'methods' => WP_REST_Server::CREATABLE,
					'callback' => function (\WP_REST_Request $request) {
						$migrate = new \App\AdvancedEntryManager\Api\Callback\Migrate();
						return $migrate->trigger_migration();
					},
					'permission_callback' => $this->permission_callback_by_method( WP_REST_Server::CREATABLE ),
				]
			],
			[
				'route' => '/migration/progress',
				'data' => [
					'methods'  => WP_REST_Server::READABLE,
					'callback' => [$this->migrate, 'get_migration_progress'],
					'permission_callback' => $this->permission_callback_by_method( WP_REST_Server::READABLE ),
				],
			],
			[
				'route' => '/export/start',
				'data' => [
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [$this->export_entries, 'start_export_job'],
					'permission_callback' => $this->permission_callback_by_method( WP_REST_Server::CREATABLE ),
					'args'                => [
						'form_id' => [
							'required' => true,
							'type'     => 'integer',
						],
						'batch_size' => [
							'required' => false,
							'type'     => 'integer',
						],
						'date_from' => [
							'required' => false,
							'type'     => 'string',
						],
						'date_to' => [
							'required' => false,
							'type'     => 'string',
						],
						'exclude_fields' => [
							'required' => false,
							'type'     => 'array',
						],
					],
				]
			],

			[
				'route' => '/download-csv',
				'data' => [
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [$this->export_entries, 'download_csv_file'],
					'permission_callback' => $this->permission_callback_by_method( WP_REST_Server::READABLE ),
					'args'                => [
						'job_id' => [
							'required' => true,
							'type'     => 'string',
						],
					],
				]
			],
			[
				'route' => '/export/progress',
				'data' => [
					'methods'  => WP_REST_Server::READABLE,
					'callback' => [$this->export_entries, 'get_export_progress'],
					'permission_callback' => $this->permission_callback_by_method( WP_REST_Server::READABLE ),
				]
			],
			[
				'route' => '/export/download',
				'data' => [
					'methods' => WP_REST_Server::READABLE,
					'callback' => [ $this->export_entries, 'download_export_file'],
					'permission_callback' => $this->permission_callback_by_method( WP_REST_Server::READABLE ),
				],
			],
			[
				'route' => '/export/delete',
				'data' => [
					'methods' => WP_REST_Server::CREATABLE,
					'callback' => [ $this->export_entries, 'delete_export_file'],
					'permission_callback' => $this->permission_callback_by_method( WP_REST_Server::READABLE ),
				]
			]
		];

        /**
         * Filter to allow other plugins to add custom routes.
         * 
         * This filter can be used to extend the existing routes
         * or to add new routes for custom functionality.
         */
        $data = apply_filters('aemfw_api_routes', $data);

		return $data;
	}

    /**
     * Registers all REST API routes defined in this class.
     * 
     * This method iterates through the routes defined in get_all_routes()
     * and registers each route with WordPress's REST API.
     * 
     * @return void
     */
    public function register_routes()
    {

        $data = $this->get_all_routes();

        foreach ((array)$data as $item) {
			register_rest_route($this->namespace, $item['route'], $item['data']);
		}
    }

    /**
     * Permission callback for the REST API routes.
     * 
     * This method checks the current user's capabilities
     * based on the requested method of the route.
     * 
     * @return bool True if the user has permission, false otherwise.
     */
    private function permission_callback_by_method(string $method)
    {
        $map = [
            WP_REST_Server::CREATABLE => 'can_create_aemfw_entries',
            WP_REST_Server::EDITABLE  => 'can_edit_aemfw_entries',
            WP_REST_Server::DELETABLE => 'can_delete_aemfw_entries',
            WP_REST_Server::READABLE  => 'can_view_aemfw_entries',
        ];

        $capability = $map[$method] ?? 'can_manage_aemfw_entries';

        return function () use ($capability) {
            return current_user_can($capability) && is_user_logged_in();
        };
    }
}
