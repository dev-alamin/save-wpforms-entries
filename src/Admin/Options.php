<?php

namespace App\AdvancedEntryManager\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Class Options
 *
 * Handles all admin-related functionalities including
 * menu registration, settings registration, asset enqueuing,
 * and admin UI rendering for WPForms Entries plugin.
 */
class Options {

	/**
	 * Constructor.
	 *
	 * Hooks into WordPress admin actions to initialize the admin menu,
	 * enqueue assets, register settings, and hide update notices on plugin pages.
	 */
	public function __construct() {
		add_action( 'admin_head', array( $this, 'hide_update_notices' ) );

		add_action( 'admin_init', array( $this, 'register_settings' ) );

		add_action( 'wp_ajax_femsave_settings', array( $this, 'save_settings' ) );
	}

	/**
	 * Save settings via AJAX.
	 *
	 * Handles saving plugin settings through an AJAX request.
	 * Validates nonce and updates options accordingly.
	 */
	public function save_settings() {
		check_ajax_referer( 'wp_rest' );

		update_option( 'femexport_limit', absint( $_POST['femexport_limit'] ?? 100 ) );
		update_option( 'fem_entries_per_page', absint( $_POST['fem_entries_per_page'] ?? 20 ) );

		wp_send_json_success( array( 'message' => 'Saved' ) );
	}

	/**
	 * Register plugin settings.
	 *
	 * Registers settings for the plugin, including OAuth credentials
	 * and new custom options for Google Sheets integration.
	 */
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
