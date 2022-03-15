<?php namespace app\controllers;

use app\components\mixer\Mixer;
use app\models\Ban;
use app\models\cockatrice\Account;
use app\models\cockatrice\Replay;
use app\models\forms\LoginForm;
use app\models\forms\RecoverForm;
use app\models\forms\RegisterForm;
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
use kiss\helpers\Strings;
use kiss\Kiss;
use Mailgun\Mailgun;
use Ramsey\Uuid\Uuid;

class MainController extends BaseController {
    public static function route() { return "/main"; }

    /** @var string homepage for a profile */
    private const PROFILE_HOME = '/profile/@me/settings';

    /** Checks the authorization of the user
     * @return bool true if they are allowed here. False if they should be redirected to login first.
     * You can also throw exceptions here if requried to tell the user off.
     */
    public function authorize($action) { return true; }

    function actionIndex() {
        return $this->render('index', [
            'fullWidth' => true,
            'wrapContents' => false,
        ]);
    }

    /** Displays statistics */
    public function actionStats() {
        $this->registerDependency('https://www.gstatic.com/charts/loader.js');
        return $this->render('stats', [
           
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

    /** Activates */
    function actionActivate() {

        $token = HTTP::get('token', '');
        if (empty($token)) 
            throw new HttpException(HTTP::BAD_REQUEST, 'Invalid token provided');

        $account = Account::findByToken($token)->one();
        if ($account == null)
            throw new HttpException(HTTP::BAD_REQUEST, 'Invalid token provided');

        if (Chickatrice::$app->loggedIn() && Chickatrice::$app->user->account->id != $account->id)
            throw new HttpException(HTTP::BAD_REQUEST, 'Invalid token provided');

        $account->active = true;
        $account->token = '';
        if ($account->save(false, ['active', 'token'])) {
            Chickatrice::$app->session->addNotification('Account activated succesfully', 'success');
        } else {
            Chickatrice::$app->session->addNotification('Failed to activate account. Please contact Lachee.', 'danger');
        }
        return Response::redirect(['/login']);
    }

    function actionRecover() {
        
        if (Chickatrice::$app->loggedIn())
            return Response::redirect(['/profile/@me/settings']);

        $token = HTTP::post('token', HTTP::get('token', ''));
        if (empty($token)) 
            throw new HttpException(HTTP::BAD_REQUEST, 'Invalid token provided');
        
        $form = new RecoverForm(['token' => $token]);
        if (HTTP::hasPost()) {
            if ($form->load(HTTP::post()) && $form->save()) {
                Chickatrice::$app->session->addNotification('Account recovered successfully', 'success');
                return Response::redirect(['/login']);
            } else {                
                Chickatrice::$app->session->addNotification('Failed to recover. ' . $form->errorSummary(), 'danger');
            }
        }
        
        return $this->render('recover', ['form' => $form ]);
    }

    /** Logs In */
    function actionLogin() {
        if (Kiss::$app->loggedIn())
            return Response::redirect(self::PROFILE_HOME);

        $loginForm      = new LoginForm(['formName' => 'login']);
        $registerForm   = new RegisterForm(['formName' => 'register']);
        if (HTTP::hasPost()) {
            if ($loginForm->load(HTTP::post())) {
                if ($loginForm->save()) {
                    if ($loginForm->btn_recover) {
                        Kiss::$app->session->addNotification('Recovery emailed', 'success');
                        return Response::redirect(['/login']);
                    } else {
                        return Response::redirect(self::PROFILE_HOME);
                    }
                } else {
                    Kiss::$app->session->addNotification('Failed to login: ' . $loginForm->errorSummary(), 'danger');
                }
            } else if ($loginForm->hasErrors()) {
                Kiss::$app->session->addNotification( $loginForm->errorSummary(), 'danger');
            }

            if ($registerForm->load(HTTP::post())) {
                if ($registerForm->save()) {
                    // Proceed with login logic
                } else {
                    Kiss::$app->session->addNotification('Failed to register: ' . $registerForm->errorSummary(), 'danger');
                }
            } else if ($registerForm->hasErrors()) {
                Kiss::$app->session->addNotification( $registerForm->errorSummary(), 'danger');
            }
        }

        return $this->render('login', [
            'loginForm'  => $loginForm,
            'registerForm' => $registerForm,
            'discordUrl' => Chickatrice::$app->discord->getAuthUrl()
        ]);
        // Kiss::$app->session->set('LOGIN_REFERRAL', HTTP::referral());
        // return Chickatrice::$app->discord->redirect();
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
            if ($tokens === false) {
                return Chickatrice::$app->discord->redirect();
            }

            //Get the discord user
            $duser  = Chickatrice::$app->discord->identify($tokens);
            if (!$duser->verified) {
                Chickatrice::$app->session->addNotification('Your Discord Account must be Verified!', 'danger');
                $referal = Kiss::$app->session->get('LOGIN_REFERRAL', HTTP::referral());
                return Response::redirect($referal ?? [ '/login' ]);
            }

            // Find matching user
            /** @var User $user */
            $user = User::findBySnowflake($duser->id)->one();
            
            // Check if we are already loggedin or not. 
            // If we are logged in, we are linking. If not, creating / logging in
            if (Chickatrice::$app->loggedIn()) {

                // Throw an error because user already exists
                if ($user != null || Chickatrice::$app->user->getSnowflake() > 0) {
                    throw new \Exception('Discord Account already linked.');
                }

                // Assign the new user
                $user = Chickatrice::$app->user;
                $user->setSnowflake($duser->id);
                if (!$user->save(false, ['snowflake'])) {
                    throw new \Exception('Failed to link discord snowflake id');
                }

                Chickatrice::$app->session->addNotification('Linked and Logged In with Discord', 'success');
            } else {
                
                // Create a new user if it doesnt exist
                if ($user == null) {
                    $user = User::createUser($duser->username, $duser->email, $duser->id);
                    Chickatrice::$app->session->addNotification('Your account has been created');
                }
                
                //Add the user to each guild
                // $guilds = Chickatrice::$app->discord->getGuilds($tokens);
                // $guilds = Arrays::map($guilds, function($g) { return $g['id']; });
                // $guilds = Guild::find()->where(['snowflake', $guilds ])->all();
                // if ($guilds == null || count($guilds) == 0) { 
                //     Chickatrice::$app->session->addNotification('Cannot possibly logged in because you do not share a server', 'danger');
                //     throw new Exception('Not in any guilds');
                // }

                // foreach($guilds as $guild) {
                //     $user->addGuild($guild);
                // }

            }

            // Update user deets
            if ($user != null) {
                
                //Store the tokens
                Chickatrice::$app->discord->getStorage($user->uuid)->setTokens($tokens);

                // Update the avatar
                $user->setDiscordUserCache($duser);
                $user->synchroniseDiscordAvatar();

                // Update their account to be active
                $user->account->active = true;
                $user->account->save(false, ['active']);

                //Actually login. We do this either way because we want to upgrade login to discord account
                if (!$user->login())
                    Kiss::$app->session->addNotification('Failed to login for some reason', 'danger');
                
                // Flush the user once we have logged in
                Chickatrice::$app->user = User::findByKey($user->getKey())->flush()->one();
            }
        } 
        catch(\Exception $e) 
        {
            Chickatrice::$app->session->addNotification('Woops, something went wrong while trying to perform that action! ' . ($e->getMessage()), 'danger');
        }         
        
        $referal = Kiss::$app->session->get('LOGIN_REFERRAL', HTTP::referral());
        return Response::redirect($referal ?? self::PROFILE_HOME);
    }
}