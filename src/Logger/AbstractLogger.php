<?php

namespace App\AdvancedEntryManager\Logger;

use App\AdvancedEntryManager\Utility\FileSystem;
use Error;

defined( 'ABSPATH' ) || exit;

/**
 * Abstract Logger class that provides a foundation for all loggers.
 * It handles the initialization of the WP_Filesystem and manages the log directory.
 */
abstract class AbstractLogger {

	/**
	 * @var FileSystem The filesystem utility class instance.
	 */
	protected $fs;

	/**
	 * @var string The base directory for all logs.
	 */
	protected $log_dir;

	public function __construct() {
		// Initialize the filesystem utility.
		$this->fs      = new FileSystem();
		$this->log_dir = $this->get_log_directory();
		$this->maybe_create_log_directory();

		$this->protect_log_directory();
	}

	/**
	 * Abstract method to be implemented by child classes for logging a message.
	 *
	 * @param string $message The message to log.
	 * @param string $level The log level (e.g., 'info', 'error', 'debug').
	 * @return bool True if the message was logged successfully, false otherwise.
	 */
	abstract public function log( $message, $level = 'info' );

	/**
	 * Abstract method to be implemented by child classes for clearing old logs.
	 *
	 * @param int $retention_days The number of days to retain logs.
	 */
	abstract public function clear_old_logs( $retention_days );

	/**
	 * Gets the full path to the log directory.
	 *
	 * @return string The log directory path.
	 */
	protected function get_log_directory() {
		$upload_dir = wp_upload_dir();
		return $upload_dir['basedir'] . '/forms-entries-manager-logs';
	}

	/**
	 * Creates the log directory if it doesn't already exist.
	 *
	 * @return bool True on success, false on failure.
	 */
	protected function maybe_create_log_directory() {
		if ( ! $this->fs->exists( $this->log_dir ) || ! $this->fs->is_dir( $this->log_dir ) ) {
			return wp_mkdir_p( $this->log_dir );
		}
		return true;
	}

	/**
	 * Secures a directory by adding .htaccess (for Apache) and index.php (for all servers).
	 *
	 * @param string $dir The directory to secure.
	 * @return bool True on success, false on failure.
	 */
	protected function protect_log_directory() {
		if ( ! $this->fs->is_dir( $this->log_dir ) ) {
			return false;
		}

		// 1. Create .htaccess for Apache protection
		$htaccess_file    = trailingslashit( $this->log_dir ) . '.htaccess';
		$htaccess_content = "# Apache 2.4+\n"
							. "<IfModule authz_core_module>\n"
							. "    Require all denied\n"
							. "</IfModule>\n\n"
							. "# Apache 2.2\n"
							. "<IfModule !authz_core_module>\n"
							. "    Deny from all\n"
							. "</IfModule>\n";

		// Check if the .htaccess file already exists to avoid unnecessary writes
		if ( ! $this->fs->exists( $htaccess_file ) ) {
			$this->fs->write( $htaccess_file, $htaccess_content );
		}

		// 2. Create index.php for universal protection
		$index_file    = trailingslashit( $this->log_dir ) . 'index.php';
		$index_content = '<?php // Silence is golden.';
		if ( ! $this->fs->exists( $index_file ) ) {
			$this->fs->write( $index_file, $index_content );
		}

		// 3. Create robots.txt to discourage indexing
		$robots_file    = trailingslashit( $this->log_dir ) . 'robots.txt';
		$robots_content = "User-agent: *\nDisallow: /";
		if ( ! $this->fs->exists( $robots_file ) ) {
			$this->fs->write( $robots_file, $robots_content );
		}

		return true;
	}
}
