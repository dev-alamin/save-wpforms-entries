<?php

namespace App\AdvancedEntryManager\Core;

defined('ABSPATH') || exit;

use App\AdvancedEntryManager\Utility\Helper;

class Handle_Cache {
    public function __construct()
    {
        // add_action( 'aemfw_after_get_total_count', [ $this, 'clear_cache' ], 10, 2 );
    }

    /**
     * Hooks into `aemfw_after_get_total_count` to perform intelligent cache invalidation.
     *
     * @param int            $total_count The total number of entries from the latest query.
     * @param WP_REST_Request $request     The current REST API request object.
     */
    function clear_cache($total_count, $request) {
        // Generate a unique cache key based on the request parameters.
        // This ensures a different cache is used for each unique query (e.g., different form IDs or filters).
        $cache_key = 'aemfw_entry_count_' . md5(serialize($request->get_params()));
        $cached_count = get_transient($cache_key);

        // If no cached count exists, this is the first time the query is run.
        if ($cached_count === false) {
            // Store the current total count in the cache for future comparison.
            // We use a long expiration time since it's only meant to be cleared manually.
            set_transient($cache_key, $total_count, WEEK_IN_SECONDS);
            return;
        }

        // Compare the current total count with the cached count.
        if ($total_count !== (int)$cached_count) {
            // The counts do not match, which means entries have been added or deleted.
            // We must now clear all related pagination caches to avoid inconsistent results.
            $this->clear_all_pagination_caches();

            // After clearing the caches, update the stored total count.
            set_transient($cache_key, $total_count, WEEK_IN_SECONDS);
        }
    }

    /**
     * Clears the transient cache for pagination cursors.
     * This should be hooked to entry creation, update, and deletion events.
     */
    public function clear_pagination_cursor_cache() {
        global $wpdb;
        $cache_prefix = 'pagination_cursor_';
        $delete = $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_{$cache_prefix}%' OR option_name LIKE '_transient_timeout_{$cache_prefix}%'");

        if( $delete ) {
            Helper::set_error_log( 'Cache cleared' );
        }else {
            Helper::set_error_log( 'Cache cannot be cleared' );
        }
    }

    function clear_all_pagination_caches() {
        global $wpdb;
        
        // Deletes all pagination cursor transients.
        $delete_cursor_cache = $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_pagination_cursor_%' OR option_name LIKE '_transient_timeout_pagination_cursor_%'");

        // It's also a good practice to clear the count cache you discussed.
        $delete_total_count = $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_aemfw_entry_count_%' OR option_name LIKE '_transient_timeout_aemfw_entry_count_%'");

        if( $delete_cursor_cache ) {
            Helper::set_error_log( 'Cache cleared' );
        }else {
            Helper::set_error_log( 'Cache cannot be cleared' );
        }
    }
}