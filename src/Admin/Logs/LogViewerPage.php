<?php

namespace App\AdvancedEntryManager\Admin\Logs;

use App\AdvancedEntryManager\Logger\FileLogger;
use App\AdvancedEntryManager\Utility\FileSystem;
use App\AdvancedEntryManager\Admin\Logs\Log_List_Table;

defined('ABSPATH') || exit;

/**
 * Manages the admin page for viewing and downloading log files.
 */
class LogViewerPage {

    /**
     * @var FileLogger The logger instance.
     */
    protected $logger;

    /**
     * @var FileSystem The filesystem instance.
     */
    protected $fs;

    /**
     * Constructor to initialize the class properties.
     */
    public function __construct() {
        $this->logger = new FileLogger();
        $this->fs = new FileSystem();
    }

    /**
     * Renders the content of the admin page.
     */
    public function render_page() {

        if (isset($_GET['action']) && $_GET['action'] === 'view_log' && isset($_GET['file'])) {
            $this->render_single_log_view();
        } else {
            $this->render_log_list();
        }
    }

    /**
     * Renders the log file list view.
     */
    private function render_log_list() {
        $log_files = $this->get_log_files();
        $message = '';
        $message_type = '';

        if (isset($_GET['message'])) {
            $message = sanitize_text_field($_GET['message']);
            $message_type = 'success';
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Forms Entries Manager Logs', 'forms-entries-manager'); ?></h1>
            
            <?php if ($message): ?>
                <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
                    <p><?php echo esc_html($message); ?></p>
                </div>
            <?php endif; ?>

            <div class="">
                <h2><?php esc_html_e('Log Files', 'forms-entries-manager'); ?></h2>
                <p><?php esc_html_e('View and download log files. Logs are cleaned up automatically after 30 days.', 'forms-entries-manager'); ?></p>
                
                <?php
                // We use Log_List_Table to render the actual list.
                $log_list_table = new \App\AdvancedEntryManager\Admin\Logs\Log_List_Table($log_files);
                $log_list_table->prepare_items();
                $log_list_table->display();
                ?>
            </div>

            <div class="card">
                <h2><?php esc_html_e('Clear Old Logs', 'forms-entries-manager'); ?></h2>
                <p><?php esc_html_e('You can manually trigger the log cleanup process.', 'forms-entries-manager'); ?></p>
                <form method="post" action="">
                    <input type="hidden" name="action" value="clear_logs">
                    <?php wp_nonce_field('forms-entries-manager-clear'); ?>
                    <input type="submit" name="submit" class="button button-danger" value="<?php esc_attr_e('Clear Logs Now', 'forms-entries-manager'); ?>">
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Renders a single log file's content.
     */
    private function render_single_log_view() {
        if (!isset($_GET['file'])) {
            return;
        }

        $file_name = sanitize_file_name($_GET['file']);
        $log_dir = $this->get_log_directory();
        $file_path = trailingslashit($log_dir) . $file_name;

        // Security check
        if ($this->fs->exists($file_path) && strpos(realpath($file_path), realpath($log_dir)) === 0) {
            $content = $this->fs->read($file_path);
        } else {
            $content = __('File not found or invalid.', 'forms-entries-manager');
        }
        ?>
        <div class="wrap">
            <h1><?php echo sprintf(esc_html__('Viewing Log File: %s', 'forms-entries-manager'), esc_html($file_name)); ?></h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=forms-entries-manager-logs')); ?>" class="button button-secondary"><?php esc_html_e('Back to Logs', 'forms-entries-manager'); ?></a>
            <a href="<?php echo esc_url(add_query_arg(['action' => 'download_log', 'file' => $file_name, '_wpnonce' => wp_create_nonce('forms-entries-manager-download')])); ?>" class="button button-primary"><?php esc_html_e('Download Log', 'forms-entries-manager'); ?></a>
            <div class="card" style="margin-top: 20px; max-width:fit-content;">
                <pre style="white-space: pre-wrap; word-wrap: break-word;"><?php echo esc_html( $content ); ?></pre>
            </div>
        </div>
        <?php
    }

    /**
     * Handles the file download request.
     */
    protected function handle_download() {
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'forms-entries-manager-download')) {
            wp_die('Invalid nonce!');
        }

        if (!isset($_GET['file'])) {
            return;
        }

        $file_name = sanitize_file_name($_GET['file']);
        $log_dir = $this->get_log_directory();
        $file_path = trailingslashit($log_dir) . $file_name;

        // Security check: Verify the file exists and is in the correct directory.
        if ($this->fs->exists($file_path) && strpos(realpath($file_path), realpath($log_dir)) === 0) {
            $file_content = $this->fs->read($file_path);

            // Set headers for file download
            header('Content-Description: File Transfer');
            header('Content-Type: text/plain');
            header('Content-Disposition: attachment; filename="' . esc_attr($file_name) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . strlen($file_content));

            // Output the file content and exit
            echo $file_content;
            exit;
        } else {
            // Redirect with an error message.
            $redirect_url = add_query_arg(['message' => urlencode('File not found or invalid.')], admin_url('admin.php?page=forms-entries-manager-logs'));
            wp_safe_redirect($redirect_url);
            exit;
        }
    }

    /**
     * Handles the log clear request.
     */
    protected function handle_clear() {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'forms-entries-manager-clear')) {
            wp_die('Invalid nonce!');
        }

        $this->logger->clear_old_logs(0); // Pass 0 to clear all logs.
        $redirect_url = add_query_arg(['message' => urlencode('All logs have been cleared.')], admin_url('admin.php?page=forms-entries-manager-logs'));
        wp_safe_redirect($redirect_url);
        exit;
    }

    /**
     * Gets a list of log files from the log directory.
     *
     * @return array An associative array of log files.
     */
    private function get_log_files() {
        $log_dir = $this->get_log_directory();
        $files = $this->fs->dirlist($log_dir, false, false);
        
        if (empty($files)) {
            return [];
        }

        // Sort files by last modification date, descending.
        usort($files, function($a, $b) {
            return $b['lastmodtime'] <=> $a['lastmodtime'];
        });

        // Filter to only include .log files.
        $log_files = [];
        foreach ($files as $file => $details) {
            if ($details['type'] === 'f' && substr($file, -4) === '.log') {
                $log_files[$file] = $details;
            }
        }
        return $log_files;
    }
    
    /**
     * Gets the full path to the log directory.
     *
     * @return string The log directory path.
     */
    protected function get_log_directory() {
        $upload_dir = wp_upload_dir();
        return trailingslashit($upload_dir['basedir']) . 'forms-entries-manager-logs';
    }
}