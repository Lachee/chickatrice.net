<?php namespace app\models\forms;

use kiss\helpers\HTML;
use kiss\models\forms\Form;
use kiss\schema\BooleanProperty;
use kiss\schema\StringProperty;

class LoginForm extends Form {

    public $email;
    public $password;

    public $btn_recover;
    public $btn_register;
    public $btn_login;

    protected function init() {
        parent::init();
    }

    public static function getSchemaProperties($options = [])
    {
        $btnParser = function($value) { return $value === '' || filter_var($value, FILTER_VALIDATE_BOOL | FILTER_NULL_ON_FAILURE); };
        return [
            'email' => new StringProperty('', 'test@example.com', [ 'title' => 'Email' ]),
            'password' => new StringProperty('', '', [ 'title' => 'Password' ]),

            'btn_recover' => new BooleanProperty('', '', [ 'parser' => $btnParser, 'required' => false, 'options' => [ 'hidden' => true ], ]),
            'btn_register' => new BooleanProperty('', '', [ 'parser' => $btnParser, 'required' => false, 'options' => [ 'hidden' => true ], ]),
            'btn_login' => new BooleanProperty('', '', [ 'parser' => $btnParser, 'required' => false, 'options' => [ 'hidden' => true ], ]),
        ];
    }

    
}