<?php namespace app\models\forms;

use app\models\cockatrice\Account;
use app\models\User;
use Chickatrice;
use kiss\helpers\HTML;
use kiss\helpers\HTTP;
use kiss\helpers\Strings;
use kiss\models\forms\Form;
use kiss\schema\BooleanProperty;
use kiss\schema\StringProperty;

class LoginForm extends Form {

    public $username;
    public $password;

    public $btn_recover;
    public $btn_login;

    protected function init() {
        parent::init();
    }

    public static function getSchemaProperties($options = [])
    {
        $btnParser = function($value) { return $value === '' || filter_var($value, FILTER_VALIDATE_BOOL | FILTER_NULL_ON_FAILURE); };
        return [
            'username' => new StringProperty('', 'xXMagicPlayerXx', [ 'title' => 'Username' ]),
            'password' => new StringProperty('', '', [ 'title' => 'Password', 'options' => [ 'password' => true ], 'required' => false]),

            'btn_recover' => new BooleanProperty('', '', [ 'parser' => $btnParser, 'required' => false, 'options' => [ 'hidden' => true ], ]),
            'btn_login' => new BooleanProperty('', '', [ 'parser' => $btnParser, 'required' => false, 'options' => [ 'hidden' => true ], ]),
        ];
    }

    public function save($validate = false)
    {        
        //Failed to load
        if ($validate && !$this->validate()) {
            return false;
        }

        if ($this->btn_recover) {
            return $this->recover();
        } else if ($this->btn_login) {
            return $this->login();
        }
    }

    private function recover() {
        $err_msg = 'Incorrect username or password';

        // Get the accounts
        /** @var Account $account */
        $account = Account::findByName($this->username)->one();
        if ($account == null) {
            return true;
        }

        /** @var User $user */
        $user = User::findByAccount($account)->one();
        if ($user != null && $user->getSnowflake() !== 0) {
            $this->addError('Cannot recover Discord accounts');
            return false;
        }

        $account->token = Strings::token(16);
        if ($account->save(false, ['token'])) {
            $name = $account->name;
            $ip = HTTP::ip();
            $agent = HTTP::userAgent();
            $link = HTTP::url(['/recover', 'token' => $account->token], true);
            $message = <<<TXT
Hello $name,

    Your account requested a password recovery from the following address:
    
    $ip
    $agent
    
    To complete the recovery, please follow this link and create a new password:
    $link
    
    If you did not request this recovery, please ignore the previous link and change your password
    on your profile settings page.

Cheers,
    Lachee
TXT;

            // Send a recovery email
            Chickatrice::$app->mail->messages()->send('mg.chickatrice.net', [
                'from'      => 'no-reply@chickatrice.net',
                'to'        => $account->email,
                'subject'   => 'Chickatrice Recovery',
                'text'      => $message
            ]);

            return true;
        } else {
            $this->addError('Failed to reset account.');
            return false;
        }
    }

    /** Logins to an existing account with Username and Password */
    private function login() {
        $err_msg = 'Incorrect username or password';

        // Get the accounts
        /** @var Account $account */
        $account = Account::findByName($this->username)->one();
        if ($account == null) {
            $this->addError($err_msg);
            return false;
        }

        /** @var User $user */
        $user = User::findByAccount($account)->one();
        if ($user != null && $user->getSnowflake() !== 0) {
            $this->addError('Account has been linked with Discord.');
            return false;
        }

        // Verify the password
        if (!$account->checkPassword($this->password)) {
            $this->addError($err_msg);
            return false;
        }

        // Login, if there is no user then create one.
        if ($user == null) 
            $user = User::createUser($account->name, $account->email, 0, $account);

        // Flush the user once we have logged in
        Chickatrice::$app->user = User::findByKey($user->getKey())->flush()->one();
        
        // Finally login
        return $user->login();
    }

}