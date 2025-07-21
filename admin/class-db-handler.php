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

class DB_Handler {

    /**
     * Get the name of the custom entries table with the WordPress table prefix.
     *
     * @global \wpdb $wpdb WordPress database abstraction object.
     * @return string The full table name for storing WPForms entries.
     */
    public static function table_name() {
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
    public static function create_table() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        require_once SWPFE_PATH . 'includes/class-capabilities.php';

        $table = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            form_id BIGINT UNSIGNED NOT NULL,
            entry LONGTEXT NOT NULL,
            status VARCHAR(20) DEFAULT 'unread',            -- read/unread
            is_favorite TINYINT(1) DEFAULT 0,               -- marked favorite
            note TEXT DEFAULT NULL,                         -- internal comment
            exported_to_csv TINYINT(1) DEFAULT 0,           -- 0 = no, 1 = exported
            synced_to_gsheet TINYINT(1) DEFAULT 0,          -- synced flag
            printed_at DATETIME DEFAULT NULL,               -- print log
            resent_at DATETIME DEFAULT NULL,                -- last resend time
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY form_id (form_id)
        ) $charset_collate;";

        dbDelta( $sql );

        new \SWPFE\Capabilities();
    }
}
