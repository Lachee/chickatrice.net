<?php namespace kiss\cache;

use kiss\exception\ArgumentException;
use kiss\models\BaseObject;

class ArrayCache extends BaseObject implements CacheInterface {

    private $_cache = [];

    /** @inheritdoc */
    public function set($key, $data) { 
        $hash = RedisCache::path($key);
        $this->_cache[$hash] = [ $key, -1 ];
        return $this;
    }

    /** @inheritdoc */
    public function get($key) { 
        if (!$this->has($key)) return null;
        $hash = RedisCache::path($key);
        return $this->_cache[$hash][0];
    }

    /** @inheritdoc */
    public function has($key) {
        $hash = RedisCache::path($key);
        if (!isset($this->_cache[$hash]))
            return false;

        return $this->_cache[$hash][1] < 0 || $this->_cache[$hash][1] < time();
    }

    /** @inheritdoc */
    public function getset($key, $callback) {
        if (!is_callable($callback))
            throw new ArgumentException('$callback must be callable');

        if (!$this->has($key)) {
            $result = call_user_func($callback);
            $this->set($key, $result);
            return $result;
        } 

        return $this->get($key);
    }

    /** @inheritdoc */
    public function ttl($key, $seconds) {
        $hash = RedisCache::path($key);
        if (isset($this->_cache[$hash]))
            $this->_cache[$hash][1] = time() + $seconds;
        return $this;
    }

    /** @inheritdoc */
    public function delete($key) { 
        $hash = RedisCache::path($key);
        if (isset($this->_cache[$hash]))
            unset($this->_cache[$hash]);
        return $this;
    }
    
}