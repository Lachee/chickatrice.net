<?php
defined('BASE_URL') or define('BASE_URL', 'http://gall.local:81/');

$config = function() {
    return [
        '$class'            => Chickatrice::class,
        'title'             => 'Chickatrice',
        'logo'              => '/images/gall_full.png',
        'mainController'    => \app\controllers\base\MainController::class,
        'baseUrl'           => BASE_URL,
        'proxySettings'         => [
            'baseUrl'   => 'https://proxy.com/',
            'key'       => 'sss',
            'salt'      => 'sss'
        ],
        'db' => [
            '$assoc'    => true,
            'dsn'       => 'mysql:dbname=xve;host=localhost',
            'user'      => 'username',
            'pass'      => 'password',
            'prefix'    => 'gall_',
        ],
        'redis'         => new \Predis\Client([], ['prefix' => 'GALL:']),
		'jwtProvider'	=> [ 
            '$class'	    => \kiss\models\JWTProvider::class,
            'algo'          => \kiss\models\JWTProvider::ALGO_RS512,
			'privateKey'    => file_get_contents(__DIR__ . "/jwt-private.key"),
			'publicKey'     => file_get_contents(__DIR__ . "/jwt-public.key"),
		],
        'components'    => [
            '$assoc' => true,
            'discord' => [
                '$class'        => \app\components\discord\Discord::class,
                'clientId'      => 'client id',     // The client ID assigned to you by the provider
                'clientSecret'  => 'client secret', // The client password assigned to you by the provider
                'botToken'      => 'bot token',     // The bot token. Used so far to get profiles of those who have not registered.
                'scopes'        => [ 'identify' ]
            ],
        ]
    ];
};
