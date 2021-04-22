<?php

declare(strict_types=1);

namespace Jonquil\Cache;

/**
 * Cache Interface
 *
 * CacheInterface is an interface which defines
 * a prototype for cache management classes.
 *
 * @package Jonquil\Cache
 */
interface CacheInterface
{
    /**
     * Retrieves an item from the cache pool
     *
     * @param string $id Cache item ID
     * @return mixed The data stored in the cache pool
     * or false if it is expired or not found
     */
    function get(string $id);

    /**
     * Stores data in the cache pool
     *
     * @param string $id Cache item ID
     * @param mixed $data Data for caching
     * @param int $lifetime Lifetime of the cache item in seconds
     * @return bool
     */
    function set(string $id, $data, int $lifetime): bool;

    /**
     * Deletes an item from the cache pool
     *
     * @param string $id Cache item ID
     * @return bool
     */
    function delete(string $id): bool;

    /**
     * Flushes all existing items in the cache pool
     *
     * @return bool
     */
    function flush(): bool;
}

// -- End of file
