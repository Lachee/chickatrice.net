<?php namespace app\controllers;

use app\components\mixer\Mixer;
use app\models\Ban;
use app\models\Guild;
use kiss\exception\HttpException;
use kiss\helpers\HTTP;
use kiss\helpers\Response;
use kiss\models\BaseObject;
use app\models\User;
use Exception;
use Chickatrice;
use kiss\exception\ArgumentException;
use kiss\helpers\Arrays;
use kiss\Kiss;
use Ramsey\Uuid\Uuid;

class MainController extends BaseController {
    public static function route() { return "/main"; }

    /** Checks the authorization of the user
     * @return bool true if they are allowed here. False if they should be redirected to login first.
     * You can also throw exceptions here if requried to tell the user off.
     */
    public function authorize($action) { return true; }

    function actionIndex() {
        if (Kiss::$app->loggedIn()) return Response::redirect(['/gallery/']);
        return $this->render('index', [
            'fullWidth' => true,
            'wrapContents' => false,
        ]);
    }

    /** View the JWT */
    function actionJWT() {
        return $this->render('jwt', [
            'key' => Kiss::$app->jwtProvider->publicKey,
            'fullWidth' => true,
            'wrapContents' => false,
        ]);
    }

    /** Logs In */
    function actionLogin() {
        Kiss::$app->session->set('LOGIN_REFERRAL', HTTP::referral());
        return Chickatrice::$app->discord->redirect();
    }

    /** Logs Out */
    function actionLogout() {
        if (($user = Kiss::$app->getUser()))  $user->logout();
        return Response::redirect('/');
    }

    /** Authorizes */
    function actionAuth() {
        try 
        { 
            //Get the tokens.
            $tokens = Chickatrice::$app->discord->handleRequest();
            if ($tokens === false) return $this->actionLogin();

            //Get the discord user
            $duser  = Chickatrice::$app->discord->identify($tokens);
            $ban = Ban::findBySnowflake($duser->id)->one();
            if ($ban != null) throw new ArgumentException('Invalid snowflake');

            //Get the user, otherwise create one.
            /** @var User $user */
            $user = User::findBySnowflake($duser->id)->one();
            if ($user == null) {
                $user = new User([
                    'uuid' => Uuid::uuid1(Chickatrice::$app->uuidNodeProvider->getNode()),
                    'username' => $duser->username,
                    'snowflake' => $duser->id,
                ]);
                Chickatrice::$app->session->addNotification('Your account has been created');
            }


            //Store the tokens
            Chickatrice::$app->discord->getStorage($user->uuid)->setTokens($tokens);
            $guilds = Chickatrice::$app->discord->getGuilds($tokens);
            $guilds = Arrays::map($guilds, function($g) { return $g['id']; });
            $guilds = Guild::find()->where(['snowflake', $guilds ])->all();
            if ($guilds == null || count($guilds) == 0) { 
                Chickatrice::$app->session->addNotification('Cannot possibly logged in because you do not share a server', 'danger');
                throw new Exception('Not in any guilds');
            }
            
            //Add the user to each guild
            foreach($guilds as $guild) {
                $user->addGuild($guild);
            }

            //Update our name and save
            $user->username = $duser->username;
            $user->save();

            //Actually login
            if (!$user->login()) {         
                Kiss::$app->session->addNotification('Failed to login for some reason', 'danger');
            }
        } 
        catch(\Exception $e) 
        {
            Chickatrice::$app->session->addNotification('Woops, something went wrong while trying to perform that action! ' . ($e->getMessage()), 'danger');
        }         
        
        $referal = Kiss::$app->session->get('LOGIN_REFERRAL', HTTP::referral());
        return Response::redirect($referal ?? [ '/gallery/']);
    }
}