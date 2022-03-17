<?php namespace kiss\cache;

use kiss\models\BaseObject;

/**
 * Handles caching of records.
 * Note that unlike redis, this has overhead for boxing and unboxing objects to and from appropriate types.
 * @package kiss\cache
 */
interface CacheInterface
{
    /**
     * Sets the data in the cache
     * @param string|object|array $key The composite key of the cached data.
     * @param mixed $data The data to be stored
     * @param int $ttl optional time to live
     * @return $this the cache
     */
    public function set($key, $data, $ttl = -1);

    /**
     * Gets the data stored at the key
     * @param string|object|array $key The composite key of the cached data.
     * @return mixed $data The data stored. Null if the data does not exist.
     */
    public function get($key);

    
    /**
     * Checks if the data exists
     * @param string|object|array $key The composite key of the cached data.
     * @return bool existance of the data
     */
    public function has($key);

    /**
     * Tries to get the data stored in the cache. 
     * If it doesn't exist, then the callback will be executed and the results
     * will be stored in the cache at the key and returned
     * @param string|object|array $key The composite key of the cached data.
     * @param Callable $callback Callback of the data to set
     * @return mixed The data at the cache, otherwise the callbacked data.
     * @param int $ttl optional time to live
     * @example
     * ```
     * $entry = $cache->getset(['some', 'id'], function() {
     *      $result = Maths::bigComplicatedOperation();
     *      return $result;
     * });
     */
    public function getset($key, $callback, $ttl = -1);

    /**
     * Sets the time to live of the given record
     * @param string|object|array $key The composite key of the cached data.
     * @param int $seconds number of seconds the record can be alive for.
     * @return $this
     */
    public function ttl($key, $seconds);
    
    /**
     * Deletes the given key
     * @param string|object|array $key The composite key of the cached data.
     * @return $thsi 
     */
    public function delete($key);
}