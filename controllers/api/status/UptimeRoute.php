<?php namespace app\controllers\api\status;

use app\models\cockatrice\Uptime;
use app\models\cockatrice\UptimeAged;
use app\controllers\api\BaseApiRoute;
use Chickatrice;
use kiss\exception\HttpException;
use kiss\helpers\HTTP;
use PDO;

class UptimeRoute extends BaseApiRoute {
    use \kiss\controllers\api\Actions;

    const MAX_COUNT = 60*4;

    /** @var int how fast in seconds before we consider the server down? */
    public $maxTimeSinceLastUptime = 15;

    //We are going to return our routing. Any segment that starts with : is a property.
    // Note that more explicit routes get higher priority. So /example/apple will take priority over /example/:fish
    protected static function route() { return "/status/uptime"; }

    //HTTP GET on the route. Return an object and it will be sent back as JSON to the client.
    // Throw an exception to send exceptions back.
    // Supports get, delete
    public function get() {
        return $this->cache(function() {
            $deep = HTTP::get('deep', false);
            return $deep !== false ? 
                $this->getDeepUptime() : 
                $this->getShallowUptime();
        }, $this->maxTimeSinceLastUptime);
    }

    /** Gets the deep uptime for the stats page */
    public function getDeepUptime() {
$SQL = <<<SQL
SELECT * FROM (
    SELECT @row := @row+1 AS rownum, UNIX_TIMESTAMP(timest) as epoch, users_count, mods_count, games_count, uptime  FROM (
        SELECT @row := 0
    ) r, cockatrice_uptime 
    ORDER BY -timest 
    LIMIT 0,5760
) ranked 
WHERE rownum MOD 60 = 0
SQL;

        $stm = Chickatrice::$app->db()->query($SQL);
        return $stm->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Gets the shallow uptime for the front page */
    public function getShallowUptime() {
        $count = HTTP::get('count', 60);
        if ($count > self::MAX_COUNT)
            throw new HttpException(HTTP::BAD_REQUEST, 'Cannot exceed ' . self::MAX_COUNT);

        $history = Uptime::getMinutelyUptimes()
                ->fields(['timest', 'uptime', 'users_count', 'games_count'])
                ->limit($count)
                ->all(true);

        // Calculate the time since last uptime
        /** @var UptimeAged  */
        $latest = UptimeAged::find()
                            ->orderByDesc('timest')
                            ->one();              
                            
        // Reutrn the result
        return [
            'uptime'  =>  $latest->age > $this->maxTimeSinceLastUptime ? 0 : intval($latest->uptime),
            'max_age' => $this->maxTimeSinceLastUptime,
            'history' => $history
        ];
    }
}