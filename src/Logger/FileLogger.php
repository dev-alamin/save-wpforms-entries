<?php

namespace App\AdvancedEntryManager\Logger;

use Error;

defined('ABSPATH') || exit;

/**
 * Concrete Logger that implements file-based logging with a configurable retention period.
 */
class FileLogger extends AbstractLogger {

    /**
     * Logs a message to a daily log file.
     *
     * @param string $message The message to log.
     * @param string $level The log level (e.g., 'info', 'error', 'debug').
     * @return bool True if the message was logged successfully, false otherwise.
     */
    public function log($message, $level = 'info') {
        if ( ! $this->maybe_create_log_directory() ) {
            return false;
        }

        $log_file = $this->log_dir . '/' . date('Y-m-d') . '.log';
        $log_entry = sprintf("[%s] [%s] %s\n", current_time('mysql'), strtoupper($level), $message);

        // Check if the file exists before attempting to read.
        if ($this->fs->exists($log_file)) {
            $existing_content = $this->fs->read($log_file);
            // This is the critical check. If read fails, existing_content will be false.
            if ( $existing_content !== false ) {
                $log_entry = $existing_content . $log_entry;
            }
            // If read fails, we will still attempt to write the new entry, overwriting the file.
        }

        return $this->fs->write($log_file, $log_entry);
    }

    /**
     * Clears log files older than the specified retention period.
     *
     * @param int $retention_days The number of days to retain logs.
     */
    public function clear_old_logs($retention_days = 30) {
        if ( ! $this->fs->is_dir($this->log_dir) ) {
            return;
        }

        $files = $this->fs->dirlist($this->log_dir);
        if ( empty($files) ) {
            return;
        }

        foreach ( $files as $file => $details ) {
            // Check if it's a file and not a directory.
            if ( $details['type'] !== 'f' ) {
                continue;
            }

            $file_date = strtotime(str_replace('.log', '', $file));
            $now = time();
            $diff_in_days = round(($now - $file_date) / (60 * 60 * 24));

            if ( $diff_in_days > $retention_days ) {
                $this->fs->delete($this->log_dir . '/' . $file);
            }
        }
    }

    /**
     * Gets the full path to the log directory.
     *
     * @return string The log directory path.
     */
    public function get_log_directory() {
        $upload_dir = wp_upload_dir();
        return $upload_dir['basedir'] . '/forms-entries-manager-logs';
    }
}