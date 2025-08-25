<?php

namespace App\AdvancedEntryManager\Admin;

defined( 'ABSPATH' ) || exit;

use App\AdvancedEntryManager\Utility\Helper;
use App\AdvancedEntryManager\Admin\Logs\LogViewerPage;

/**
 * Class Menu
 *
 * Handles the admin menu registration, settings page, and asset enqueuing
 * for the Advanced Entries Manager for WPForms plugin.
 */
class Menu {

	/**
	 * LogViewer instance.
	 *
	 * @var Assets
	 */
	protected $log_viewer_page;

	/**
	 * Constructor.
	 *
	 * Hooks into WordPress admin actions to initialize the admin menu,
	 * enqueue assets, register settings, and hide update notices on plugin pages.
	 */
	public function __construct() {
		$this->log_viewer_page = new LogViewerPage();
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_head', array( $this, 'hide_update_notices' ) );
		// add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public function register_settings() {
		// OAuth credentials
		register_setting(
			'femgoogle_settings',
			'femgoogle_sheet_tab',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		// New custom options
		register_setting(
			'femgoogle_settings',
			'fem_entries_per_page',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 25,
			)
		);

		register_setting(
			'femgoogle_settings',
			'femgoogle_sheet_id',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		register_setting(
			'femgoogle_settings',
			'femgoogle_sheet_auto_sync',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => function ( $val ) {
					return $val === '1' || $val === 1;
				},
				'default'           => true,
			)
		);
	}

	/**
	 * Add admin menu pages.
	 *
	 * Adds a top-level menu for WPForms Entries and a submenu for
	 * plugin settings. Both are accessible only to users with
	 * 'manage_options' capability.
	 *
	 * @return void
	 */
	public function add_menu() {
		$legacy_table_exists = Helper::table_exists( 'wpforms_db' );

		add_menu_page(
			__( 'Forms Entries', 'forms-entries-manager' ),
			__( 'Forms Entries', 'forms-entries-manager' ),
			'manage_options',
			'forms-entries-manager',
			array( $this, 'render_page' ),
			'dashicons-feedback',
			25
		);

		add_submenu_page(
			'forms-entries-manager',
			__( 'WPForms Entry Sync Settings', 'forms-entries-manager' ),
			__( 'Settings', 'forms-entries-manager' ),
			'manage_options',
			'form-entries-settings',
			array( $this, 'render_settings_page' ),
			65
		);

		add_submenu_page(
			'forms-entries-manager',
			__( 'Logs', 'forms-entries-manager' ),
			__( 'Logs', 'forms-entries-manager' ),
			'manage_options',
			'forms-entries-manager-logs',
			array( $this->log_viewer_page, 'render_page' )
		);

		if ( $legacy_table_exists && ! Helper::get_option( 'migration_complete' ) ) :
			add_submenu_page(
				'forms-entries-manager',
				__( 'Migration', 'forms-entries-manager' ),
				__( 'Migration', 'forms-entries-manager' ),
				'manage_options',
				'form-entries-migration',
				array( $this )
			);
		endif;
	}

	/**
	 * Render main entries page.
	 *
	 * Includes the main admin page view file.
	 *
	 * @return void
	 */
	public function render_page() {
		include __DIR__ . '/views/view-entries.php';
	}

	/**
	 * Render settings page.
	 *
	 * Includes the settings page view file.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		include __DIR__ . '/views/settings-page.php';
	}

	/**
	 * Render migration page.
	 *
	 * Includes the migration page view file.
	 *
	 * @return void
	 */
	public function render_migration_page() {
		include __DIR__ . '/views/migration-page.php';
	}

	/**
	 * Hide update notices on plugin admin pages.
	 *
	 * Prevents update nags, warnings, and other notices from
	 * displaying on the plugin's admin screens to keep UI clean.
	 *
	 * @return void
	 */
	public function hide_update_notices() {
		$screen = get_current_screen();

		if ( $screen && strpos( $screen->id, 'fem' ) !== false ) {
			echo '<style>
                .update-nag, 
                .updated, 
                .notice, 
                .update-message,
                div.notice.notice-warning,
                .notice.is-dismissible {
                    display: none !important;
                }
            </style>';
		}
	}
}
