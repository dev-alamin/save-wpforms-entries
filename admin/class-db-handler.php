<?php

/**
 * Class DB_Handler
 *
 * Handles database operations for saving WPForms entries in a custom table.
 * Provides methods to retrieve the table name and to create the required database table
 * for storing form entries, including metadata such as status, notes, export/sync flags, and timestamps.
 *
 * @package SWPFE
 */

namespace SWPFE;

class DB_Handler
{
    /**
     * Get the name of the custom entries table with the WordPress table prefix.
     *
     * @global \wpdb $wpdb WordPress database abstraction object.
     * @return string The full table name for storing WPForms entries.
     */
    public static function table_name()
    {
        global $wpdb;
        return $wpdb->prefix . 'swpfe_entries';
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
        require_once SWPFE_PATH . 'includes/class-capabilities.php';

        $table = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            form_id BIGINT UNSIGNED NOT NULL,
            entry LONGTEXT NOT NULL,
            name VARCHAR(255) DEFAULT NULL,
            email VARCHAR(255) DEFAULT NULL,
            status ENUM('unread','read') DEFAULT 'unread',
            is_favorite BOOLEAN DEFAULT FALSE,
            note TEXT DEFAULT NULL,
            exported_to_csv BOOLEAN DEFAULT FALSE,
            synced_to_gsheet BOOLEAN DEFAULT FALSE,
            printed_at DATETIME DEFAULT NULL,
            is_spam TINYINT(1) DEFAULT 0,
            resent_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_form_id (form_id),
            INDEX idx_status (status),
            INDEX idx_created_at (created_at),
            INDEX idx_email (email),
            INDEX idx_name (name),
            INDEX idx_formid_id (form_id, id) -- Composite index for pagination performance
        ) $charset_collate;";


        dbDelta($sql);

        new \SWPFE\Capabilities();
    }
}
