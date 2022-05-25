<?php

namespace app\controllers;

use app\components\mixer\Mixer;
use app\controllers\traits\ProfileTrait;
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
use kiss\helpers\Strings;
use kiss\Kiss;
use Ramsey\Uuid\Uuid;

/**
 * @property ReplayGame $replay
 */
class GameController extends BaseController
{

    use ProfileTrait;

    public $replayId;
    private $_replay;

    public static function route()
    {
        return "/profile/:profile/replays/:replayId";
    }

    function actionIndex()
    {
        return Response::redirect(['/profile/@me/replays']);
    }

    /** Downloads a specific replay */
    function actionDownload()
    {
        //Verify its their own profile
        if (
            !$this->account->isModerator() &&
            $this->user->id != Kiss::$app->user->id
        )
            throw new HttpException(HTTP::FORBIDDEN, 'You can only view your own games.');

        $filename = Strings::safe($this->replay->description) . '.cor';
        $filedata = $this->replay->replayData;
        return Response::file($filename, $filedata);
    }

    /** Deletes a specific replay */
    function actionDelete()
    {
        //Verify its their own profile
        if (
            !$this->account->isAdmin() &&
            $this->user->id != Kiss::$app->user->id
        )
            throw new HttpException(HTTP::FORBIDDEN, 'You can only view your own games.');

        // Delete the replay
        if ($this->replay->delete()) {
            Chickatrice::$app->session->addNotification('Replay Removed', 'success');
        } else {
            Chickatrice::$app->session->addNotification('Failed to remove replay', 'danger');
        }

        // Redirect back to our list of replays
        return Response::redirect(['/profile/:profile/replays', 'profile' => $this->profile]);
    }

    /** Analytics */
    function actionAnalytics()
    {
        if ($this->user->id != Chickatrice::$app->user->id)
            throw new HttpException(HTTP::FORBIDDEN, 'Cannot view other people\'s replays.');

        return $this->render('analytics', [
            'profile'       => $this->user,
            'replay'          => $this->replay,

            'fullWidth' => true,
            'wrapContents' => false,
        ]);
    }

    /** @return ReplayGame The replay that matches the ID */
    public function getReplay()
    {
        if ($this->_replay != null)
            return $this->_replay;

        $this->_replay = ReplayGame::findByAccount($this->account)
            ->andWhere(['id', $this->replayId])
            ->one();

        if ($this->_replay != null)
            return $this->_replay;

        //This is bunk, we found nudda
        throw new HttpException(HTTP::NOT_FOUND, 'Replay doesn\'t exist');
    }
}
