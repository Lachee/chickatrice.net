<?php namespace kiss\cache;

use kiss\db\ActiveRecord;
use kiss\exception\ArgumentException;
use kiss\Kiss;
use kiss\models\BaseObject;

/**
 * Redis Cache
 * @package kiss\cache
 */
class RedisCache extends BaseObject implements CacheInterface {

    /** @var \Predis\Client $redis */
    protected $redis;

    /** @var string $prefix prefix for the redis keys */
    public static $prefix = "cache:v1";

    protected function init()
    {
        parent::init();
        if ($this->redis == null)
            $this->redis = Kiss::$app->redis();
    }

    
    public function set($key, $data) { 
        $serialized = serialize($data);
        $this->redis->set(static::path($key), $serialized);
        return $this;
    }

    public function get($key) { 
        $serialized = $this->redis->get(static::path($key));
        return unserialize($serialized);
    }

    /** @inheritdoc */
    public function has($key) { 
        return $this->redis->exists(static::path($key)) !== 0;
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

    public function ttl($key, $seconds) { 
        $this->redis->expire(static::path($key), $seconds);
        return $this;
    }

    public function delete($key) { 
        $this->redis->del(static::path($key));
    }

    /**
     * Builds the composite key into a solid namespace
     * @param string|object|array $key The composite key of the cached data.
     * @return string
     */
    public static function path($key) {
        if (!is_array($key)) {
            return  static::$prefix . ':' . static::obj2path($key);
        }

        $folders = [ static::$prefix ];
        foreach($key as $obj) {
            $folders[] = static::obj2path($obj);
        }
        return join(':', $folders);
    }

    private static function obj2path($obj) {
        if (is_array($obj))
            return static::path($obj);
        if ($obj instanceof ActiveRecord) 
            return $obj->getKey();
        if (is_object($obj))
            return uniqid() . spl_object_hash($obj);
        return strval($obj);
    }
}