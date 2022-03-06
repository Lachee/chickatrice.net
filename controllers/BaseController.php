<?php namespace app\controllers;

use app\models\Ban;
use Exception;
use Chickatrice;
use kiss\controllers\Controller;
use kiss\exception\ExpiredOauthException;
use kiss\exception\HttpException;
use kiss\helpers\HTML;
use kiss\helpers\HTTP;
use kiss\helpers\Response;
use kiss\Kiss;
use Mixy;
use XVE;

class BaseController extends Controller {

    //private const LOGIN_ERROR_CODE = HTTP::FORBIDDEN;
    private const LOGIN_ERROR_CODE = HTTP::UNAUTHORIZED;
    
    private $_previousRoute;

    public function authorize($action) {        
        if (!Chickatrice::$app->allowVisitors && !Chickatrice::$app->loggedIn()) return false;
        return true;
    }

    public function action($endpoint, ...$args) {
        
        HTML::$title = Kiss::$app->title;

        //Force a check on the mixer user, validating the oauth. We dont want to apply this rule to the /auth endpoint tho.
        if (Chickatrice::$app->loggedIn()) {

            //If we were unable to validate the user, log them out
            //if ($endpoint != '/auth' && $endpoint != '/login' && $endpoint != 'exception' && !GALL::$app->getUser()->validateDiscordToken()) {
            //    //try { 
            //    //    $discordUser = GALL::$app->getUser()->getDiscordUser();
            //    //} catch(\Exception $ex) { 
            //    //    //We failed to get the user for what ever reason, lets abort
            //    //    Kiss::$app->getUser()->logout(); 
            //    //    Kiss::$app->session->addNotification('Failed to validate the Discord authentication.', 'danger');
            //    //    $referal = Kiss::$app->session->get('LOGIN_REFERRAL', HTTP::referral());
            //    //    return Kiss::$app->respond(Response::redirect($referal));
            //    //}
            //}

            //Update that we have seen them
            Chickatrice::$app->user->seen();

            //Validate they are not on our blacklist
            if (Ban::findBySnowflake(Chickatrice::$app->user->getSnowflake())->one() != null) {
                Kiss::$app->getUser()->logout(); 
                Kiss::$app->session->addNotification('Failed to validate the Discord authentication.', 'danger');
            }
        }
    
        if (!$this->authorize($endpoint))
            throw new HttpException(self::LOGIN_ERROR_CODE, 'You need to be logged in to do that.');

        // Unless we are the main controller, we have to be whitelisted
        //if (!($this instanceof MainController)) {            
        //if (Kiss::$app->loggedIn()) {
        //    if (!GALL::$app->getUser()->whitelist)
        //    {                    
        //        Kiss::$app->getUser()->logout(); 
        //        Kiss::$app->session->addNotification('Your account is forbidden from accessing content.', 'danger');
        //        return Kiss::$app->respond(Response::redirect('/'));
        //    }
        //}
        //}

        $response = parent::action($endpoint, ...$args);
        return $response;
    }

    public function render($action, $options = []) {
        //$this->registerJsVariable("mixy", "new mixlib.Mixy(" . json_encode($mixyDefaults) . ")", Controller::POS_START, 'const', false);
        return parent::render($action, $options);
    }
}