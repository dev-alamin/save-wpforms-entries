<?php

namespace App\AdvancedEntryManager\Utility;

defined( 'ABSPATH' ) || exit;

/**
 * A wrapper class for the WordPress Filesystem API.
 * * This class provides a centralized and secure way to interact with the file system.
 * It ensures that all file operations are handled correctly, regardless of the server's
 * file access method (direct, FTP, etc.).
 */
class FileSystem {

	/**
	 * @var \WP_Filesystem_Base The instance of the WP Filesystem API.
	 */
	private $fs;

	public function __construct() {
		// Initialize the WP Filesystem API.
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();
		global $wp_filesystem;
		$this->fs = $wp_filesystem;
	}

	/**
	 * Writes data to a file.
	 *
	 * @param string    $path The full path to the file.
	 * @param string    $content The content to write.
	 * @param int|false $mode The file mode (permissions).
	 * @return bool True on success, false on failure.
	 */
	public function write( $path, $content, $mode = false ) {
		return $this->fs->put_contents( $path, $content, $mode );
	}

	/**
	 * Reads data from a file.
	 *
	 * @param string $path The full path to the file.
	 * @return string|false The file content on success, false on failure.
	 */
	public function read( $path ) {
		return $this->fs->get_contents( $path );
	}

	/**
	 * Deletes a file.
	 *
	 * @param string $path The full path to the file.
	 * @param bool   $recursive Whether to recursively delete directories.
	 * @return bool True on success, false on failure.
	 */
	public function delete( $path, $recursive = false ) {
		return $this->fs->delete( $path, $recursive );
	}

	/**
	 * Checks if a file or directory exists.
	 *
	 * @param string $path The full path to the file or directory.
	 * @return bool True if it exists, false otherwise.
	 */
	public function exists( $path ) {
		return $this->fs->exists( $path );
	}

	/**
	 * Checks if a path is a directory.
	 *
	 * @param string $path The full path to the file or directory.
	 * @return bool True if it is a directory, false otherwise.
	 */
	public function is_dir( $path ) {
		return $this->fs->is_dir( $path );
	}

	/**
	 * Checks if a path is a file.
	 *
	 * @param string $path The full path to the file.
	 * @return bool True if it is a file, false otherwise.
	 */
	public function is_file( $path ) {
		return $this->fs->is_file( $path );
	}

	/**
	 * Gets a list of files and directories in a directory.
	 *
	 * @param string $path The full path to the directory.
	 * @param bool   $include_hidden Whether to include hidden files.
	 * @param bool   $recursive Whether to list files in subdirectories.
	 * @return array|false An array of files and directories, false on failure.
	 */
	public function dirlist( $path, $include_hidden = true, $recursive = false ) {
		return $this->fs->dirlist( $path, $include_hidden, $recursive );
	}
}
