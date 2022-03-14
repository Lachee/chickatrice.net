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

    /**
     * Gets all the uptimes that are longer than a second
     * @param int $count number of times
     * @return ActiveQuery 
     * @throws QueryException 
     */
    public static function getUptimes($count = 10) {
        return self::find()->where(['uptime', '>', '1'])->orderByDesc('timest')->limit($count);
    }

    /** Gets the uptimes for each minute
     * @param int $count the number of times to get
     * @return ActiveQuery|Uptime[]
     */
    public static function getMinutelyUptimes($count = 10) {
        //SELECT * FROM `cockatrice_uptime` WHERE timest IN ( SELECT max(timest) FROM `cockatrice_uptime` GROUP BY EXTRACT(HOUR_MINUTE FROM `timest`) ) ORDER BY timest DESC; 
        $groupQuery = self::find()->fields(['max(timest)'])->groupBy('EXTRACT(HOUR_MINUTE FROM `timest`)')->orderByDesc('timest');
        return self::find()->where(['timest', $groupQuery])->orderByDesc('timest')->limit($count);
    }
}