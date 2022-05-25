<?php namespace app\controllers\traits;

use app\models\cockatrice\Account;
use app\models\User;
use Chickatrice;
use kiss\exception\HttpException;
use kiss\helpers\HTTP;

/**
 * @property User $user The user that we are looking up
 * @property Account $account The account that belongs to $user. Alias of `$user->account;`
 */
trait ProfileTrait {
    
    /** @var string Name of the profile */
    public $profile;
    private $_user;

    /** @return User gets the user */
    public function getUser()
    {
        if ($this->_user != null)
            return $this->_user;

        if ($this->profile == '@me' && !Chickatrice::$app->loggedIn())
            throw new HttpException(HTTP::UNAUTHORIZED, 'Need to be logged in');

        if ($this->profile == '@me')
            return $this->_user = Chickatrice::$app->user;

        // Find the user by the name
        $this->_user = User::findByUsername($this->profile)
            ->orWhere(['uuid', $this->profile])
            ->one();

        // If the user doesn't exist, then we will do a reverse lookup via account.
        // If we manage to find an account but still no user, we will create the user on the spot
        // This ensures there is always a User -> Account relation.
        if ($this->_user == null) {
            $account = Account::findByName($this->profile)->one();
            if ($account != null) {
                $this->_user = User::findByAccount($account)->one();
                if ($this->_user == null) {
                    $this->_user = User::createUser($account->profile, $account->email, null, $account);
                }
            }
        }

        // If we still dont have a user for what ever reason (account is null?) then we will throw an exception
        if ($this->_user == null)
            throw new HttpException(HTTP::NOT_FOUND, 'User doesn\'t exist');

        return $this->_user;
    }

    /** @return Account gets the user account */
    public function getAccount()
    {
        return $this->getUser()->getAccount();
    }
}