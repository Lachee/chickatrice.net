<?php namespace app\models;

use kiss\db\ActiveQuery;
use kiss\db\ActiveRecord;
use kiss\Kiss;
use kiss\models\BaseObject;

class Identifier extends ActiveRecord {

    public $uuid;
    public $name;
    public $text;
    public $set_code;
    public $multiverse_id;
    public $scryfall_id;

    /** @return string image url */
    public function getImageUrl() {
        return "https://api.scryfall.com/cards/{$this->scryfall_id}?format=image";
    }

    public static function tableName() {
        return "chickatrice_cards";
    }

    /** @return ActiveQuery|Identifier[]  */
    public static function findByName($name) {
        return static::find()->where(['name', $name]);
    }

    /** Creates an active query
     * @return ActiveQuery
     */
    public static function query() {
        $query = parent::query();
        $query->cacheDuration = 24 * 60 * 60;
        return $query;
    }
}