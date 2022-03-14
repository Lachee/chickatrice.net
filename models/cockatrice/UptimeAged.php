<?php namespace app\models\cockatrice;

use DateTime;
use kiss\db\ActiveQuery;
use kiss\db\ActiveRecord;
use kiss\exception\QueryException;
use kiss\exception\ArgumentException;
use kiss\exception\SQLException;
use kiss\exception\SQLDuplicateException;
use kiss\exception\InvalidOperationException;
use kiss\helpers\HTTP;
use kiss\helpers\Strings;

class UptimeAged extends Uptime {
    public $age;
    
    /** @inheritdoc */
    public static function find() {
        $query = parent::find();
        return $query->fields(['*', 'CURRENT_TIMESTAMP - `timest` as age']);
    }
}