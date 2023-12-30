<?php namespace kiss\db;

use kiss\models\BaseObject;

class Connection  extends \PDO
{
    protected $_table_prefix;
    protected $_table_suffix;

    public function __construct($dsn, $user = null, $password = null, $driver_options = array(), $prefix = null)
    {
        $this->_table_prefix = $prefix;
        parent::__construct($dsn, $user, $password, $driver_options);
    }

    public function exec($statement)
    {
        $statement = $this->_tablePrefixSuffix($statement);
        return parent::exec($statement);
    }

    public function prepare($statement, $driver_options = array())
    {
        $statement = $this->_tablePrefixSuffix($statement);
        return parent::prepare($statement, $driver_options);
    }

    public function query($statemen, $fetchMode = null, ...$fetchModeArgs)
    {
        $statement = $this->_tablePrefixSuffix($statement);
        $args      = func_get_args();

        if (count($args) > 1) {
            return call_user_func_array(array($this, 'parent::query'), $args);
        } else {
            return parent::query($statement, $fetchMode, ...$fetchModeArgs);
        }
    }

    /** Gets the table prefix */
    public function getPrefix() { 
        return $this->_table_prefix;
    }

    protected function _tablePrefixSuffix($statement)
    {
        return str_replace("$", $this->_table_prefix, $statement);
    }

    /** Creates a new query with some options
     * @return Query the query
     */
    public function createQuery($options = []) {
        $options['conn'] = $this;
        return BaseObject::new(Query::class, $options);
    }
}
