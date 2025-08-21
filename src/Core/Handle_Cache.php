<?php

namespace App\AdvancedEntryManager\Core;

defined('ABSPATH') || exit;

class Handle_Cache {
      
    /**
     * Cache group name for object cache to avoid conflicts.
     *
     * @var string
     */
    private $cache_group = 'fem_forms';

    /**
     * Sets a value in the object cache.
     *
     * @param string $key   The cache key to store the value under.
     * @param mixed  $value The value to store.
     * @param int    $ttl   The time to live in seconds.
     * @return bool True if the value was successfully set, false otherwise.
     */
    public function set_object_cache($key, $value, $ttl = HOUR_IN_SECONDS) {
        return wp_cache_set($key, $value, $this->cache_group, $ttl);
    }

    /**
     * Retrieves a value from the object cache.
     *
     * @param string $key The cache key to retrieve.
     * @return mixed The cached value, or false if the key does not exist.
     */
    public function get_object_cache($key) {
        return wp_cache_get($key, $this->cache_group);
    }

    /**
     * Deletes a value from the object cache.
     *
     * @param string $key The cache key to delete.
     * @return bool True if the value was successfully deleted, false otherwise.
     */
    public function delete_object_cache($key) {
        return wp_cache_delete($key, $this->cache_group);
    }
}