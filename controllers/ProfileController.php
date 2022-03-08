<?php namespace app\controllers;

use app\components\mixer\Mixer;
use app\helpers\Country;
use app\models\cockatrice\Deck;
use app\models\forms\ProfileSettingForm;
use app\models\forms\UserSettingForm;
use app\models\Gallery;
use app\models\Identifier;
use app\models\Sparkle;
use kiss\exception\HttpException;
use kiss\helpers\HTTP;
use kiss\helpers\Response;
use kiss\models\BaseObject;
use app\models\User;
use app\widget\Notification;
use Chickatrice;
use kiss\helpers\HTML;
use kiss\Kiss;
use Ramsey\Uuid\Uuid;

/**
 * @property User $profile
 */
class ProfileController extends BaseController {

    public const DBEUG_USERS = [
        '130973321683533824', // Lachee
        '171764626755813376', // Swarely
    ];

    /** SCopes while debugging */
    public const DEBUG_SCOPES = [
        'emote.update', 'emote.publish', 'emote.remove',
        'guild', 'guild.publish', 'guild.remove', 'guild.update', 
        'gallery', 'gallery.favourite', 'gallery.pin', 'gallery.publish', 'gallery.search', 'gallery.update', 'gallery.reaction',
        'bot.impersonate'
    ];

    /** Scopes to give to normal users */
    public const SCOPES = [
        'gallery', 'gallery.favourite', 'gallery.pin', 'gallery.publish', 'gallery.search', 'gallery.update', 'gallery.reaction',
    ];

    public $profile_name;
    public static function route() { return "/profile/:profile_name"; }

    function action($endpoint, ...$args) {
        if ($this->profile_name == '@me' && !Chickatrice::$app->loggedIn())
            throw new HttpException(HTTP::UNAUTHORIZED, 'Need to be logged in to see your own profile.');

        parent::action($endpoint, ...$args);
    }

    function actionIndex() {
        /** @var User $profile */
        $profile = $this->profile;
        return $this->render('index', [
            'profile'       => $profile,
        ]);
    }    
    
    function actionDecks() {
        /** @var User $profile */
        $profile = $this->profile;

        $decks = Deck::findByAccount($profile->getAccount())->orderByAsc('id')->all();
        return $this->render('decks', [
            'profile'   => $profile,
            'decks'     => $decks,
        ]);
    }

    function actionSettings() {
        //Verified they are logged in
        if (!Chickatrice::$app->loggedIn())
            throw new HttpException(HTTP::UNAUTHORIZED, 'Need to be logged in to edit your settings.');

        //Verify its their own profile
        /** @var User $profile */
        if ($this->profile->id != Kiss::$app->user->id) 
            throw new HttpException(HTTP::FORBIDDEN, 'Can only edit your own settings');
        
        //Regenerate the API key if we are told to
        if (HTTP::get('regen', false, FILTER_VALIDATE_BOOLEAN)) {
            if ($this->profile->regenerateApiKey()) {
                Kiss::$app->session->addNotification('Regenerated your API key', 'success');
            } else {
                Kiss::$app->session->addNotification('Failed to regenerate your API key', 'danger');
            }
            return Response::refresh();
        }
    
        if (HTTP::get('sync', false, FILTER_VALIDATE_BOOLEAN)) {
            if ($this->profile->synchroniseDiscordAvatar()) {
                Kiss::$app->session->addNotification('Avatar Synchronised', 'success');
            } else {
                Kiss::$app->session->addNotification('Failed to synchronise your avatar', 'danger');
            }
            sleep(1);
            return Response::refresh();
        }

        // Show the profile
        $form = new UserSettingForm([ 'user' => $this->profile ]);
        if (HTTP::hasPost()) {
            if ($form->load(HTTP::post()) && $form->save()) {
                Kiss::$app->session->addNotification('Updated profile settings', 'success');
                return Response::redirect([ '/profile/@me/settings' ]);
            } else {                
                Kiss::$app->session->addNotification('Failed to load: ' . $form->errorSummary(), 'danger');
            }
        }

        //Setup the scopes
        $scopes = self::SCOPES;
        if (KISS_DEBUG || in_array($this->profile->snowflake, self:: DBEUG_USERS)) 
            $scopes = self::DEBUG_SCOPES;

        //Render the page
        return $this->render('settings', [
            'profile'       => $this->profile,
            'discord'       => $this->profile->getDiscordUser(),
            'model'         => $form,
            'key'           => $this->api_key = $this->profile->apiToken([ 'scopes' => $scopes ]),
            'fullwidth'     => false,
        ]);
    }

    private $_profile;
    public function getProfile() {

        if ($this->profile_name == '@me' && !Chickatrice::$app->loggedIn()) 
            throw new HttpException(HTTP::UNAUTHORIZED, 'Need to be logged in');
        
        if ($this->_profile != null) 
            return $this->_profile;        

        if ($this->profile_name == '@me')
            return $this->_profile = Chickatrice::$app->user;

        $this->_profile = User::find()->where(['uuid', $this->profile_name])->ttl(false)->one();
        if ($this->_profile != null) return $this->_profile;        
     

        //This is bunk, we found nudda
        throw new HttpException(HTTP::NOT_FOUND, 'Profile doesn\'t exist');
    }
}