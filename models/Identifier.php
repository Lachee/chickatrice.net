<?php namespace app\models;

use kiss\db\ActiveQuery;
use kiss\db\ActiveRecord;

class Identifier extends ActiveRecord {

    public $uuid;
    public $name;
    public $text;
    public $set_code;
    public $multiverse_id;
    public $scryfall_id;

    public static function tableName() {
        return "chickatrice_cards";
    }

    /** @return ActiveQuery|Identifier[]  */
    public static function findByName($name) {
        return static::find()->where(['name', $name]);
    }
}