<?php

namespace app\controllers;

use app\components\mixer\Mixer;
use app\controllers\traits\ProfileTrait;
use app\helpers\Country;
use app\models\cockatrice\Account;
use app\models\cockatrice\Deck;
use app\models\cockatrice\Game;
use app\models\cockatrice\Replay;
use app\models\cockatrice\ReplayAccess;
use app\models\cockatrice\ReplayGame;
use app\models\forms\ProfileSettingForm;
use app\models\forms\RegisterForm;
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
use kiss\helpers\Arrays;
use kiss\helpers\HTML;
use kiss\helpers\Strings;
use kiss\Kiss;
use Ramsey\Uuid\Uuid;

class ProfileController extends BaseController
{
    use ProfileTrait;

    public const DBEUG_USERS = ['130973321683533824',];
    /** Scopes while debugging */
    public const DEBUG_SCOPES = [];
    /** Scopes to give to normal users */
    public const SCOPES = [];

    public static function route()  { return "/profile/:profile"; }

    /** Displays the users profile */
    function actionIndex()
    {
        return $this->render('index', [
            'profile' => $this->user,
            'fullWidth' => true,
            'wrapContents' => false
        ]);
    }

    #region Deck Functions
    /** Manages the users Decks */
    function actionDecks()
    {
        // Make sure we dont list other people's decks (unless we are judges)
        if (
            !$this->account->isJudge() &&
            $this->user->deck_privacy >= User::DECK_PRIVACY_PRIVATE && $this->user->id != Chickatrice::$app->user->id
        )
            throw new HttpException(HTTP::FORBIDDEN, 'User has hiden their decks');

        $decks = Deck::findByAccount($this->account)->orderByAsc('id')->all();
        return $this->render('decks', [
            'profile'   => $this->user,
            'decks'     => $decks,
        ]);
    }
    /** Imports a Moxfield deck */
    function actionImportDeck()
    {
        //Verify its their own profile
        if ($this->user->id != Kiss::$app->user->id)
            throw new HttpException(HTTP::FORBIDDEN, 'You can only import to your own decks.');

        $mox = HTTP::get('mox', null);
        if (empty($mox)) {
            throw new HttpException(HTTP::BAD_REQUEST, 'mox needs to be supplied');
        }

        $moxId = preg_replace('/https:\/\/(www\.)?moxfield.com\/decks\//', '', $mox);
        $deck = null;
        try {
            $deck = Deck::importMoxfield($moxId);
        } catch (\Exception $e) {
            Chickatrice::$app->session->addNotification('Failed to import the deck.', 'danger');
            return Response::redirect(['/profile/@me/decks']);
        }

        $deck->id_user = $this->user->account->id;
        if ($deck->save()) {
            Chickatrice::$app->session->addNotification('Imported the deck ' . $deck->name, 'success');
            return Response::redirect(['/profile/@me/decks/:deck/', 'deck' => $deck->id]);
        } else {
            Chickatrice::$app->session->addNotification('Failed to import the deck.', 'danger');
            return Response::redirect(['/profile/@me/decks']);
        }
    }
    #endregion

    #region Relation Functions
    /** Manages the user buddies */
    function actionRelations()
    {
        //Verify its their own profile
        if (
            !$this->account->isAdmin() &&
            $this->user->id != Kiss::$app->user->id
        )
            throw new HttpException(HTTP::FORBIDDEN, 'You can only view your own friends.');

        if (HTTP::get('rf', false)) {
            $this->account->removeFriend(HTTP::get('rf'));
            return Response::redirect('relations');
        }

        if (HTTP::get('ri', false)) {
            $this->account->removeIgnore(HTTP::get('ri'));
            return Response::redirect('relations');
        }

        // Fetch the Ignores, Buddies, and Online Accounts who are also buddies
        $ignore = $this->account->ignores;
        $friends = $this->account->friends;
        $friend_ids = array_values(Arrays::map($friends, function ($v) {
            return $v->id;
        }));
        $online = Arrays::map(
            Account::findByOnline()
                ->andWhere(['cockatrice_users.id', $friend_ids])
                ->fields('cockatrice_users.id')
                ->all(true),
            function ($account) {
                return $account['id'];
            }
        );

        // Lets do a really bad naive shuffle so online buddies are on top
        // This is terrible and i could probably do this with one lookup in SQL... but lets be honest, who cares?
        $online_buddies = [];
        $offline_buddies = [];
        foreach ($friends as $friend) {
            if (in_array($friend->id, $online)) {
                $online_buddies[] = $friend;
            } else {
                $offline_buddies[] = $friend;
            }
        }

        // Render it all out
        return $this->render('relations', [
            'profile'           => $this->user,
            'buddies'           => $online_buddies + $offline_buddies,
            'online_ids'        => $online,
            'ignores'           => $ignore,
        ]);
    }
    #endregion

    #region Replay Functions
    /** Manages the user Games */
    function actionReplays()
    {
        //Verify its their own profile
        if (
            !$this->account->isModerator() &&
            $this->user->id != Kiss::$app->user->id
        )
            throw new HttpException(HTTP::FORBIDDEN, 'You can only view your own games.');
        
        // Find replays
        $replays = ReplayGame::findByAccount($this->user->getAccount())
            ->orderByDesc('time_started')
            ->all();

        return $this->render('replays', [
            'profile'   => $this->user,
            'replays'   => $replays,
        ]);
    }
    #endregion

    #region Setting Functions
    /** Manages the user account settings */
    function actionSettings()
    {
        //Verified they are logged in
        if (!Chickatrice::$app->loggedIn())
            throw new HttpException(HTTP::UNAUTHORIZED, 'Need to be logged in to edit your settings.');

        //Verify its their own profile
        /** @var User $profile */
        if ($this->user->id != Kiss::$app->user->id)
            throw new HttpException(HTTP::FORBIDDEN, 'Can only edit your own settings');

        //Regenerate the API key if we are told to
        if (HTTP::get('regen', false, FILTER_VALIDATE_BOOLEAN)) {
            if ($this->user->regenerateApiKey()) {
                Kiss::$app->session->addNotification('Regenerated your API key', 'success');
            } else {
                Kiss::$app->session->addNotification('Failed to regenerate your API key', 'danger');
            }
            return Response::refresh();
        }

        if (HTTP::get('sync', false, FILTER_VALIDATE_BOOLEAN)) {
            if ($this->user->synchroniseDiscordAvatar()) {
                Kiss::$app->session->addNotification('Avatar Synchronised', 'success');
            } else {
                Kiss::$app->session->addNotification('Failed to synchronise your avatar', 'danger');
            }
            sleep(1);
            return Response::refresh();
        }

        // Show the profile
        $form = new UserSettingForm(['user' => $this->user]);
        if (HTTP::hasPost()) {
            if ($form->load(HTTP::post()) && $form->save()) {
                Kiss::$app->session->addNotification('Updated profile settings', 'success');
                return Response::redirect(['/profile/@me/settings'], HTTP::OK);
            } else {
                Kiss::$app->session->addNotification('Failed to load: ' . $form->errorSummary(), 'danger');
            }
        }

        //Setup the scopes
        $scopes = self::SCOPES;
        if (KISS_DEBUG || in_array($this->user->snowflake, self::DBEUG_USERS))
            $scopes = self::DEBUG_SCOPES;

        //Render the page
        return $this->render('settings', [
            'profile'       => $this->user,
            'discord'       => $this->user->getDiscordUser(),
            'discordUrl'    => Chickatrice::$app->discord->getAuthUrl(false),
            'model'         => $form,
            'key'           => $this->api_key = $this->user->apiToken(['scopes' => $scopes]),
            'fullwidth'     => false,
        ]);
    }

    function actionDelete()
    {
        //Verified they are logged in
        if (!Chickatrice::$app->loggedIn())
            throw new HttpException(HTTP::UNAUTHORIZED, 'Need to be logged in to edit your settings.');

        //Verify its their own profile
        if ($this->user->id != Kiss::$app->user->id && !$this->user->account->isAdmin)
            throw new HttpException(HTTP::FORBIDDEN, 'You cannot delete someone\'s else account! You cheeky bastard.');

        // Mark their account inactive and ready to delete in the next cron-job
        $account = $this->user->account;
        $this->user->account->active = false;
        $this->user->account->token = null;
        $this->user->account->setPassword(Strings::token(32));
        if (!$this->user->account->save())
            throw new HttpException(HTTP::INTERNAL_SERVER_ERROR, 'Failed to mark account for deletion');

        // Delete their user account and logout
        $this->user->logout();
        $this->user->delete();

        // Return home
        return Response::redirect(Kiss::$app->baseURL());
    }

    /** Displays the users profile */
    function actionResend()
    {
        //Verify its their own profile
        if (
            !$this->account->isAdmin() &&
            $this->user->id != Kiss::$app->user->id
        )
            throw new HttpException(HTTP::FORBIDDEN, 'Cannot resend someone else\'s activation.');

        $activation = Chickatrice::$app->redis()->get($this->user->id . ':activation');
        if ($activation) {
            Chickatrice::$app->session->addNotification('Activation code was already sent! Please wait 15 minutes.', 'danger');
        } else {
            RegisterForm::sendAccountActivation($this->user->account);
            Chickatrice::$app->redis()->set($this->user->id . ':activation', 'true');
            Chickatrice::$app->redis()->expire($this->user->id . ':activation', 15 * 60);
        }
        return Response::redirect(['settings']);
    }

    /** Gets the raw contents of the avatar */
    function actionAvatar()
    {
        // We need to transcode
        $bmp = $this->user->account->avatar_bmp;
        if ($bmp !== null)
            return Response::image($bmp, 'bmp');

        // We can just return directly
        return Response::redirect(HTTP::url($this->user->avatarUrl, true));
    }
    #endregion

    #region Getters
 
    #endregion
}
