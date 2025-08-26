<?php

namespace App\AdvancedEntryManager\Admin;

use App\AdvancedEntryManager\Utility\Helper;

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

		add_action( 'wp_ajax_fem_save_settings', array( $this, 'save_settings' ) );
	}

	/**
	 * Save settings via AJAX.
	 *
	 * Handles saving plugin settings through an AJAX request.
	 * Validates nonce and updates options accordingly.
	 */
	public function save_settings() {
		check_ajax_referer( 'wp_rest' );

		Helper::update_option( 'export_limit', absint( $_POST['fem_export_limit'] ?? 100 ) );
		Helper::update_option( 'entries_per_page', absint( $_POST['fem_entries_per_page'] ?? 20 ) );

		if ( ! empty( $_POST['fem_custom_columns'] ) && is_array( $_POST['fem_custom_columns'] ) ) {
			$sanitized = array();

            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
			foreach ( $_POST['fem_custom_columns'] as $form_id => $fields ) {
				$sanitized[ absint( $form_id ) ] = array_map( 'sanitize_text_field', (array) $fields );
			}

			Helper::update_option( 'cusom_form_columns_settings', json_encode( $sanitized ) );
		} else {
			Helper::update_option( 'cusom_form_columns_settings', array() );
		}

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
			'fem_google_settings',
			'fem_google_sheet_tab',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		// New custom options
		register_setting(
			'fem_google_settings',
			'fem_entries_per_page',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 25,
			)
		);

		register_setting(
			'fem_google_settings',
			'fem_google_sheet_id',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		register_setting(
			'fem_google_settings',
			'fem_google_sheet_auto_sync',
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
