<?php namespace kiss\db;

use Exception;
use kiss\exception\ArgumentException;
use kiss\exception\DuplicateEntryException;
use kiss\exception\QueryException;
use kiss\exception\SQLDuplicateException;
use kiss\exception\SQLException;
use kiss\Kiss;
use kiss\models\BaseObject;

class Query extends BaseObject{

    protected const QUERY_SELECT = 'SELECT';
    protected const QUERY_SELECT_MINIMISE = 'SELECT_MINIMISE';
    protected const QUERY_DELETE = 'DELETE';
    protected const QUERY_UPDATE = 'UPDATE';
    protected const QUERY_INSERT = 'INSERT';
    protected const QUERY_INSERT_OR_UPDATE = 'INSERT_OR_UPDATE';
    protected const QUERY_INCREMENT = 'INCREMENT';

    /** @var int $cacheDuration how long in second cached results last for. */
    public $cacheDuration = 1;
    protected $cacheVersion = 7;
    protected $flushCache = false;

    public $remember = true;

    protected $conn;
    
    protected $query;
    protected $from;
    protected $values = [];
    protected $fields = [];
    protected $limit = null;
    protected $orderBy = null;
    protected $order = 'DESC';
    protected $includeNull = false;
    protected $join = [];
    protected $groupBy = null;
    protected $incrementAmount = 1;

    
    /** @var mixed Stores the where rules.
     * Array of arrays, where the inner array looks like this: [$method, $field, $operator, $value ] */
    protected $wheres = [];
    
    private static $_memcache = [];
    private static $_execLog = [ 'QUERY' => [],  'CACHE' => [], 'REPEAT' => [] ];
    public static function execLog() { return self::$_execLog; }

    protected function init() {
        parent::init();
        if ($this->conn == null)
            throw new ArgumentException('Query cannot have a null connection');
    }

    /** The current DB connection */
    public function db() : Connection { return $this->conn; }

    /** Sets the table.
     * @param string $tableName name of the table.
     * @return $this
     */
    public function from($tableName) {
        $this->from = $tableName;
        return $this;
    }

    /** Performs a SQL SELECT
     * @param string $from name of the table.
     * @param string[] $fields the list of fields
     * @return $this
     */
    public function select($from = null, $fields = null)
    {
        $this->query = self::QUERY_SELECT; //$fields === null ? self::QUERY_SELECT : self::QUERY_SELECT_MINIMISE;
        $this->from = $from ?? $this->from;
        $this->fields = $fields ?? [ "*" ];
        return $this;
    }

    /** Selects only the specified fields.
     * @param string|string[] $fields the columns to select
     * @return $this */
    public function fields($fields) {
        if (is_array($fields)) {
            $this->fields = $fields;
        } else {
            $this->fields = [ $fields ];
        }

        return $this;
    }

    /** sets if null parameters should be included. 
     * @param bool $state
     * @return $this
    */
    public function withNull($state = true) {
        $this->includeNull = $state;
        return $this;
    }

    /** Deletes a record from the table 
     * @param string $from name of the table.
     * @return $this
    */
    public function delete($from = null) {
        $this->query = self::QUERY_DELETE;
        $this->from = $from ?? $this->from;

        $this->remember = false;
        $this->cacheDuration = -1;
        return $this;
    }

    /** Updates a table
     * @param string[] $values the values to update
     * @param string $from name of the table.
     * @return $this
    */
    public function update($values, $from = null) {
        $this->query = self::QUERY_UPDATE;
        $this->values = $values;
        $this->from = $from ?? $this->from;

        $this->remember = false;
        $this->cacheDuration = -1;
        return $this;
    }

    /** Increments the fields by the given amount
     * @param string[] $fields the fields to increment
     * @param int $amount how much to increment by
     * @param string $from name of the table.
     * @return $this
     */
    public function increment($fields, $amount = 1, $from = null) {
        if (!is_integer($amount)) throw new ArgumentException('amount must be a integer');
        $this->query = self::QUERY_INCREMENT;
        $this->fields = $fields;
        $this->form = $from ?? $this->from;
        $this->incrementAmount = intval($amount);
        return $this;
    }

    /** Insert into a table
     * @param string[] $values the values to update
     * @param string $table name of the table.
     * @return $this
    */
    public function insert($values, $table = null) {
        $this->query = self::QUERY_INSERT;
        $this->values = $values;
        $this->from = $table ?? $this->from;

        $this->remember = false;
        $this->cacheDuration = -1;
        return $this;
    }

    /** Inserts or Updates a table
     * @param string[] $values the values to update
     * @param string $from name of the table.
     * @return $this
    */
    public function insertOrUpdate($values, $from = null) {
        $this->query = self::QUERY_INSERT_OR_UPDATE;
        $this->values = $values;
        $this->from = $from ?? $this->from;
        
        $this->remember = false;
        $this->cacheDuration = -1;
        return $this;
    }

    /** Left Joins another table
     * @param string $table the table to join
     * @param array $on the rule to join on. In the format of ['left_column' => 'right_column']
     * @return $this
     */
    public function leftJoin($table, $on) {
        return $this->join($table, $on, 'LEFT JOIN');
    }

    /** Right Joins another table
     * @param string $table the table to join
     * @param array $on the rule to join on. In the format of ['left_column' => 'right_column']
     * @return $this
     */
    public function rightJoin($table, $on) {
        return $this->join($table, $on, 'RIGHT JOIN');
    }
    
    /** Joins another table
     * @param string $table the table to join
     * @param array $on the rule to join on. In the format of ['left_column' => 'right_column']
     * @return $this
     */
    public function join($table, $on, $joinType = 'JOIN') {
        if (count($on) != 1) throw new ArgumentException('Join $on must be associative');
        $this->join[] = [
            'type'  => $joinType,
            'table' => $table,
            'on'    => $on
        ];
        return $this;
    }

    /** Groups by a field
     * @param string $field the field to group on
     * @return $this
     */
    public function groupBy($field) {
        $this->groupBy = $field;
        return $this;
    }

    /** Sets how long the results will last for.
     * @param int $duration seconds to live. If below 0, then they will not be cached.
     * @return $this
     */
    public function cache($duration) {
        $this->cacheDuration = $duration;
        return $this;
    }

    /** Sets how long the result will be cached for. Alias of cache
     * @param int $duration duration of cache
     * @return $this
     */
    public function ttl($duration) {
        return $this->cache($duration);
    }

    /** Tells the query if it should remember or not. */
    public function remember($state = true) {
        $this->remember = $state;
        return $this;
    }

    /** Ensures the value is pulled and clears the cache.
     * @return $this
     */
    public function flush() {
        $this->flushCache = true;
        return $this;
    }

    /** Where condition. 
     * @param array[] $params parameters. eg: [ [ key, value ], [ key, op, value ] ], [ key, value ], [ operation ]
     * @param string $method operator, ie and.
     * @return $this
    */
    public function where($params, $method = 'and') {
        if (!is_array($params)) 
            throw new QueryException($this, "where parameter is not an array");

        if (count($params) == 0)
            throw new QueryException($this, "where parameter cannot be empty");

        if (!isset($params[0]))
            throw new QueryException($this, "where paramter needs to be an non-assoc array");

        if (is_array($params[0])) {
            //We are an array of AND WHERES
            // so we will recursively add them
            foreach($params as $p) {
                $this->where($p, $method);
            }

            return $this;
        }

        if (count($params) == 1) {            
            $this->wheres[] = [ $method, $params[0] ];
        } else {
            $field = ''; $operator = '='; $value = '';
            if (count($params) == 2) {       
                $field = $params[0];
                $value = $params[1];
                if ($value instanceof Query || is_array($value)) { $operator = ''; }
                if ($value === null) $operator = 'IS';
            } else {
                $field = $params[0];
                $operator = $params[1];
                $value = $params[2];
            }

            $this->wheres[] = [ $method, $field, $operator, $value ];
        }

        return $this;
    }

    /** And Where on the query
     * @param mixed[] $params parameters.
     * @return $this 
    */
    public function andWhere($params) { return $this->where($params, 'and'); }

    /** Or Where on the query
     * @param mixed[] $params parameters.
     * @return $this 
    */
    public function orWhere($params) { return $this->where($params, 'or'); }

    /** Limit the count of values returned
     * @param int $count the number of rows to limit
     * @return $this 
    */
    public function limit($count, $skip = 0) { 
        $this->limit = [ $skip, $count ];
        return $this;
    }

    /** Returns the current limit */
    public function getLimit() { return $this->limit; }

    /** Order the query by the value */
    public function orderByDesc($field) {
        $this->orderBy = $field;
        $this->order = 'DESC';
        return $this;
    }

    /** Order the query by the value ascending. */
    public function orderByAsc($field) { 
        $this->orderBy = $field;
        $this->order = "ASC";
        return $this;
    }

    /** Checks if there is at least 1 record.
     *  Changes the SQL into a minimised select and limits the result to 1. 
     *  @return bool true if there is an element */
    public function any() {
        $this->query = self::QUERY_SELECT_MINIMISE;
        $this->limit(1);
        $result = $this->execute();
        return count($result) > 0;
    }

    /** Minimises the select to only the fields required 
     * @return array
    */
    private function minimise() {
        if ($this->query != self::QUERY_SELECT_MINIMISE) 
            throw new QueryException($this, 'Cannot minimise a non-select query');

         //Add existing fields
         $fields = [];
        foreach($this->fields as $field) {
            if ($field != '*') $fields[] = $field;
        }

        //Add all the wheres
        if ($this->wheres !== null) {
            foreach($this->wheres as $w) {
                if (count($w) == 4)
                    $fields[] = $w[1];
            }
        }

        //Add all the orderBy
        if (!empty($this->orderBy))
            $this->fields[] = $this->orderBy;

        //Add all the joins
        if ($this->join) {
            foreach($this->join as $join) {
                foreach($join['on'] as $key => $pair) {
                    $this->fields[] = $key;
                    $this->fields[] = $pair;
                }
            }
        }

        //Set the fields
        return array_unique($fields);
    }

    /** Builds the query 
     * @return array Array containing the query and an array of binding values.
    */
    public function build() {
        if (empty($this->from))
            throw new QueryException('Cannot build query as the TABLE is empty');

        if (empty($this->query)) 
            throw new QueryException('Cannot build query as we dont know what kind of query it is!');

        $query = "";

        $bindings = [];

        $value_fields   = [];
        $value_binds    = [];

        foreach($this->values as $key => $pair) {
            if ($pair !== null || $this->includeNull) {
                //Regular binding
                $value_fields[] = $key;
                $value_binds[] = "?";
                if (is_bool($pair)) $pair = $pair === true ? 1 : 0;
                $bindings[]     = $pair;
            }
        }

        switch ($this->query) {
            
            case self::QUERY_SELECT:
            case self::QUERY_SELECT_MINIMISE:
                $fields = join(", ", $this->query == self::QUERY_SELECT_MINIMISE ? $this->minimise() : $this->fields);
                $query = "SELECT {$fields} FROM {$this->from}";
                break;

            case self::QUERY_DELETE:
                $query = "DELETE FROM {$this->from}";
                break;

            case self::QUERY_UPDATE:
                $query = "UPDATE {$this->from} SET ". $this->buildUpdateQuery($this->values);
                break;

            case self::QUERY_INSERT:
                $query = "INSERT INTO {$this->from} (".join(',', $value_fields).") VALUES (".join(',', $value_binds).")";
                break;            
                
            case self::QUERY_INSERT_OR_UPDATE:
                $query = "INSERT INTO {$this->from} (".join(',', $value_fields).") VALUES (".join(',', $value_binds).") ON DUPLICATE KEY UPDATE " . $this->buildUpdateQuery($this->values, $bindings);
                $this->wheres = null;
                $this->limit = null;
                $this->orderBy = null;
                break;

            case self::QUERY_INCREMENT:
                if (!is_integer($this->incrementAmount))
                    throw new ArgumentException('amount must be a integer');
                $increments = [];
                foreach($this->fields as $field)
                    $increments[] = "{$field} = {$field} + {$this->incrementAmount}";
                
                $query = "UPDATE {$this->from} SET " . join(', ', $increments);
                break;
        }

        //Add the joins
        $joins = " ";
        if ($this->join != null && is_array($this->join)) {
            foreach($this->join as $join) {
                $table  = $join['table'];
                $keys   = \array_keys($join['on']);
                $lhs    = $keys[0];
                $rhs    = $join['on'][$lhs];
                $joins .= "{$join['type']} `{$table}` ON `{$this->from}`.`{$lhs}` = `{$table}`.`{$rhs}`";
            }
        }
        $query .= $joins;

        //Create the where statement
        $wheres = "";
        if ($this->wheres != null && is_array($this->wheres)) {
            foreach ($this->wheres as $w) {
                
                $prefix = empty($wheres) ? " WHERE" : " {$w[0]}";
                
                if (count($w) == 2) {
                    $wheres .= "{$prefix} {$w[1]}";
                } else  if ($w[3] === null) {
                    $wheres .= "{$prefix} {$w[1]} {$w[2]} NULL";
                } else if ($w[3] instanceof Query) {
                    /** @var Query $q */
                    $q = $w[3];

                    //Validate the query type
                    if ($q->query != self::QUERY_SELECT && $q->query != self::QUERY_SELECT_MINIMISE)
                        throw new ArgumentException('IN sub queries have to be a SELECT query!');
                    
                    //Make sure we dont minimise it
                    $q->query = self::QUERY_SELECT;

                    //Build the query.
                    [ $wQuery, $wBindings ] = $q->build();
                    $wheres .= "{$prefix} {$w[1]} {$w[2]} IN ({$wQuery})";
                    $bindings = array_merge($bindings, $wBindings);
                } else if (is_array($w[3])) {

                    if (count($w[3]) == 0)
                        throw new ArgumentException('IN lists require to have some elements');

                    //Skip this condition and replace with "0 = 1"
                    if (!isset($w[3][0]))
                        throw new ArgumentException('IN lists have to be indexed!');
                
                    $bs     = str_repeat('?, ', count($w[3]));
                    $wQuery = trim($bs, ', ');
                    $wheres .= "{$prefix} {$w[1]} {$w[2]} IN ({$wQuery})";
                    $bindings = array_merge($bindings, $w[3]);
                } else {
                    if (is_bool($w[3])) $w[3] = $w[3] === true ? 1 : 0;
                    $wheres .= "{$prefix} {$w[1]} {$w[2]} ?";
                    $bindings[] = $w[3];
                }
            }
        }
        $query .= $wheres;
        
        //Add the group by
        if ($this->groupBy != null) {
            $query .= " GROUP BY {$this->groupBy} ";
        }

        //Create the order statement
        if ($this->orderBy != null) {
            $query .= " ORDER BY {$this->orderBy} {$this->order}";
        }
        
        //Create the limit
        if ($this->limit != null && count($this->limit) == 2 && $this->limit[1] != 0) {
            if ($this->limit[0] == 0) { 
                $query .= " LIMIT {$this->limit[1]}";
            } else {
                $limit = join(',', $this->limit);
                $query .= " LIMIT {$limit}";
            }
        }

        //Return the query and binding
        return array($query, $bindings);
    }

    /** Builds the SET a = ? for updates */
    private function buildUpdateQuery($values, &$bindings = []) {
        $dupe = [];
        foreach($values as $key => $pair) {
            if ($pair !== null || $this->includeNull) {
                $dupe[] = $key . " = ?";
                if (is_bool($pair)) $pair = $pair === true ? 1 : 0;
                $bindings[]     = $pair;
            }
        }
        return join(', ', $dupe);
    }

    /** Builds the query and executes it, returning the result of the execute.
     * @return array|int|false  If the query is select then it will return an associative array of the object; otherwise it will return the last auto incremented id or the number of rows effected.
     */
    public function execute() {
        list($query, $bindings) = $this->build();
        $querySummary = self::createPreviewStatement($query, $bindings); //$query . ';?' . join(', ', $bindings);
        
        //Check the cache if we ahve the duration for it
        $redis      = Kiss::$app->redis();
        $cacheKey   = 'query:' . $this->cacheVersion . ':' . md5($querySummary);

        //Check if its in the memory
        if (!$this->flushCache && $this->remember && isset(self::$_memcache[$cacheKey])) {
            self::$_execLog['REPEAT'][] = $querySummary;
            return self::$_memcache[$cacheKey];
        }

        //Get it from the cache
        if (!$this->flushCache && $redis != null && $this->cacheDuration > 0) {
            $cacheResults = $redis->get($cacheKey);
            if ($cacheResults != null) {
                self::$_execLog['CACHE'][] = $querySummary;
                $data = unserialize($cacheResults);
                if ($this->remember) self::$_memcache[$cacheKey] = $data;
                return $data;
            }
        }
        
        $stm = $this->conn->prepare($query);
        for($i = 0; $i < count($bindings); $i++) {
            $stm->bindParam($i + 1, $bindings[$i]);
        }

        //Execute and check if we fail or not
        self::$_execLog['QUERY'][] = $querySummary;
        $result = $stm->execute();
        if (!$result) {
            $err = $stm->errorInfo();
            switch($err[1]) {
                default:
                    throw new SQLException($this, $query, $err[2], $err[1]);
                case SQLException::ER_DUP_ENTRY:
                case SQLException::ER_DUP_KEYNAME:
                case SQLException::ER_DUP_FIELDNAME:
                    throw new SQLDuplicateException($this, $query, $err[2], $err[1]);

            }
        }

        //Select is the only one where we want to return the object
        if ($this->query === self::QUERY_SELECT || $this->query === self::QUERY_SELECT_MINIMISE) {
            $result = $stm->fetchAll(\PDO::FETCH_ASSOC);

            //Store the result in memory
            if ($this->remember) 
                self::$_memcache[$cacheKey] = $result;

            //We should cache it if we can
            if ($redis != null && $this->cacheDuration > 0) {
                $data = serialize($result);
                $redis->set($cacheKey, $data);
                $redis->expire($cacheKey, $this->cacheDuration);
            }

            //finally return the result
            return $result;
        }

        //Return the last inserted id if its an insert query
        if ($this->query === self::QUERY_INSERT || $this->query === self::QUERY_INSERT_OR_UPDATE)
            return $this->lastId();

        //Otherwise retunr number of rows affected
        return $stm->rowCount();
    }

    public function lastId() { return $this->conn->lastInsertId(); }

    /** Gets teh statement for debugging purposes. DO NOT EXECUTE THIS.
     * @return string the SQL statement
     */
    public function previewStatement() {
        list($query, $bindings) = $this->build();
        return self::createPreviewStatement($query, $bindings);
    }

    /** @return string Gets the statement for debugging purposes. */
    private static function createPreviewStatement($query, $bindings) {
        foreach($bindings as $b) { 
            $index = strpos($query, '?');
            $lhs = substr($query, 0, $index);
            $rhs = substr($query, $index + 1);
            $query = $lhs . $b . $rhs;
        }
        return $query;
    }
}
