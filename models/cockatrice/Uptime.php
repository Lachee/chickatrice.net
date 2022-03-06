<?php namespace app\models\cockatrice;

use kiss\db\ActiveQuery;
use kiss\db\ActiveRecord;
use kiss\exception\QueryException;
use kiss\exception\ArgumentException;
use kiss\exception\SQLException;
use kiss\exception\SQLDuplicateException;
use kiss\exception\InvalidOperationException;
use kiss\helpers\HTTP;
use kiss\helpers\Strings;

class Uptime extends ActiveRecord {
    public static function tableName() { return "cockatrice_uptime"; }

    public $id_server;
    public $timest;
    public $uptime;
    public $users_count;
    public $mods_count;
    public $mods_list;
    public $games_count;
    public $rx_bytes;
    public $tx_bytes;

    public static function getUptimes($count = 10) {
        return self::find()->where(['uptime', '>', '1'])->orderByDesc('timest')->limit($count);
    }
}