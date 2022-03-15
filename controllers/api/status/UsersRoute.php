<?php namespace app\controllers\api\status;

use app\models\cockatrice\Uptime;
use app\models\cockatrice\UptimeAged;
use app\controllers\api\BaseApiRoute;
use Chickatrice;
use kiss\exception\HttpException;
use kiss\helpers\HTTP;
use PDO;

/**
 * Gets data about users
 * @package app\controllers\api\status
 */
class UsersRoute extends BaseApiRoute {
    use \kiss\controllers\api\Actions;

    const MAX_COUNT = 60*4;

    //We are going to return our routing. Any segment that starts with : is a property.
    // Note that more explicit routes get higher priority. So /example/apple will take priority over /example/:fish
    protected static function route() { return "/status/users"; }

    //HTTP GET on the route. Return an object and it will be sent back as JSON to the client.
    // Throw an exception to send exceptions back.
    // Supports get, delete
    public function get() {
        return $this->cache(function() {
            return $this->countUniqueUsers();
        }, 60);
    }

    public function countUniqueUsers() {
        $fromDate = date("Y-m-d 00:00:00", strtotime("-2 weeks"));
        return Chickatrice::$app->db()->createQuery()
                    ->select('cockatrice_sessions', [
                        'CONCAT(EXTRACT(YEAR_MONTH FROM `start_time`), DAY(`start_time`)) as date',
                        'COUNT(DISTINCT clientid) as users',
                    ])
                    ->groupBy('date')
                    ->orderByDesc('start_time')
                    ->where(['start_time', '>', $fromDate])
                    ->execute(true);
    }
}