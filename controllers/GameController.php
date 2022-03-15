<?php namespace app\controllers;

use app\components\mixer\Mixer;
use app\helpers\Country;
use app\models\cockatrice\Deck;
use app\models\cockatrice\Replay;
use app\models\cockatrice\ReplayGame;
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
 * @property ReplayGame $game
 */
class GameController extends BaseController {

    public $profile_name;
    public $game_id;

    public static function route() { return "/profile/:profile_name/replays/:game_id"; }

    function actionIndex() {
        return Response::redirect(['/profile/@me/replays']);
    }    

    function actionAnalytics() {
        if ($this->profile->id != Chickatrice::$app->user->id)
            throw new HttpException(HTTP::FORBIDDEN, 'Cannot view other people\'s replays.');
        
        return $this->render('analytics', [            
            'profile'       => $this->profile,
            'replay'          => $this->replay,
            
            'fullWidth' => true,
            'wrapContents' => false,
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

    private $_replay;
    public function getDeck() {
        if ($this->_replay != null) 
            return $this->_replay;        

        $this->_replay = ReplayGame::findByAccount($this->profile->getAccount()->id )
                                ->andWhere(['id', $this->game_id])
                                ->one();

        if ($this->_replay != null) 
            return $this->_replay;        

        //This is bunk, we found nudda
        throw new HttpException(HTTP::NOT_FOUND, 'Replay doesn\'t exist');
    }
}