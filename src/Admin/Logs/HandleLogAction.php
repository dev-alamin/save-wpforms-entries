<?php

namespace App\AdvancedEntryManager\Admin\Logs;

use App\AdvancedEntryManager\Logger\FileLogger;
use App\AdvancedEntryManager\Utility\FileSystem;

defined( 'ABSPATH' ) || exit;

/**
 * Handles log-related actions outside of the page rendering loop.
 */
class HandleLogAction {

	/**
	 * @var FileSystem
	 */
	protected $fs;

	/**
	 * @var FileLogger
	 */
	protected $logger;

	public function __construct() {
		$this->fs     = new FileSystem();
		$this->logger = new FileLogger();
		add_action( 'admin_init', array( $this, 'handle_log_actions' ) );
	}

	/**
	 * Handles log download and clear actions.
	 */
	public function handle_log_actions() {

		if ( ! current_user_can( 'can_manage_fem_entries' ) ) {
			return;
		}

		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'forms-entries-manager-logs' ) {
			return;
		}

		if ( isset( $_GET['action'] ) && $_GET['action'] === 'download_log' ) {
			if ( check_admin_referer( 'forms-entries-manager-download' ) === false ) {
				printf(
					'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
					esc_html__( 'Security check failed. Please try again.', 'forms-entries-manager' )
				);
			}

			$this->handle_download();
		}

		if ( isset( $_POST['action'] ) && $_POST['action'] === 'clear_logs' ) {
			if ( check_admin_referer( 'forms-entries-manager-clear' ) === false ) {
				printf(
					'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
					esc_html__( 'Security check failed. Please try again.', 'forms-entries-manager' )
				);
			}

			$this->handle_clear();
		}
	}

	/**
	 * Handles the file download request.
	 */
	protected function handle_download() {

		if ( ! isset( $_GET['file'] ) ) {
			return;
		}

		if ( check_admin_referer( 'forms-entries-manager-download' ) === false ) {
			wp_die( esc_html__( 'Invalid nonce!', 'forms-entries-manager' ) );
		}

		$file_name = sanitize_file_name( wp_unslash( $_GET['file'] ) );
		$log_dir   = $this->logger->get_log_directory();
		$file_path = trailingslashit( $log_dir ) . $file_name;

		// Security check
		if ( $this->fs->exists( $file_path ) && strpos( realpath( $file_path ), realpath( $log_dir ) ) === 0 ) {
			$file_content = $this->fs->read( $file_path );

			header( 'Content-Description: File Transfer' );
			header( 'Content-Type: text/plain' );
			header( 'Content-Disposition: attachment; filename="' . esc_attr( $file_name ) . '"' );
			header( 'Expires: 0' );
			header( 'Cache-Control: must-revalidate' );
			header( 'Pragma: public' );
			header( 'Content-Length: ' . strlen( $file_content ) );

			echo esc_html( $file_content );
			exit;
		} else {
			// Redirect with an error message.
			$redirect_url = add_query_arg(
				array(
					'message' => urlencode( 'File not found or invalid.' ),
				),
				admin_url( 'admin.php?page=forms-entries-manager-logs' )
			);
				wp_safe_redirect( $redirect_url );
			exit;
		}
	}

	/**
	 * Handles the log clear request.
	 */
	protected function handle_clear() {
		if ( check_admin_referer( 'forms-entries-manager-clear' ) === false ) {
			wp_die( esc_html__( 'Invalid nonce!', 'forms-entries-manager' ) );
		}

		$this->logger->clear_old_logs( 0 ); // Pass 0 to clear all logs.
		$redirect_url = add_query_arg(
			array(
				'message' => urlencode( 'All logs have been cleared.' ),
			),
			admin_url( 'admin.php?page=forms-entries-manager-logs' )
		);
		wp_safe_redirect( $redirect_url );
		exit;
	}
}
