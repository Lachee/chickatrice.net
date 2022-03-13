<?php namespace app\models\forms;

use app\helpers\Country;
use app\models\cockatrice\Account;
use app\models\User;
use Chickatrice;
use kiss\exception\InvalidOperationException;
use kiss\helpers\Strings;
use kiss\models\forms\Form;
use kiss\schema\EnumProperty;
use kiss\schema\StringProperty;

class UserSettingForm extends Form {

    const DECK_PRIVACY = [
        0 => 'Public',
        1 => 'Copy-Protected',
        // 2 => 'Private' // Removed Private because they can just not upload decks. 
    ];

    /** @var User $user */
    protected $user;
    /** @var Account $account */
    protected $account;

    public $name;
    public $email;
    public $realname;
    public $country;
    public $avatar;

    public $privacy;
    
    public $password;
    public $passwordConfirm;

    protected function init()
    {
        parent::init();

        if ($this->user == null)
            throw new InvalidOperationException('user cannot be null');

        $this->account    = $this->user->getAccount();
        if ($this->account == null)
            throw new InvalidOperationException('account cannot be null');

        $this->name       = $this->account->name;
        $this->email      = $this->account->email;
        $this->realname   = $this->account->realname;
        $this->country    = Strings::toUpperCase($this->account->country);
        $this->avatar     = $this->account->avatar_bmp;
        
        $this->privacy    = $this->user->deck_privacy;
    }

    public static function getSchemaProperties($options = [])
    {
        $countries = Country::flags();
        return [
            'name'              => new StringProperty('', '', [ 'title' => 'Username', 'readOnly' => true, 'required' => false ]),
            'email'             => new StringProperty('', 'test@example.com', [ 'title' => 'Email', 'readOnly' => true,  'required' => false ]),
            'realname'          => new StringProperty('', '', [ 'title' => 'Real Name', 'required' => false ]),
            'country'           => new EnumProperty('', $countries, null, ['title' => 'Country']),
            'privacy'           => new EnumProperty('', array_keys(static::DECK_PRIVACY), null, [ 'title' => 'Deck Privacy', 'required' => false, 'options' => ['enum_titles' =>  array_values(static::DECK_PRIVACY) ]]),
            'password'          => new StringProperty('', '', [ 'title' => 'Password', 'required' => false, 'options' => [ 'password' => true ]]),
            'passwordConfirm'   => new StringProperty('', '', [ 'title' => 'Password Confirm', 'required' => false,  'options' => [ 'password' => true ]]),

        ];
    }

    public function validate()
    {
        if (!parent::validate())
            return false;

        if (!empty($this->password) && $this->password != $this->passwordConfirm) {
            $this->addError('Passwords do not match');
            return false;
        }
        
        return true;
    }

    public function save($validate = false)
    {
        if ($validate && !$this->validate())
            return false;

        // Account options
        $this->account->realname = $this->realname;
        $this->account->country = $this->country;    
        if (!empty($this->password)) {
            $this->account->setPassword($this->password);
            Chickatrice::$app->session->addNotification('Your password has been changed.');
        }

        if ($this->account->active) {
            $this->account->token = '';
        }

        // User options
        $this->user->deck_privacy = intval($this->privacy);
        return $this->user->save() && $this->account->save();
    }
}