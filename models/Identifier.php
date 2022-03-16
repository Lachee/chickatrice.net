<?php namespace app\models;

use app\models\scryfall\Tag;
use Chickatrice;
use DateTime;
use DateTimeInterface;
use Exception;
use kiss\db\ActiveQuery;
use kiss\db\ActiveRecord;
use kiss\exception\SQLDuplicateException;
use kiss\helpers\GuzzleUtils;
use kiss\helpers\Strings;
use kiss\Kiss;
use kiss\models\BaseObject;

class Identifier extends ActiveRecord {


    public static function tableName() {
        return '$cards';
    }

    /** The ID of the table */
    public static function tableKey() { return ['uuid']; }


    public $uuid;
    public $name;
    public $text;
    public $set_code;
    public $set_number;
    public $multiverse_id;
    public $scryfall_id;

    /** @return string image url */
    public function getImageUrl() {
        return "https://api.scryfall.com/cards/{$this->scryfall_id}?format=image";
    }

    /** Gets the stored tags to this card 
     * @return ActiveQuery|Tag[]
    */
    public function getTags() {
        return Tag::find()
                    ->leftJoin('$cards_tags', ['uuid'  => 'tag'])
                    ->where(['$cards_tags.uuid', $this->uuid]);
    }

    /** Queries all the tags from Scryfall and saves them to the database.
     * @return ActiveQuery|Tag[] $tags
     */
    public function downloadTags() {
        if (!Chickatrice::$app->tagger)
            return false;
        Chickatrice::$app->tagger->downloadTags($this);
        return $this->getTags();
    }

    /** @return ActiveQuery|Identifier[]  */
    public static function findByName($name) {
        return static::find()->where(['name', $name]);
    }

    /** @inheritdoc */
    public static function query($db = null) {
        $query = parent::query($db);
        $query->cacheDuration = 24 * 60 * 60;

        if (KISS_DEBUG)
            $query->cacheDuration = -1;

        return $query;
    }
}