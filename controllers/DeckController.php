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
 * @property Deck $deck
 */
class DeckController extends BaseController {

    public $profile_name;
    public $deck_id;

    public static function route() { return "/profile/:profile_name/decks/:deck_id"; }

    function actionIndex() {
        $this->deck->loadIdentifiers();
        return $this->render('index', [
            'profile'       => $this->profile,
            'deck'          => $this->deck,
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

        $this->_profile = User::findByUsername($this->profile_name)
                                    ->orWhere(['uuid', $this->profile_name])
                                    ->one();
        if ($this->_profile != null)
            return $this->_profile;        
     


        //This is bunk, we found nudda
        throw new HttpException(HTTP::NOT_FOUND, 'Profile doesn\'t exist');
    }

    private $_deck;
    public function getDeck() {
        if ($this->_deck != null) 
            return $this->_deck;        

        $this->_deck = Deck::find()
                                ->where(['id', $this->deck_id])
                                ->andWhere(['id_user', $this->profile->getAccount()->id ])
                                ->one();

        if ($this->_deck != null) 
            return $this->_deck;        

        //This is bunk, we found nudda
        throw new HttpException(HTTP::NOT_FOUND, 'Deck doesn\'t exist');
    }
}