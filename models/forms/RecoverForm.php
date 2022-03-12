<?php namespace app\models\forms;

use app\models\cockatrice\Account;
use app\models\User;
use Chickatrice;
use kiss\exception\HttpException;
use kiss\helpers\Arrays;
use kiss\helpers\HTML;
use kiss\helpers\HTTP;
use kiss\models\forms\Form;
use kiss\schema\StringProperty;

class RecoverForm extends Form {

    /** @var Account $account */
    protected $account = null;

    public $token;
    public $password;
    public $passwordConfirm;

    protected function init() {
        parent::init();

        $this->account = Account::findByToken($this->token)->one();
        if ($this->account == null || empty($this->token))
            throw new HttpException(HTTP::BAD_REQUEST, 'Invalid token provided');

    }

    public static function getSchemaProperties($options = [])
    {
        return [
            'token' => new StringProperty('', '', [ 'title' => 'Token', 'readOnly' => true ]),
            'password' => new StringProperty('', '', [ 'title' => 'Password', 'options' => [ 'password' => true ]]),
            'passwordConfirm' => new StringProperty('', '', [ 'title' => 'Password Confirm', 'options' => [ 'password' => true ]]),
        ];
    }

    /** Renders a text field
     * @param string $name the property name
     * @param StringProperty $scheme
     * @return string
    */
    protected function fieldToken($name, $scheme, $options = []) {
        return HTML::input('text', [ 
            'class'         => 'input',
            'value'         => $this->getProperty($name, ''),
            'disabled'      => $scheme->getProperty('readOnly', false)
        ]) . HTML::input('hidden', [
            'name'  => $name,
            'value' => $this->getProperty($name, ''),
        ]);
    }

    public function validate()
    {
        if (!parent::validate())
            return false;

        if (empty($this->password))
        {
            $this->addError('Password cannot be empty');
            return false;
        }

        // Validate password
        if ($this->password != $this->passwordConfirm)
        {
            $this->addError('Passwords do not match');
            return false;
        }

        // Ensure the validity of the token
        if (!empty($this->account->token)) {
            $this->addError('Invalid Token Provided.');
            return false;
        }

        if ($this->account->token !== $this->token) {
            $this->addError('Invalid Token Provided.');
            return false;
        }

        // Ensure it isn't a discord account
        /** @var User $user */
        $user = User::findByAccount($this->account)->one();
        if ($user && $user->getSnowflake() > 0) {
            $this->addError('Cannot recover Discord Accounts.');
            return false;
        }

        return true;
    }

    public function getUsername() {
        return $this->account->name;
    }


    public function save($validate = false)
    {        
        //Failed to load
        if ($validate && !$this->validate()) {
            return false;
        }
     
        $this->account->token = '';
        $this->account->setPassword($this->password);
        if ($this->account->save()) {
            $name = $this->account->name;
            $ip = HTTP::ip();
            $agent = HTTP::userAgent();
            $message = <<<TXT
Hello $name,

    Your account has been recently recovered and the password was reset.
    
    $ip
    $agent
    
    If you did not authorise this account reset, please request a new one and change
    your password immediately. 
    
    Tip: 
    Link using your Discord Account to prevent this in the future.

Cheers,
    Lachee
TXT;

            // Ensure they know their account was reset
            Chickatrice::$app->mail->messages()->send('mg.chickatrice.net', [
                'from'      => 'no-reply@chickatrice.net',
                'to'        => $this->account->email,
                'subject'   => 'Chickatrice Recovery',
                'text'      => $message
            ]);

            return true;
        }

        return false;
    }
}