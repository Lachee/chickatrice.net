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
class DailyRoute extends BaseApiRoute {
    use \kiss\controllers\api\Actions;

    const MAX_COUNT = 60*4;

    public $fromDate = null;

    //We are going to return our routing. Any segment that starts with : is a property.
    // Note that more explicit routes get higher priority. So /example/apple will take priority over /example/:fish
    protected static function route() { return "/status/daily"; }

    //HTTP GET on the route. Return an object and it will be sent back as JSON to the client.
    // Throw an exception to send exceptions back.
    // Supports get, delete
    public function get() {
        $this->fromDate = HTTP::get('from', date("Y-m-d 00:00:00", strtotime("-2 weeks")));
        return $this->cache(function() {
            return [
                'users'     => $this->countDailyUsers(),
                'games'     => $this->countDailyGames(),
                'countries' => $this->countCountries(),
            ];
        }, 60 * 60, 2);
    }

    public function countCountries() {
        return Chickatrice::$app->db()->createQuery()
            ->select('cockatrice_sessions', [
                'COUNT(*) as value',
                '`cockatrice_users`.`country`'
            ])
            ->leftJoin('cockatrice_users', [ 'user_name' => 'name' ])
            ->groupBy('country')
            ->orderByDesc('start_time')
            ->where(['start_time', '>', $this->fromDate])
            ->andWhere([['country', '<>', '']])
            ->andWhere(['country', 'IS NOT', null])
            ->limit(1000)
            ->execute(true);
    }

    public function countDailyGames() {
        return Chickatrice::$app->db()->createQuery()
                    ->select('cockatrice_games', [
                        'CONCAT(EXTRACT(YEAR_MONTH FROM `time_started`), DAY(`time_started`)) as date',
                        'COUNT(*) as value',
                    ])
                    ->groupBy('date')
                    ->orderByDesc('time_started')
                    ->where(['time_started', '>', $this->fromDate])
                    ->limit(10000)
                    ->execute(true);
    }

    public function countDailyUsers() {
        return Chickatrice::$app->db()->createQuery()
                    ->select('cockatrice_sessions', [
                        'CONCAT(EXTRACT(YEAR_MONTH FROM `start_time`), DAY(`start_time`)) as date',
                        'COUNT(DISTINCT clientid) as value',
                    ])
                    ->groupBy('date')
                    ->orderByDesc('start_time')
                    ->where(['start_time', '>', $this->fromDate])
                    ->limit(10000)
                    ->execute(true);
    }
}