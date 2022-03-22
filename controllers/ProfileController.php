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
 * @property Account $account
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

    /** @var string name of the profile */
    public $name;
    /** @var User $user the user account being viewed */
    public $user;
    /** @var Account $account the account being viewed */
    public $account;

    public static function route() { return "/profile/:name"; }
    function action($endpoint, ...$args)
    {
        $this->loadRecords();
        parent::action($endpoint, ...$args);
    }

    /** Gets the avatar for the given account */
    function actionAvatar() {
        // We need to transcode
        $bmp = $this->account->avatar_bmp;
        if ($bmp !== null) 
            return Response::image($bmp, 'bmp');

        if ($this->user === null)
            throw new HttpException(HTTP::NOT_FOUND, 'Account does not have an avatar');

        // We can just return directly
        return Response::redirect(HTTP::url($this->user->avatarUrl, true));
    }

    /** Displays the users profile */
    function actionIndex() {
        return $this->render('index', [
            'account'       => $this->account,
            'user'          => $this->user,
            'fullWidth'     => true,
            'wrapContents'  => false
        ]);
    }

    /** Displays the users profile */
    function actionResend() {
        //Verify its their own profile
        if ($this->user == null || $this->user->id != Kiss::$app->user->id)
            throw new HttpException(HTTP::FORBIDDEN, 'Cannot resend someone else\'s activation.');
    
        $activation = Chickatrice::$app->redis()->get($this->user->id . ':activation');
        if ($activation) {
            Chickatrice::$app->session->addNotification('Activation code was already sent! Please wait 15 minutes.', 'danger');
        } else {
            RegisterForm::sendAccountActivation($this->account);
            Chickatrice::$app->redis()->set($this->user->id . ':activation', 'true');
            Chickatrice::$app->redis()->expire($this->user->id . ':activation', 15 * 60);
        }
        return Response::redirect(['settings']);
    }

    /** Manages the user buddies */
    function actionRelations() {
        //Verify its their own profile
        if ($this->user == null || $this->user->id != Kiss::$app->user->id)
            throw new HttpException(HTTP::FORBIDDEN, 'You can only view your own friends.');

        if (HTTP::get('rf', false)) {
            $this->profile->account->removeFriend(HTTP::get('rf'));
            return Response::redirect('relations');
        }

        if (HTTP::get('ri', false)) {
            $this->profile->account->removeIgnore(HTTP::get('ri'));
            return Response::redirect('relations');
        }

        // Fetch the Ignores, Buddies, and Online Accounts who are also buddies
        $ignore = $this->profile->account->ignores;
        $friends = $this->profile->account->friends;
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
            'profile'           => $this->profile,
            'buddies'           => $online_buddies + $offline_buddies,
            'online_ids'        => $online,
            'ignores'           => $ignore,
        ]);
    }

    /** Manages the users Decks */
    function actionDecks() {

        /** @var User $profile */
        $profile = $this->profile;

        // Make sure we dont list other people's decks
        if ($this->profile->deck_privacy >= 2 && $this->profile->id != Chickatrice::$app->user->id)
            throw new HttpException(HTTP::FORBIDDEN, 'User has hiden their decks');

        //Verify its their own profile
        // if ($this->profile->id != Kiss::$app->user->id) 
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
        if ($this->profile->id != Kiss::$app->user->id)
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

        $deck->id_user = $this->profile->account->id;
        if ($deck->save()) {
            Chickatrice::$app->session->addNotification('Imported the deck ' . $deck->name, 'success');
            return Response::redirect(['/profile/@me/decks/:deck/', 'deck' => $deck->id ]);
        } else {
            Chickatrice::$app->session->addNotification('Failed to import the deck.', 'danger');
            return Response::redirect(['/profile/@me/decks']);
        }
    }

    /** Manages the user Games */
    function actionReplays() {
        /** @var User $profile */
        $profile = $this->profile;

        //Verify its their own profile
        if ($this->profile->id != Kiss::$app->user->id)
            throw new HttpException(HTTP::FORBIDDEN, 'You can only view your own games.');

        // Download the replay
        if (HTTP::get('download', false) !== false) {
            $download_id = HTTP::get('download');

            /** @var ReplayAccess $access */
            $access = ReplayAccess::findByAccount($this->profile->getAccount())
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
            $count = ReplayAccess::findByAccount($this->profile->getAccount())
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
        $replays = ReplayGame::findByAccount($this->profile->getAccount())
            ->orderByDesc('time_started')
            ->all();

        return $this->render('replays', [
            'profile'   => $profile,
            'replays'   => $replays,
        ]);
    }

    /** Manages the user account settings */
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
        $form = new UserSettingForm(['user' => $this->profile]);
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
        if (KISS_DEBUG || in_array($this->profile->snowflake, self::DBEUG_USERS))
            $scopes = self::DEBUG_SCOPES;

        //Render the page
        return $this->render('settings', [
            'profile'       => $this->profile,
            'discord'       => $this->profile->getDiscordUser(),
            'discordUrl'    => Chickatrice::$app->discord->getAuthUrl(false),
            'model'         => $form,
            'key'           => $this->api_key = $this->profile->apiToken(['scopes' => $scopes]),
            'fullwidth'     => false,
        ]);
    }

    function actionDelete() {
        //Verified they are logged in
        if (!Chickatrice::$app->loggedIn())
            throw new HttpException(HTTP::UNAUTHORIZED, 'Need to be logged in to edit your settings.');

        //Verify its their own profile
        /** @var User $profile */
        if ($this->profile->id != Kiss::$app->user->id && $this->profile->account->admin != Account::ADMIN_LEVEL_OWNER)
            throw new HttpException(HTTP::FORBIDDEN, 'You cannot delete someone\'s else account! You cheeky bastard.');

        // Mark their account inactive and ready to delete in the next cron-job
        $account = $this->profile->account;
        $this->profile->account->active = false;
        $this->profile->account->token = null;
        $this->profile->account->setPassword(Strings::token(32));
        if (!$this->profile->account->save())
            throw new HttpException(HTTP::INTERNAL_SERVER_ERROR, 'Failed to mark account for deletion');

        // Delete their user account and logout
        $this->profile->logout();
        $this->profile->delete();

        // Return home
        return Response::redirect(Kiss::$app->baseURL());
    }

    /** Reads the properties and fetches the records */
    protected function loadRecords() {
        if ($this->name == '@me' && !Chickatrice::$app->loggedIn())
            throw new HttpException(HTTP::UNAUTHORIZED, 'Need to be logged in');

        // We are just ourselves
        if ($this->name == '@me') {
            $this->user = Chickatrice::$app->user;
            $this->account = $this->user->account;
            return;
        }

        // Find the user with the associated name/uuid and pull its account.
        // If we couldn't find the user, we will just pull the account directly
        $this->user = User::findByUsername($this->name)
                        ->orWhere(['uuid', $this->name])
                        ->one();
        
        $this->account = $this->user != null ? 
                            $this->user->account :
                            Account::findByName($this->name)
                                ->orWhere(['id', $this->name])
                                ->one();
        
        if ($this->account == null)                                                     
            throw new HttpException(HTTP::NOT_FOUND, 'Account does not exist');
            
            
        // Copy back user if its null but account isnt
        if ($this->user === null && $this->account !== null)
            $this->user = User::findByAccount($this->account)->one();

        // If the user doesn't match the cockatrice account, throw an error.
        // This should basically never happen, but failsafe.
        if ($this->user != null && $this->user->cockatrice_id != $this->account->id)
            throw new HttpException(HTTP::CONFLICT, 'Account does not match the user\'s account. Contact Lachee');
    }
}
