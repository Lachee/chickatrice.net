<?php namespace app\controllers\api\status;

use app\models\cockatrice\Uptime;
use app\models\cockatrice\UptimeAged;
use app\controllers\api\BaseApiRoute;
use Chickatrice;
use kiss\exception\HttpException;
use kiss\helpers\HTTP;
use kiss\helpers\Response;
use PDO;

class ShieldRoute extends BaseApiRoute {
    use \kiss\controllers\api\Actions;

    /** @var int how fast in seconds before we consider the server down? */
    public $maxTimeSinceLastUptime = 15;
    
    //We are going to return our routing. Any segment that starts with : is a property.
    // Note that more explicit routes get higher priority. So /example/apple will take priority over /example/:fish
    protected static function route() { return "/status/shield"; }

    //HTTP GET on the route. Return an object and it will be sent back as JSON to the client.
    // Throw an exception to send exceptions back.
    // Supports get, delete
    public function get() {
        $schema = $this->cache(function() {
            $latest = UptimeAged::find()
                        ->orderByDesc('timest')
                        ->one();  

            // If we are offilne, return that.
            if ($latest->age > $this->maxTimeSinceLastUptime) {
                return [
                    'schemaVersion' => 1,
                    'label' => 'offline',
                    'message' => $latest->age . 's',
                    'color' => 'red'
                ];
            }

            // Return the field we want
            $field  = HTTP::get('field', 'users_count');
            $title  = HTTP::get('field', 'online');
            $colour  = HTTP::get('colour', 'blue');
            $data   = $latest->getProperty($field, 0);

            return [
                'schemaVersion' => 1,
                'label'     => $title,
                'message'   => $data,
                'color'     => $colour
            ];
        }, 15, 1);

        return Response::jsonRaw(HTTP::OK, $schema);
    }

}