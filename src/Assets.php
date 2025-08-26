<?php

namespace App\AdvancedEntryManager;

use App\AdvancedEntryManager\Utility\Helper;

defined( 'ABSPATH' ) || exit;
/**
 * Class Assets
 *
 * Handles the registration and enqueueing of admin assets.
 */
class Assets {

	/**
	 * Constructor: Hook into admin asset loading.
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	/**
	 * Get all admin scripts.
	 *
	 * @return array
	 */
	public function get_scripts() {
		$version = defined( 'FEM_VERSION' ) ? FEM_VERSION : time();

		return array(
			'fem-tailwind-css' => array(
				'src'       => FEM_ASSETS_URL . 'admin/tailwind.min.js',
				'deps'      => array(),
				'version'   => $version,
				'in_footer' => false,
			),
			'fem-admin-js'     => array(
				'src'       => FEM_ASSETS_URL . 'admin/admin.js',
				'deps'      => array(),
				'version'   => filemtime( FEM_PATH . 'assets/admin/admin.js' ),
				'in_footer' => true,
			),
			'fem-collapse'     => array(
				'src'       => FEM_ASSETS_URL . 'admin/collapse.js',
				'deps'      => array(),
				'version'   => null,
				'in_footer' => true,
			),
			'fem-alpine'       => array(
				'src'       => FEM_ASSETS_URL . 'admin/alpine.min.js',
				'deps'      => array( 'fem-collapse' ),
				'version'   => null,
				'in_footer' => true,
			),
			'fem-lottie'       => array(
				'src'       => FEM_ASSETS_URL . 'admin/lottie-player.js',
				'deps'      => array(),
				'version'   => '5.12.0',
				'in_footer' => false,
			),
		);
	}

	/**
	 * Get all admin styles.
	 *
	 * @return array
	 */
	public function get_styles() {
		$version = defined( 'FEM_VERSION' ) ? FEM_VERSION : time();

		return array(
			'fem-admin-css' => array(
				'src'     => FEM_ASSETS_URL . 'admin/admin.css',
				'deps'    => array(),
				'version' => filemtime( FEM_PATH . 'assets/admin/admin.css' ),
			),
		);
	}

	/**
	 * Register and enqueue assets on specific admin pages.
	 *
	 * @param string $hook
	 * @return void
	 */
	public function register_assets( $hook ) {
		if ( ! in_array(
			$hook,
			array(
				'toplevel_page_forms-entries-manager',
				'forms-entries_page_form-entries-settings',
				'forms-entries_page_form-entries-migration',
			),
			true
		) ) {
			return;
		}

		// Register styles
		foreach ( $this->get_styles() as $handle => $style ) {
			wp_register_style(
				$handle,
				$style['src'],
				$style['deps'] ?? array(),
				$style['version'] ?? false
			);

			wp_enqueue_style( $handle );
		}

		// Register scripts
		foreach ( $this->get_scripts() as $handle => $script ) {
			wp_register_script(
				$handle,
				$script['src'],
				$script['deps'] ?? array(),
				$script['version'] ?? false,
				$script['in_footer'] ?? true
			);

			wp_enqueue_script( $handle );
		}

		wp_enqueue_script( 'lodash.min.js' );
		// Get the existing custom columns from the database.
		$initial_columns = Helper::get_option( 'cusom_form_columns_settings', array() );
		// Localize main admin JS
		wp_localize_script(
			'fem-admin-js',
			'femSettings',
			array(
				'restUrl'        => esc_url_raw( rest_url() ),
				'nonce'          => wp_create_nonce( 'wp_rest' ),
				'perPage'        => Helper::get_option( 'entries_per_page', 20 ),
				'ajax_url'       => admin_url( 'admin-ajax.php' ),
				'initialColumns' => $initial_columns ? json_decode( $initial_columns ) : array(),
			)
		);

		wp_localize_script(
			'fem-admin-js',
			'femMigrationNotice',
			array(
				'title'      => __( 'Migrate from WPFormsDB', 'forms-entries-manager' ),
				'message'    => __( 'We found data in the legacy', 'forms-entries-manager' ) . ' <code>wpforms_db</code> ' . __( 'table. You can migrate all your entries into our advanced manager in just a few clicks.', 'forms-entries-manager' ),
				'start'      => __( 'Start Migration', 'forms-entries-manager' ),
				'dismissAlt' => __( 'Dismiss', 'forms-entries-manager' ),
			)
		);

		wp_localize_script(
			'fem-admin-js',
			'searchDropdownString',
			array(
				'emailLabel'   => esc_html__( 'Email', 'forms-entries-manager' ),
				'nameLabel'    => esc_html__( 'Name', 'forms-entries-manager' ),
				'entryIdLabel' => esc_html__( 'Entry ID', 'forms-entries-manager' ),
			),
		);

		wp_localize_script(
			'fem-admin-js',
			'femStrings',
			array(
				'title'                => __( 'Migrate from WPFormsDB', 'forms-entries-manager' ),
				'message'              => __( 'We found data in the legacy', 'forms-entries-manager' ) . ' <code>wpforms_db</code> ' . __( 'table. You can migrate all your entries into our advanced manager in just a few clicks.', 'forms-entries-manager' ),
				'start'                => __( 'Start Migration', 'forms-entries-manager' ),
				'dismissAlt'           => __( 'Dismiss', 'forms-entries-manager' ),
				// General Messages
				'csvExportedSuccess'   => __( '✅ CSV exported successfully!', 'forms-entries-manager' ),
				'changesSavedSuccess'  => __( '✅ Saved changes successfully!', 'forms-entries-manager' ),
				'settingsSavedSuccess' => __( '✅ Settings saved successfully!', 'forms-entries-manager' ),

				// Time ago
				'timeAgoJustNow'       => __( 'just now', 'forms-entries-manager' ),
				/* translators: %d is the number of minutes ago */
				'timeAgoMinutes'       => _n( '%d minute ago', '%d minutes ago', 0, 'forms-entries-manager' ),
				/* translators: %d is the number of hours ago */
				'timeAgoHours'         => _n( '%d hour ago', '%d hours ago', 0, 'forms-entries-manager' ),
				'timeAgoYesterday'     => __( 'Yesterday', 'forms-entries-manager' ),

				// Errors & Warnings
				'noteTooLong'          => __( 'Note is too long. Please limit to 1000 characters.', 'forms-entries-manager' ),
				'deleteFailedUnknown'  => __( 'Failed to delete entry: Unknown error', 'forms-entries-manager' ),
				'deleteRequestFailed'  => __( 'Delete request failed. Check console for details.', 'forms-entries-manager' ),
				'networkError'         => __( 'A network error occurred. Please try again.', 'forms-entries-manager' ),
				'entryNotFound'        => __( '❌ Entry not found in the list.', 'forms-entries-manager' ),
				'bulkActionFailed'     => __( 'Bulk action failed:', 'forms-entries-manager' ),
				'exportFailed'         => __( 'Failed to start export.', 'forms-entries-manager' ),
				'exportProgressFailed' => __( 'Failed to fetch export progress.', 'forms-entries-manager' ),
				'exportSelectForm'     => __( 'Please select a form before exporting.', 'forms-entries-manager' ),
				'exportInvalidCSV'     => __( 'Invalid CSV content.', 'forms-entries-manager' ),
				'exportComplete'       => __( 'Export complete! Your download should start shortly.', 'forms-entries-manager' ),
				'fetchFormsError'      => __( 'Failed to fetch forms:', 'forms-entries-manager' ),
				'fetchEntriesError'    => __( 'Failed to fetch entries:', 'forms-entries-manager' ),
				'fetchFieldsError'     => __( 'Failed to fetch form fields. Please try again.', 'forms-entries-manager' ),
				'unexpectedError'      => __( '❌ Unexpected error occurred.', 'forms-entries-manager' ),
				'syncDone'             => __( 'Entry synchronization Done!', 'forms-entries-manager' ),
				'syncFailed'           => __( '❌ Synchronization failed.', 'forms-entries-manager' ),
				'saveFailed'           => __( '❌ Save failed.', 'forms-entries-manager' ),
				/* translators: %s is the text for id, name or email */
				'searchPlaceholder'    => esc_html__( '🔍 Search by %s...', 'forms-entries-manager' ),
				'emailLabel'           => esc_html__( 'Email', 'forms-entries-manager' ),
				'nameLabel'            => esc_html__( 'Name', 'forms-entries-manager' ),
				'entryIdLabel'         => esc_html__( 'Entry ID', 'forms-entries-manager' ),
				'copyToClipboard'      => esc_html__( 'Copy Entry', 'forms-entries-manager' ),
				'copiedMessage'        => esc_html__( 'Copied!', 'forms-entries-manager' ),
				'copyTitle'            => esc_attr__( 'Copy all to clipboard', 'forms-entries-manager' ),
			)
		);
	}
}
