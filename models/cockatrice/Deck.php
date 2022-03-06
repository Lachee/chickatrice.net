<?php namespace app\models\cockatrice;

use app\models\Identifier;
use kiss\db\ActiveQuery;
use kiss\db\ActiveRecord;
use SimpleXMLElement;

class Deck extends ActiveRecord {
    public static function tableName() { return "cockatrice_decklist_files"; }
    
    public $id;
    public $folder_id;
    public $id_user;
    public $name;
    public $upload_time;
    public $content;

    /** @return ActiveQuery|mixed */
    public function getData() {
        return new SimpleXMLElement($this->content);
    }

    /** Finds the decks for the user
     * @param Account $account 
     * @return ActiveQuery|Deck[]
     */
    public static function findByAccount(Account $account) {
        return static::find()->where(['id_user', $account->id]);
    }
}