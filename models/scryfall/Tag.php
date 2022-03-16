<?php namespace app\models\scryfall;

use DateTime;
use kiss\db\ActiveQuery;
use kiss\db\ActiveRecord;

/**
 * @property string $uuid
 * @property bool $category
 * @property DateTime $createdAt
 * @property string $name
 * @property string $type
 * @property-read Tag[] $ancestors
 * @package app\models\scryfall
 */
class Tag extends ActiveRecord {

    public static function tableName() {
        return '$scryfall_tags';
    }

    public static function tableKey() {
        return ['uuid'];
    }

    protected $uuid;
    protected $category;
    protected $created_at;
    protected $name;
    protected $type;
    protected $status;

    /** @return DateTime the time the tag was created */
    public function getCreatedAt() {
        return DateTime::createFromFormat('Y-m-d H:i:s', $this->created_at);
    }

    /** sets the time the card was created
     * @param DateTime $dateTime
     * @return $this
     */
    public function setCreatedAt($dateTime) {
        $this->created_at = $dateTime->format('Y-m-d H:i:s');
        return $this;
    }

    /**
     * Gets the ancestors of the tag
     * @return ActiveQuery|Tag[]
     */
    public function getAncestors() {
        return static::find()
            ->leftJoin('$scryfall_tags_ancestory', [ 'uuid' => 'ancestor' ])
            ->where(['`$scryfall_tags_ancestory`.`uuid`', $this->uuid]);
    }
}