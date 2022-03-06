<?php namespace kiss\db;

use kiss\exception\QueryException;

class ActiveQuery extends Query {

    public static $memoryCache = true;
    private static $_memcacheVersion = 0;
    private static $_memcache = [];

    protected $className;

    /** @return string Class that is being queried. */
    public function class() {return $this->className; }

    protected function init()
    {
        parent::init();
        $this->select($this->className);
    }


    /** @inheritdoc */
    public function join($table, $on, $joinType = 'JOIN') {
        if (is_subclass_of($table, ActiveRecord::class, true)) {
            $table = $table::tableName();
        }
        return parent::join($table, $on, $joinType);
    }

    /** @inheritdoc */
    public function where($params, $method = 'and') {
        if (!is_array($params))
            throw new QueryException($this, "where parameter is not an array");

        if (count($params) == 0)
            throw new QueryException($this, "where parameter cannot be empty");

        if (!isset($params[0]))
            throw new QueryException($this, "where paramter needs to be an non-assoc array");

            
        if (is_array($params[0])) {
            foreach($params as $p) {
                $this->where($p, $method);
            }
            return $this;
        }

        //Converts ActiveRecord into the key
        foreach($params as $key => $pair) {
            if ($pair instanceof ActiveRecord)
                $params[$key] = $pair->getKey();
        }

        return parent::where($params, $method);
    }

    
    /** Fetches a single record 
     * @param bool $assoc Should associative arrays be returned instead? 
     * @param bool $extractScalar Should scalar values be removed from their object? Useful for just quering a single entry value (for example, getting username from a record id).
     * @return ActiveRecord|null|false the records
    */
    public function one($assoc = false, $extractScalar = false) {
        //Execute the query
        $result = $this->limit(1)->execute();
        if ($result !== false) {
            foreach($result as $r) {

                if ($assoc) { 
                    $instance =  $extractScalar && count($this->fields) == 1 ? $r[$this->fields[0]] : $r;
                } else {
                    //Create a new instance of the class
                    $instance = new $this->className;
                    $instance->setQueryResult($r);
                }
                
                return $instance; 
            }
        }

        return false;
    }
    
    /** Fetch all records.
     * @param $assoc Should associative arrays be returned instead?
     * @param $extractScalar Should scalar values be removed from their object?
     * @return ActiveRecord[]|false the records
     */
    public function all($assoc = false, $extractScalar = false) {

        //Prepare a list of instances
        $instances = [];

        //TODO: Implement Cache

        //Execute the query
        $result = $this->execute();
        if ($result !== false) {

            foreach($result as $r) {
                if ($assoc) { 
                    $instance = $extractScalar && count($this->fields) == 1 ? $r[$this->fields[0]] : $r;
                } else {
                    //Create a new instance of the class
                    $instance = new $this->className;
                    $instance->setQueryResult($r);
                }

                $instances[] = $instance;
            }

            return $instances;
        }

        return $instances;
    }

    /** Purges the temporary cache used to minimise requests */
    public static function purgeMemoryCache() {
        self::$_memcacheVersion++;
    }

}