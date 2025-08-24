<?php

namespace App\AdvancedEntryManager\Api;

defined( 'ABSPATH' ) || exit;

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
class Route {


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
	) {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );

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
	private function get_all_routes() {
		$data = array(
			// Route: GET /entries - List entries with filtering and pagination
			array(
				'route' => '/entries',
				'data'  => array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this->get_entries, 'get_entries' ),
					'permission_callback' => $this->permission_callback_by_method( WP_REST_Server::READABLE ),
					'args'                => array(
						'per_page'  => array(
							'description'       => __( 'Number of entries per page.', 'forms-entries-manager' ),
							'type'              => 'integer',
							'default'           => 20,
							'sanitize_callback' => 'absint',
							'validate_callback' => function ( $value ) {
								return $value > 0 && $value <= 100;
							},
						),
						'page'      => array(
							'description'       => __( 'Page number.', 'forms-entries-manager' ),
							'type'              => 'integer',
							'default'           => 1,
							'sanitize_callback' => 'absint',
						),
						'form_id'   => array(
							'description'       => __( 'Limit entries to a specific form ID.', 'forms-entries-manager' ),
							'type'              => 'integer',
							'required'          => false,
							'sanitize_callback' => 'absint',
						),
						'search'    => array(
							'description'       => __( 'Search within entry values.', 'forms-entries-manager' ),
							'type'              => 'string',
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'status'    => array(
							'description'       => __( 'Filter by read/unread status.', 'forms-entries-manager' ),
							'type'              => 'string',
							'validate_callback' => function ( $value ) {
								return in_array( $value, array( 'read', 'unread', '', null ), true );
							},
							'required'          => false,
						),
						'date_from' => array(
							'description'       => __( 'Filter by submission start date (YYYY-MM-DD)', 'forms-entries-manager' ),
							'type'              => 'string',
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => function ( $param ) {
								return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $param );
							},
						),
						'date_to'   => array(
							'description'       => __( 'Filter by submission end date (YYYY-MM-DD)', 'forms-entries-manager' ),
							'type'              => 'string',
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => function ( $param ) {
								return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $param );
							},
						),
					),
				),
			),

			// Route: POST /create - Create a new form entry
			array(
				'route' => '/entries',
				'data'  => array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this->create_entries, 'create_entries' ),
					'permission_callback' => $this->permission_callback_by_method( WP_REST_Server::CREATABLE ),
					'args'                => array(
						'form_id'          => array(
							'description'       => __( 'Form ID for the entry.', 'forms-entries-manager' ),
							'required'          => true,
							'validate_callback' => function ( $param ) {
								return is_string( $param ) || is_numeric( $param );
							},
							'sanitize_callback' => 'sanitize_text_field',
						),
						'entry'            => array(
							'description'       => __( 'Entry data as an associative array.', 'forms-entries-manager' ),
							'required'          => true,
							'validate_callback' => function ( $param ) {
								return is_array( $param );
							},
						),
						'status'           => array(
							'description'       => __( 'Read/unread status for the entry.', 'forms-entries-manager' ),
							'required'          => false,
							'validate_callback' => function ( $param ) {
								return in_array( $param, array( 'unread', 'read' ), true );
							},
							'sanitize_callback' => 'sanitize_text_field',
							'default'           => 'unread',
						),
						'is_favorite'      => array(
							'description'       => __( 'Mark entry as favorite (0 or 1).', 'forms-entries-manager' ),
							'required'          => false,
							'validate_callback' => function ( $param ) {
								return is_numeric( $param ) && in_array( $param, array( 0, 1 ), true );
							},
							'sanitize_callback' => 'absint',
							'default'           => 0,
						),
						'note'             => array(
							'description'       => __( 'Internal note for the entry (max 500 words).', 'forms-entries-manager' ),
							'required'          => false,
							'validate_callback' => function ( $param ) {
								return is_string( $param ) && str_word_count( $param ) <= 500;
							},
							'sanitize_callback' => 'sanitize_text_field',
						),
						'exported_to_csv'  => array(
							'description'       => __( 'Exported to CSV flag (0 or 1).', 'forms-entries-manager' ),
							'required'          => false,
							'validate_callback' => function ( $param ) {
								return is_numeric( $param ) && in_array( $param, array( 0, 1 ), true );
							},
							'sanitize_callback' => 'absint',
							'default'           => 0,
						),
						'synced_to_gsheet' => array(
							'description'       => __( 'Synced to Google Sheet flag (0 or 1).', 'forms-entries-manager' ),
							'required'          => false,
							'validate_callback' => function ( $param ) {
								return is_numeric( $param ) && in_array( $param, array( 0, 1 ), true );
							},
							'sanitize_callback' => 'absint',
							'default'           => 0,
						),
						'printed_at'       => array(
							'description'       => __( 'Printed at datetime (Y-m-d H:i:s).', 'forms-entries-manager' ),
							'required'          => false,
							'validate_callback' => function ( $param ) {
								return strtotime( $param ) !== false;
							},
							'sanitize_callback' => function ( $param ) {
								return gmdate( 'Y-m-d H:i:s', strtotime( $param ) );
							},
						),
						'resent_at'        => array(
							'description'       => __( 'Resent at datetime (Y-m-d H:i:s).', 'forms-entries-manager' ),
							'required'          => false,
							'validate_callback' => function ( $param ) {
								return strtotime( $param ) !== false;
							},
							'sanitize_callback' => function ( $param ) {
								return gmdate( 'Y-m-d H:i:s', strtotime( $param ) );
							},
						),
					),
				),
			),

			// Route: GET /single - Get a single form metadata (alias)
			// [
			// 'route' => '/forms/(?P<id>\d+)',
			// 'data'  => [
			// 'methods'             => WP_REST_Server::READABLE,
			// 'callback'            => [$this->get_forms, 'get_forms'],
			// 'permission_callback' => $this->permission_callback_by_method(WP_REST_Server::READABLE),
			// ],
			// ],

			// Route: GET /forms - Get all form metadata
			array(
				'route' => '/forms',
				'data'  => array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this->get_forms, 'get_forms' ),
					'permission_callback' => $this->permission_callback_by_method( WP_REST_Server::READABLE ),
				),
			),

			// Route: POST/PATCH /update - Update an existing entry
			array(
				'route' => '/entries/(?P<id>\d+)',
				'data'  => array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this->update_entries, 'update_entry' ),
					'permission_callback' => $this->permission_callback_by_method( WP_REST_Server::EDITABLE ),
					'args'                => array(
						'id'               => array(
							'description'       => __( 'Entry ID to update.', 'forms-entries-manager' ),
							'required'          => true,
							'sanitize_callback' => 'absint',
							'validate_callback' => function ( $param ) {
								return is_numeric( $param ) && $param > 0;
							},
						),
						'form_id'          => array(
							'description'       => __( 'Form ID for the entry.', 'forms-entries-manager' ),
							'required'          => true,
							'sanitize_callback' => 'absint',
							'validate_callback' => function ( $param ) {
								return is_numeric( $param ) && $param > 0;
							},
						),
						'entry'            => array(
							'description'       => __( 'Entry data as an associative array.', 'forms-entries-manager' ),
							'required'          => false,
							'validate_callback' => function ( $param ) {
								return is_array( $param );
							},
						),
						'status'           => array(
							'description'       => __( 'Read/unread status for the entry.', 'forms-entries-manager' ),
							'required'          => false,
							'validate_callback' => function ( $param ) {
								return in_array( $param, array( 'unread', 'read' ), true );
							},
							'sanitize_callback' => 'sanitize_text_field',
							'default'           => 'unread',
						),
						'note'             => array(
							'description'       => __( 'Internal note for the entry (max 500 words).', 'forms-entries-manager' ),
							'required'          => false,
							'validate_callback' => function ( $param ) {
								return is_string( $param ) && str_word_count( $param ) <= 500;
							},
							'sanitize_callback' => 'sanitize_text_field',
						),
						'is_favorite'      => array(
							'description'       => __( 'Mark entry as favorite (0 or 1).', 'forms-entries-manager' ),
							'required'          => false,
							'validate_callback' => function ( $param ) {
								return is_numeric( $param ) && in_array( $param, array( 0, 1 ), true );
							},
							'sanitize_callback' => 'absint',
							'default'           => 0,
						),
						'exported_to_csv'  => array(
							'description'       => __( 'Exported to CSV flag (0 or 1).', 'forms-entries-manager' ),
							'required'          => false,
							'validate_callback' => function ( $param ) {
								return is_numeric( $param ) && in_array( $param, array( 0, 1 ), true );
							},
							'sanitize_callback' => 'absint',
							'default'           => 0,
						),
						'synced_to_gsheet' => array(
							'description'       => __( 'Synced to Google Sheet flag (0 or 1).', 'forms-entries-manager' ),
							'required'          => false,
							'validate_callback' => function ( $param ) {
								return is_numeric( $param ) && in_array( $param, array( 0, 1 ), true );
							},
							'sanitize_callback' => 'absint',
							'default'           => 0,
						),
						'printed_at'       => array(
							'description'       => __( 'Printed at datetime (Y-m-d H:i:s).', 'forms-entries-manager' ),
							'required'          => false,
							'validate_callback' => function ( $param ) {
								return strtotime( $param ) !== false;
							},
							'sanitize_callback' => function ( $param ) {
								if ( empty( $param ) || ! is_string( $param ) ) {
									return null; // or a default date/time string
								}
								$timestamp = strtotime( $param );
								if ( $timestamp === false ) {
									return null; // or a fallback date/time string
								}
								return gmdate( 'Y-m-d H:i:s', $timestamp );
							},
						),
						'resent_at'        => array(
							'description'       => __( 'Resent at datetime (Y-m-d H:i:s).', 'forms-entries-manager' ),
							'required'          => false,
							'validate_callback' => function ( $param ) {
								return strtotime( $param ) !== false;
							},
							'sanitize_callback' => function ( $param ) {
								if ( empty( $param ) || ! is_string( $param ) ) {
									return null; // or return a default safe value like current date/time: gmdate('Y-m-d H:i:s')
								}
								$timestamp = strtotime( $param );
								if ( $timestamp === false ) {
									return null; // or a fallback date/time
								}
								return gmdate( 'Y-m-d H:i:s', $timestamp );
							},
						),
					),
				),
			),

			// Route: Sync a single entry
			array(
				'route' => '/entries/(?P<id>\d+)/unsync',
				'data'  => array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this->update_entries, 'handle_unsync_request' ),
					'permission_callback' => $this->permission_callback_by_method( WP_REST_Server::CREATABLE ),
					'args'                => array(
						'id' => array(
							'validate_callback' => function ( $param ) {
								return is_numeric( $param );
							},
							'required'          => true,
						),
					),
				),
			),

			// Route: Sync a single entry
			array(
				'route' => '/entries/(?P<id>\d+)/sync',
				'data'  => array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this->update_entries, 'handle_sync_request' ),
					'permission_callback' => $this->permission_callback_by_method( WP_REST_Server::CREATABLE ),
					'args'                => array(
						'id' => array(
							'validate_callback' => function ( $param ) {
								return is_numeric( $param );
							},
							'required'          => true,
						),
					),
				),
			),

			// Route: DELETE /delete - Delete a specific entry
			array(
				'route' => '/entries/(?P<id>\d+)',
				'data'  => array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this->delete_single_entry, 'delete_entry' ),
					'permission_callback' => $this->permission_callback_by_method( WP_REST_Server::DELETABLE ),
					'args'                => array(
						'id'      => array(
							'required'          => true,
							'sanitize_callback' => 'absint',
							'validate_callback' => function ( $param ) {
								return is_numeric( $param ) && $param > 0;
							},
						),
						'form_id' => array(
							'required'          => true,
							'sanitize_callback' => 'absint',
							'validate_callback' => function ( $param ) {
								return is_numeric( $param ) && $param > 0;
							},
						),
					),
				),
			),
			// Route for bulk actions
			array(
				'route' => '/entries/bulk',
				'data'  => array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this->bulk_action, 'bulk_actions' ),
					'permission_callback' => $this->permission_callback_by_method( WP_REST_Server::EDITABLE ),
					'args'                => array(
						'ids'    => array(
							'required'          => true,
							'type'              => 'array',
							'items'             => array(
								'type' => 'integer',
							),
							'sanitize_callback' => function ( $ids ) {
								return array_map( 'intval', (array) $ids );
							},
							'validate_callback' => function ( $ids ) {
								return is_array( $ids ) && count( $ids ) > 0;
							},
						),
						'action' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => function ( $action ) {
								return in_array(
									$action,
									array(
										'mark_read',
										'mark_unread',
										'favorite',
										'unfavorite',
										'mark_spam',
										'unmark_spam',
										'delete',
									),
									true
								);
							},
						),
					),
				),
			),
			array(
				'route' => '/export/bulk',
				'data'  => array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this->bulk_action, 'export_entries_csv_bulk' ),
					'permission_callback' => $this->permission_callback_by_method( WP_REST_Server::CREATABLE ),
					'args'                => array(
						'ids' => array(
							'required' => true,
							'type'     => 'array',
						),
					),
				),
			),
			array(
				'route' => '/forms/(?P<form_id>\d+)/fields',
				'data'  => array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this->get_forms, 'get_form_fields' ),
					'permission_callback' => $this->permission_callback_by_method( WP_REST_Server::READABLE ),
				),
			),

			array(
				'route' => '/legacy/source/count',
				'data'  => array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this->migrate, 'wpformsdb_data' ),
					'permission_callback' => $this->permission_callback_by_method( WP_REST_Server::READABLE ),
				),
			),
			array(
				'route' => '/migration/trigger',
				'data'  => array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => function ( \WP_REST_Request $request ) {
						$migrate = new \App\AdvancedEntryManager\Api\Callback\Migrate();
						return $migrate->trigger_migration();
					},
					'permission_callback' => $this->permission_callback_by_method( WP_REST_Server::CREATABLE ),
				),
			),
			array(
				'route' => '/migration/progress',
				'data'  => array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this->migrate, 'get_migration_progress' ),
					'permission_callback' => $this->permission_callback_by_method( WP_REST_Server::READABLE ),
				),
			),
			array(
				'route' => '/export/start',
				'data'  => array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this->export_entries, 'start_export_job' ),
					'permission_callback' => $this->permission_callback_by_method( WP_REST_Server::CREATABLE ),
					'args'                => array(
						'form_id'        => array(
							'required' => true,
							'type'     => 'integer',
						),
						'date_from'      => array(
							'required' => false,
							'type'     => 'string',
						),
						'date_to'        => array(
							'required' => false,
							'type'     => 'string',
						),
						'exclude_fields' => array(
							'required' => false,
							'type'     => 'array',
						),
					),
				),
			),

			array(
				'route' => '/download-csv',
				'data'  => array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this->export_entries, 'download_csv_file' ),
					'permission_callback' => $this->permission_callback_by_method( WP_REST_Server::READABLE ),
					'args'                => array(
						'job_id' => array(
							'required' => true,
							'type'     => 'string',
						),
					),
				),
			),
			array(
				'route' => '/export/progress',
				'data'  => array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this->export_entries, 'get_export_progress' ),
					'permission_callback' => $this->permission_callback_by_method( WP_REST_Server::READABLE ),
				),
			),
			array(
				'route' => '/export/download',
				'data'  => array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this->export_entries, 'download_export_file' ),
					// 'permission_callback' => $this->permission_callback_by_method( WP_REST_Server::READABLE ),
					'permission_callback' => '__return_true',
				),
			),
			array(
				'route' => '/export/delete',
				'data'  => array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this->export_entries, 'delete_export_file' ),
					'permission_callback' => $this->permission_callback_by_method( WP_REST_Server::READABLE ),
				),
			),
		);

		/**
		 * Filter to allow other plugins to add custom routes.
		 *
		 * This filter can be used to extend the existing routes
		 * or to add new routes for custom functionality.
		 */
		$data = apply_filters( 'femapi_routes', $data );

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
	public function register_routes() {

		$data = $this->get_all_routes();

		foreach ( (array) $data as $item ) {
			register_rest_route( $this->namespace, $item['route'], $item['data'] );
		}
	}

	/**
	 * Permission callback for the REST API routes.
	 *
	 * This method checks the current user's capabilities
	 * based on the requested method of the route.
	 *
	 * @param string $method The HTTP method of the request.
	 * @return bool True if the user has permission, false otherwise.
	 */
	private function permission_callback_by_method( string $method ) {
		$map = array(
			WP_REST_Server::CREATABLE => 'can_create_fem_entries',
			WP_REST_Server::EDITABLE  => 'can_edit_fem_entries',
			WP_REST_Server::DELETABLE => 'can_delete_fem_entries',
			WP_REST_Server::READABLE  => 'can_view_fem_entries',
		);

		$capability = $map[ $method ] ?? 'can_manage_fem_entries';

		return function () use ( $capability ) {
			return current_user_can( $capability ) && is_user_logged_in();
		};
	}
}
