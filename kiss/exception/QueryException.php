<?php namespace kiss\exception;

use Throwable;

class QueryException extends \Exception {

    /** @var \kiss\db\Query query */
    public $query;

    /** {@inheritdoc}
     * @param \kiss\db\Query $query
     */
    public function __construct($query, $message = '', $code = 0, Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
        $this->query = $query;
    }
}