<?php namespace app\components\discord\interaction;

use app\components\discord\interaction\commands\BaseCommand;
use kiss\models\BaseObject;

class Interaction extends BaseObject {
    const RESPONSE_TYPE_PONG = 1;
    const RESPONSE_TYPE_ACKNOWLEDGE = 2;
    const RESPONSE_TYPE_CHANNEL_MESSAGE = 3;
    const RESPONSE_TYPE_CHANNEL_MESSAGE_WITH_SOURCE = 4;
    const RESPONSE_TYPE_ACKNOWLEDGE_WITH_SOURCE = 5;

    const REQUEST_TYPE_PING = 1;
    const REQUEST_TYPE_APPLICATION_COMMAND = 2;

    const RESPONSE_FLAG_EPHEMERAL = 1 << 6;

    /** @var string[string] $_commands map of name to command*/
    private static $_commands = [];

    public $discord;

    public $type;
    public $token;
    public $member;
    
    public $id;
    public $guild_id;
    public $channel_id;
    
    public $data;

    public function respond() {
        return [
            'type' => Interaction::RESPONSE_TYPE_ACKNOWLEDGE
        ];
    }

    /** Returns a list of commands */
    public static function commands() {
        return self::$_commands;
    }

    /** Registers a command class */
    public static function register($command) {
        if ($command == BaseCommand::class || !is_subclass_of($command, BaseCommand::class)) return false;
        self::$_commands[$command::name()] = $command;
        return true;
    }

    /** Registers all the commands in the directory */
    public static function registerDirectory($directory, $filters = "*.php") {
        if (!is_array($filters)) 
            $filters = [$filters];

        //List of files
        $files = [];
        $commands = [];

        //Go through every filter
        foreach($filters as $filter) {

            //Scan the directory and include all the files
            $glob = glob($directory . $filter);
            foreach ($glob as $filename)
            {
                if (@include_once $filename) {
                    $files[] = $filename;
                }
            }
        }

        //Search all the declared classes and register them
        foreach(get_declared_classes() as $class) {
            if(is_subclass_of($class, BaseCommand::class)) {
                if (self::register($class)) {
                    $commands[] = $class;
                }
            }
        }

        //Return all the commands we found
        return $commands;
    }


}