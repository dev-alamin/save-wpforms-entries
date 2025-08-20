<?php

/**
 * Class DB_Schema
 *
 * Handles database operations for saving WPForms entries in a custom table.
 * Provides methods to retrieve the table name and to create the required database table
 * for storing form entries, including metadata such as status, notes, export/sync flags, and timestamps.
 *
 * @package aemfw
 */

namespace App\AdvancedEntryManager\Core;

defined('ABSPATH') || exit;

class DB_Schema
{
    /**
     * Get the name of the custom entries table with the WordPress table prefix.
     *
     * @global \wpdb $wpdb WordPress database abstraction object.
     * @return string The full table name for storing WPForms entries.
     */
    public static function table()
    {
        global $wpdb;
        return $wpdb->prefix . 'aemfw_entries';
    }

    /**
     * Create the custom database table for storing WPForms entries if it does not exist.
     *
     * Uses WordPress dbDelta to safely create or update the table structure.
     *
     * @global \wpdb $wpdb WordPress database abstraction object.
     * @return void
     */
    public static function create_table()
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        $table = self::table();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            form_id BIGINT(20) UNSIGNED NOT NULL,
            entry LONGTEXT NOT NULL,
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

        /**
         * Filter the SQL query for creating the entries table.
         * 
         * This allows developers to modify the SQL query before it is executed.
         * @param string $sql The SQL query to create the entries table.
         * @return string Modified SQL query.
         */
        $sql = apply_filters('aemfw_create_entries_table_sql', $sql);

        dbDelta($sql);
    }
}
