<?php namespace kiss\exception;

use Throwable;

class SQLException extends QueryException {

    // List of error codes https://mariadb.com/kb/en/mariadb-error-codes/

    public const ER_DUP_FIELDNAME = 1060;
    public const ER_DUP_KEYNAME = 1061;
    public const ER_DUP_ENTRY = 1062;

    /** @var string SQL */
    public $sql;

    /** {@inheritdoc}
     * @param string $SQL
     */
    public function __construct($query, $sql, $message = '', $code = 0, Throwable $previous = null) {
        parent::__construct($query, $message, $code, $previous);
        $this->sql = $sql;
    }
}