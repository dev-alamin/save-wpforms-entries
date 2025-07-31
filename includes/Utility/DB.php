<?php

namespace App\AdvancedEntryManager\Utility;

class DB {

    /**
     * Check if a given database table exists (with prefix).
     *
     * @param string $table_suffix The suffix of the table name (without prefix).
     * @return bool
     */
    public static function table_exists( string $table_suffix ): bool {
        global $wpdb;

        $full_table = $wpdb->prefix . $table_suffix;

        $exists = $wpdb->get_var(
            $wpdb->prepare(
                'SHOW TABLES LIKE %s',
                $full_table
            )
        );

        return $exists === $full_table;
    }

    /**
     * Retrieve all entries from a specific custom table.
     *
     * @param string $table_suffix The suffix of the table name (excluding prefix).
     * @param ?int $limit Optional limit for number of rows to fetch.
     *
     * @return array List of entries as associative arrays. Empty if table does not exist or is invalid.
     */
    public static function get_all_entries( string $table_suffix, ?int $limit = null ): array {
        global $wpdb;

        // Allow only alphanumeric and underscores to avoid SQL injection
        if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', $table_suffix ) ) {
            return [];
        }

        $table_name = $wpdb->prefix . $table_suffix;

        // Check if table exists
        $table_exists = $wpdb->get_var( $wpdb->prepare(
            "SHOW TABLES LIKE %s", $table_name
        ) );

        if ( $table_exists !== $table_name ) {
            return [];
        }

        // Add optional limit clause for safety
        $limit_sql = '';
        if ( is_int( $limit ) && $limit > 0 ) {
            $limit_sql = $wpdb->prepare( 'LIMIT %d', $limit );
        }

        // Fetch entries
        $results = $wpdb->get_results(
            "SELECT * FROM `{$table_name}` {$limit_sql}",
            ARRAY_A
        );

        return is_array( $results ) ? $results : [];
    }

}
