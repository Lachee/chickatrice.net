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

class RegisterForm extends Form {

    public const ALLOW_REGISTRATION = true;

    public $username;
    public $email;
    public $password;
    public $passwordConfirm;

    protected function init() {
        parent::init();
    }

    public static function getSchemaProperties($options = [])
    {
        return [
            'username' => new StringProperty('', 'xXBestMagicPlayerXx', ['title' => 'Username']),
            'email' => new StringProperty('', 'test@example.com', [ 'title' => 'Email' ]),
            'password' => new StringProperty('', '', [ 'title' => 'Password', 'options' => [ 'password' => true ]]),
            'passwordConfirm' => new StringProperty('', '', [ 'title' => 'Password Confirm', 'options' => [ 'password' => true ]]),
        ];
    }

    public function render($options = [])
    {
        if (!self::ALLOW_REGISTRATION)
            return HTML::tag('i', 'Registration via email and username has been disabled. Please use Third-Party method or try again later.');
        return parent::render($options);
    }

    public function load($data = null)
    {
        if (!parent::load($data)) {
            return false;
        }

        $this->username = Strings::safe($this->username);
        return true;
    }

    public function validate()
    {
        if (!self::ALLOW_REGISTRATION)
        {
            $this->addError('Registration has been disabled');
            return false;    
        }

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

        // Ensure account doesnt exist
        if (Account::findByEmail($this->email)->any() || Account::findByName($this->username)->any()) {
            $this->addError('Account already exist. Please try a different username or email.');
            return false;
        }

        // Ensure the email address isn't forbidden
        $emailError = self::checkEmailSpam($this->email);
        if ($emailError !== true) {
            $this->addError($emailError);
            return false;
        }

        return true;
    }

    public function save($validate = false)
    {        
        //Failed to load
        if ($validate && !$this->validate()) {
            return false;
        }
        
        if (!self::ALLOW_REGISTRATION)
        {
            $this->addError('Registration has been disabled');
            return false;    
        }

        $user = User::createUser($this->username, $this->email, 0);
        $account = $user->getAccount();
        $account->setPassword($this->password);
        static::sendAccountActivation($account);
        Chickatrice::$app->session->addNotification('Your account has been created. Please check your emails before you can use it.');
        return true;
    }

    public static function checkEmailSpam($email) {
        if (empty($email)) 
            return "Email cannot be empty";

        //Validate the parts of the email.
        $email_parts = explode('@', $email);
        if (count($email_parts) != 2) return "Email does not match User@Domain.";

        //Validate the domain
        $email_full_domain = $email_parts[1];
        $email_domains = explode('.', $email_full_domain);
        if (count($email_domains) < 2) return "Email domain is invalid.";

        $email_tld = $email_domains[count($email_domains) - 1];

        //Validate the TLD
        $valid_tld = array('com', 'it','de','fr', 'net','edu','uk', /*'ru',*/ 'ca','br', 'es','pl', 'org','cz','au','at','nl', 'ch','hu','se','cl', 'ie', 'jp', 'pt','us', 'co');    
        if (!in_array($email_tld, $valid_tld)) return "TLD {$email_tld} is invalid.";

        //Check a hard coded blocklist that is curated by me.
        $emailListFileName = Chickatrice::$app->baseDir() . "/components/badmail.txt";
        $emaillist = file_get_contents($emailListFileName);
        $emaillist = str_replace("\r\n", "\n", $emaillist);
        $emaillist = str_replace(" ", "", $emaillist);
        $banned_emails = explode("\n", $emaillist);

        if (in_array($email_full_domain, $banned_emails))
            return $email_full_domain . " is blocked.";

        if (in_array($email, $banned_emails))
            return $email . " is blocked.";

        //Final check. Using StopForumSpam API to check the email address. If its above 30% likelihood of a spam account, then block the email.
        $url = "http://api.stopforumspam.org/api?email=" . urlencode($email) . "&json";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);    
        $json = curl_exec($ch);
        curl_close($ch);

        //Validate the actual email
        $result = json_decode($json, true);
        if ($result['success'] != 1) return "Failed to validate email.";
        if (isset($result['email']['confidence']))
        { 
            if ($result['email']['confidence'] > 80)
            {
                //We are actually going to add the email to the list because its so high.
                $email_parts = explode('@', $email, 2);
                if (count($email_parts) != 2) {                    
                    $suspectFileName = Chickatrice::$app->baseDir() . "/components/badmail.suspect.txt";
                    $suspects = @file_get_contents($suspectFileName);
                    file_put_contents($suspectFileName, $email . "\n" . $suspects);
                } else {
                    $emaillist = @file_get_contents($emailListFileName);
                    file_put_contents($emailListFileName, $email_parts[1] . "\n" . $emaillist);
                }
                return "Spam email detected. Email is blocked.";
            }

            //High confidence still, this is the minium.
            if ($result['email']['confidence'] > 30)
                return "High probability of spam email.";       
        }

        return true;
    }

    public static function sendAccountActivation($account) {
        $account->active = 0;
        $account->token = Strings::token();
        $account->save();       

        $name = $account->name;
        $token = HTTP::url(['/activate', 'token' => $account->token], true);

$message = <<<TXT

Welcome $name,

    Thank you for registering your account on Chickatrice.net. 
    
    Before you can play, you need to activate your account. This can be done by following the
    link in this email or signing in with Discord.    
    I was too lazy to make a fancy email, so have this plain text one instead! Its very
    doubtful that anyone reads these anyways. 
    

    Please activate your account by going to the following link:
    $token

Cheers,
    Lachee
TXT;

        Chickatrice::$app->mail->messages()->send('mg.chickatrice.net', [
            'from'      => 'no-reply@chickatrice.net',
            'to'        => $account->email,
            'subject'   => 'Chickatrice Activation',
            'text'      => $message
        ]);

    }
}