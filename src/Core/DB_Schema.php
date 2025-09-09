<?php

/**
 * Class DB_Schema
 *
 * Handles database operations for saving WPForms entries in a custom table.
 * Provides methods to retrieve the table name and to create the required database table
 * for storing form entries, including metadata such as status, notes, export/sync flags, and timestamps.
 *
 * @package fem
 */
namespace App\AdvancedEntryManager\Core;

use App\AdvancedEntryManager\Utility\Helper;

defined( 'ABSPATH' ) || exit;

class DB_Schema {

    /**
     * Get the name of the main submissions table.
     *
     * @return string
     */
    public static function submissions_table() {
        global $wpdb;
        return $wpdb->prefix . 'forms_entries_manager_submissions';
    }
    
    /**
     * Get the name of the entries key/value table.
     *
     * @return string
     */
    public static function entries_table() {
        global $wpdb;
        return $wpdb->prefix . 'forms_entries_manager_data';
    }

    /**
     * Create the custom database tables.
     *
     * @return void
     */
    public static function create_tables() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $submissions_table = self::submissions_table();
        $entries_table     = self::entries_table();
        $charset_collate   = $wpdb->get_charset_collate();

        // SQL for the submissions table
        $sql_submissions = "CREATE TABLE $submissions_table (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            form_id BIGINT(20) UNSIGNED NOT NULL,
            name VARCHAR(255) DEFAULT NULL,
            email VARCHAR(255) DEFAULT NULL,
            status ENUM('unread','read') DEFAULT 'unread',
            is_favorite TINYINT(1) DEFAULT 0,
            note TEXT DEFAULT NULL,
            exported_to_csv TINYINT(1) DEFAULT 0,
            synced_to_gsheet TINYINT(1) DEFAULT 0,
            printed_at DATETIME DEFAULT NULL,
            is_spam TINYINT(1) DEFAULT 0,
            resent_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            retry_count INT(11) DEFAULT 0,
            PRIMARY KEY  (id),
            KEY idx_form_id (form_id),
            KEY idx_status (status),
            KEY idx_created_at (created_at),
            KEY idx_email (email),
            KEY idx_name (name),
            KEY idx_formid_id (form_id, id)
        ) $charset_collate;";

        // SQL for the new entries key/value table
        $sql_entries = "CREATE TABLE $entries_table (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            submission_id BIGINT(20) UNSIGNED NOT NULL,
            field_key VARCHAR(255) NOT NULL,
            field_value LONGTEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_submission_id (submission_id),
            KEY idx_field_key (field_key),
            FOREIGN KEY (submission_id) REFERENCES $submissions_table(id) ON DELETE CASCADE
        ) $charset_collate;";

        dbDelta( $sql_submissions );
        dbDelta( $sql_entries );

        // Save the database version for future reference.
        Helper::update_option( 'db_version', FEM_DB_VERSION );
    }
}
