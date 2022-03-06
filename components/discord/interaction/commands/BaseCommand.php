<?php namespace app\components\discord\interaction\commands;

use kiss\models\BaseObject;

class BaseCommand extends BaseObject {
    public static function name() { return "command"; }
    public static function description() { return ""; }
    public static function options() { return []; }
}