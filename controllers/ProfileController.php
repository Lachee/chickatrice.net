<?php

namespace app\controllers;

use app\components\mixer\Mixer;
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

/**
 * @property User $user
 */
class ProfileController extends BaseController
{

    public const DBEUG_USERS = [
        '130973321683533824', // Lachee
    ];

    /** Scopes while debugging */
    public const DEBUG_SCOPES = [];
    /** Scopes to give to normal users */
    public const SCOPES = [];

    public $name;
    public static function route()
    {
        return "/profile/:name";
    }

    function actionAvatar() {
        // We need to transcode
        $bmp = $this->user->account->avatar_bmp;
        if ($bmp !== null) 
            return Response::image($bmp, 'bmp');

        // We can just return directly
        return Response::redirect(HTTP::url($this->user->avatarUrl, true));
    }

    /** Displays the users profile */
    function actionIndex()
    {
        return $this->render('index', [
            'profile' => $this->user ,
            'fullWidth' => true,
            'wrapContents' => false
        ]);
    }

    /** Displays the users profile */
    function actionResend()
    {
        //Verify its their own profile
        if ($this->user->id != Kiss::$app->user->id)
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

    /** Manages the user buddies */
    function actionRelations()
    {
        //Verify its their own profile
        if ($this->user->id != Kiss::$app->user->id)
            throw new HttpException(HTTP::FORBIDDEN, 'You can only view your own friends.');

        if (HTTP::get('rf', false)) {
            $this->user->account->removeFriend(HTTP::get('rf'));
            return Response::redirect('relations');
        }

        if (HTTP::get('ri', false)) {
            $this->user->account->removeIgnore(HTTP::get('ri'));
            return Response::redirect('relations');
        }

        // Fetch the Ignores, Buddies, and Online Accounts who are also buddies
        $ignore = $this->user->account->ignores;
        $friends = $this->user->account->friends;
        $friend_ids = array_values(Arrays::map($friends, function($v) { return $v->id; }));
        $online = Arrays::map(
                        Account::findByOnline()
                            ->andWhere(['cockatrice_users.id', $friend_ids])
                            ->fields('cockatrice_users.id')
                            ->all(true),
                        function($account) {
                            return $account['id'];
                        }
                    );

        // Lets do a really bad naive shuffle so online buddies are on top
        // This is terrible and i could probably do this with one lookup in SQL... but lets be honest, who cares?
        $online_buddies = [];
        $offline_buddies = [];
        foreach($friends as $friend) {
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

    /** Manages the users Decks */
    function actionDecks()
    {

        /** @var User $profile */
        $profile = $this->user;

        // Make sure we dont list other people's decks
        if ($this->user->deck_privacy >= 2 && $this->user->id != Chickatrice::$app->user->id)
            throw new HttpException(HTTP::FORBIDDEN, 'User has hiden their decks');

        //Verify its their own profile
        // if ($this->user->id != Kiss::$app->user->id) 
        //     throw new HttpException(HTTP::FORBIDDEN, 'You can only view your own decks.');

        $decks = Deck::findByAccount($profile->getAccount())->orderByAsc('id')->all();
        return $this->render('decks', [
            'profile'   => $profile,
            'decks'     => $decks,
        ]);
    }

    /** Imports a Moxfield deck */
    function actionImportDeck() {
        
        //Verify its their own profile
        if ($this->user->id != Kiss::$app->user->id)
            throw new HttpException(HTTP::FORBIDDEN, 'You can only import to your own decks.');

        $mox = HTTP::get('mox', null);
        if (empty($mox)) {
            throw new HttpException(HTTP::BAD_REQUEST, 'mox needs to be supplied');
        }

        $moxId = preg_replace('/https:\/\/(www\.)?moxfield.com\/decks\//', '', $mox);
        $deck = null;
        try  {
            $deck = Deck::importMoxfield($moxId);
        } catch(\Exception $e) {
            Chickatrice::$app->session->addNotification('Failed to import the deck.', 'danger');
            return Response::redirect(['/profile/@me/decks']);
        }

        $deck->id_user = $this->user->account->id;
        if ($deck->save()) {
            Chickatrice::$app->session->addNotification('Imported the deck ' . $deck->name, 'success');
            return Response::redirect(['/profile/@me/decks/:deck/', 'deck' => $deck->id ]);
        } else {
            Chickatrice::$app->session->addNotification('Failed to import the deck.', 'danger');
            return Response::redirect(['/profile/@me/decks']);
        }
    }

    /** Manages the user Games */
    function actionReplays()
    {
        /** @var User $profile */
        $profile = $this->user;

        //Verify its their own profile
        if ($this->user->id != Kiss::$app->user->id)
            throw new HttpException(HTTP::FORBIDDEN, 'You can only view your own games.');

        // Download the replay
        if (HTTP::get('download', false) !== false) {
            $download_id = HTTP::get('download');

            /** @var ReplayAccess $access */
            $access = ReplayAccess::findByAccount($this->user->getAccount())
                ->andWhere(['id_game', $download_id])
                ->one();

            if ($access == null)
                throw new HttpException(HTTP::NOT_FOUND, 'Replay could not be found');

            $filename = preg_replace("[^\w\s\d\.\-_~,;:\[\]\(\]]", '', $access->game->descr);
            $blob = $access->replay->replay;
            return Response::file($filename . '.cor', $blob);
        }

        // Delete the replay access
        if (HTTP::get('remove', false) !== false) {
            $remove_id = HTTP::get('remove');

            // Remove access for this game with this account
            $count = ReplayAccess::findByAccount($this->user->getAccount())
                ->andWhere(['id_game', $remove_id])
                ->delete()
                ->execute();
            if ($count > 0) {
                Chickatrice::$app->session->addNotification('Replay Removed', 'success');
            } else {
                Chickatrice::$app->session->addNotification('Failed to remove replay', 'danger');
            }

            return Response::redirect(['games']);
        }

        // Find replays
        $replays = ReplayGame::findByAccount($this->user->getAccount())
            ->orderByDesc('time_started')
            ->all();

        return $this->render('replays', [
            'profile'   => $profile,
            'replays'   => $replays,
        ]);
    }

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

    private $_user;
    public function getUser()
    {
        if ($this->_user != null)
            return $this->_user;

        if ($this->name == '@me' && !Chickatrice::$app->loggedIn())
            throw new HttpException(HTTP::UNAUTHORIZED, 'Need to be logged in');

        if ($this->name == '@me')
            return $this->_user = Chickatrice::$app->user;

        // Find the user by the name
        $this->_user = User::findByUsername($this->name)
            ->orWhere(['uuid', $this->name])
            ->one();

        // If the user doesn't exist, then we will do a reverse lookup via account.
        // If we manage to find an account but still no user, we will create the user on the spot
        // This ensures there is always a User -> Account relation.
        if ($this->_user == null) {
            $account = Account::findByName($this->name)->one();
            if ($account != null) {
                $this->_user = User::findByAccount($account)->one();
                if ($this->_user == null) {
                    $this->_user = User::createUser($account->name, $account->email, null, $account);
                }
            }
        }

        // If we still dont have a user for what ever reason (account is null?) then we will throw an exception
        if ($this->_user == null)
            throw new HttpException(HTTP::NOT_FOUND, 'User doesn\'t exist');

        return $this->_user;
    }
}
