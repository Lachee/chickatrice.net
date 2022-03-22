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
use kiss\helpers\Strings;
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
        if ($this->profile->deck_privacy >= 2 && $this->profile->id != Chickatrice::$app->user->id)
            throw new HttpException(HTTP::FORBIDDEN, 'User has hidden their decks');

        $this->deck->loadIdentifiers();
        return $this->render('index', [
            'profile'       => $this->profile,
            'deck'          => $this->deck,
        ]);
    }    

    function actionTest() {
        //$this->deck->downloadTags();
        $query = $this->deck->getTags();
        die($query->previewStatement());
    }

    function actionAnalytics() {
        if ($this->profile->deck_privacy >= 2 && $this->profile->id != Chickatrice::$app->user->id)
            throw new HttpException(HTTP::FORBIDDEN, 'User has hidden their decks');

        return $this->render('analytics', [            
            'profile'       => $this->profile,
            'deck'          => $this->deck,
            
            'fullWidth' => true,
            'wrapContents' => false,
        ]);
    }

    function actionRemove() {
        if ($this->profile->id != Chickatrice::$app->user->id)
            throw new HttpException(HTTP::FORBIDDEN, 'Cannot delete other people\'s decks! Cheeky!');
        
        $this->deck->delete();
        return Response::redirect(['/profile/:profile/decks', 'profile' => $this->profile_name ]);
    }
   
    function actionCopy() {
        if ($this->profile->deck_privacy >= 1 && $this->profile->id != Chickatrice::$app->user->id)
            throw new HttpException(HTTP::FORBIDDEN, 'User has copy-protected their decks');

        $deck = $this->deck;
        $deck->id = null;
        $deck->id_user = Chickatrice::$app->user->account->id;
        $deck->id_folder = 0;
        $deck->upload_time = date("Y-m-d H:i:s");
        $deck->markNewRecord();
        $deck->save();
        return Response::redirect(['/profile/@me/decks/:deck/', 'deck' => $deck->id ]);
    }

    function actionDownload() {       
        if ($this->profile->deck_privacy >= 1 && $this->profile->id != Chickatrice::$app->user->id)
            throw new HttpException(HTTP::FORBIDDEN, 'User has copy-protected their decks');

        $filename = Strings::safe($this->deck->name);
        return Response::file("$filename.cod", $this->deck->content);
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