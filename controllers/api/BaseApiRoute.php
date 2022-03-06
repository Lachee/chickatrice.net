<?php namespace app\controllers\api;

use app\models\Ban;
use app\models\User;
use GALL;
use kiss\controllers\api\ApiRoute;
use kiss\exception\HttpException;
use kiss\helpers\HTTP;
use kiss\helpers\Scope;
use kiss\Kiss;
use Ramsey\Uuid\Uuid;

/**
 * @property User $actingUser the identity we are currently acting on behalf.
 * @package app\controllers\api
 */
class BaseApiRoute extends ApiRoute {
    use \kiss\controllers\api\Actions;

    /** @var User the identity we are acting on behalf */
    private $_user;

    /** @inheritdoc */
    public function getActingUser() { return $this->_user ?? GALL::$app->user; }

    /** @inheritdoc */
    protected function scopes() { return GALL::$app->allowVisitors ? null : [ ]; } 

    /** @inheritdoc */
    public function authenticate($identity)
    {
        //Do base auth stuff
        parent::authenticate($identity);

        //If we have impersonate, and the header, then lets set our "currentIdentity"
        $snowflake = HTTP::header('X-Actors-Snowflake', false);
        if ($snowflake !== false) {
            if (!Scope::check($identity, 'bot.impersonate'))
                throw new HttpException(HTTP::FORBIDDEN, 'Cannot act as a snowflake, missing permission.');
            
            $this->_user = User::findBySnowflake($snowflake)->ttl(0)->remember(false)->one();

            //Create the user if we didnt find any, making sure we dont try if we already have
            if ($this->_user == null) {

                //Make sure we havn't already done this snowflake. If not, then make sure we dont do it again
                $redis_key = 'impersonations:snowflake:' . $snowflake;
                $impersonated = GALL::$app->redis()->get($redis_key);
                if ($impersonated !== null) throw new HttpException(HTTP::BAD_REQUEST, 'Cannot act as invalid snowflakes.');
                GALL::$app->redis()->set($redis_key, $snowflake);
                GALL::$app->redis()->expire($redis_key, 3600);

                //If this user is specifically banned, lets anon it.
                $ban = Ban::findBySnowflake($snowflake)->one();
                if ($ban != null) {
                    $user = User::findAnnon()->one();
                    if ($user == null) throw new HttpException(HTTP::BAD_REQUEST, 'Cannot act as invalid snowflakes.');
                    $this->_user = $user;
                    return;
                }

                //We didnt find one, so setup the discord user
                $duser = GALL::$app->discord->getUser($snowflake);
                if ($duser == null) throw new HttpException(HTTP::BAD_REQUEST, 'Cannot act as invalid snowflakes.');

                //Create a new user
                $user = new User([
                    'uuid'      => Uuid::uuid1(GALL::$app->uuidNodeProvider->getNode()),
                    'username'  => $duser->username,
                    'snowflake' => $duser->id,
                ]);

                //We failed to save so abort
                if (!$user->save()) 
                    throw new HttpException(HTTP::INTERNAL_SERVER_ERROR, 'Failed to create actors account. ' . join('. ', $user->errors()));

                //Finally, link back who we are impersonating
                $this->_user = $user;
            }
        }
    }
}