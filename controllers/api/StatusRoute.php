<?php namespace app\controllers\api;

use app\models\cockatrice\Uptime;
use app\models\cockatrice\UptimeAged;
use kiss\controllers\api\ApiRoute;
use kiss\db\ActiveQuery;
use kiss\exception\HttpException;
use kiss\helpers\HTTP;
use kiss\Kiss;
use kiss\router\Route;
use kiss\router\RouteFactory;

class StatusRoute extends BaseApiRoute {
    use \kiss\controllers\api\Actions;

    const MAX_COUNT = 60*4;

    /** @var int how fast in seconds before we consider the server down? */
    public $maxTimeSinceLastUptime = 15;

    //We are going to return our routing. Any segment that starts with : is a property.
    // Note that more explicit routes get higher priority. So /example/apple will take priority over /example/:fish
    protected static function route() { return "/status"; }

    //HTTP GET on the route. Return an object and it will be sent back as JSON to the client.
    // Throw an exception to send exceptions back.
    // Supports get, delete
    public function get() {
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