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

    public $comment;
    public $zones;

    /** @inheritdoc */
    public function afterQueryLoad($data) {
        parent::afterQueryLoad($data);
        $this->decomposeData();
    }

    /** decomposes the data into its basic form */
    private function decomposeData() {
        
        $xml = new SimpleXMLElement($this->content);        
        $this->comment = $xml->comments;

        $this->zones = [];
        foreach($xml->zone as $zone) {
            // Decompose the zone
            $name = strval($zone['name']);
            $this->zones[$name] = [];

            // Add the card names
            foreach($zone->card as $card) {
                $this->zones[$name][] = [ 
                    'name' => strval($card['name']), 
                    'count' => intval($card['number']),
                    'price' => intval($card['price']),
                ];
            }
        }

        return $this;
    }

    /** @return mixed Loads the identifiers for the deck */
    public function loadIdentifiers() {        
        foreach($this->zones as $name => $cards) {
            foreach($cards as $index => $card) {
                $identifier = Identifier::findByName($card['name'])->one();
                if ($identifier != null) {
                    $this->zones[$name][$index]['identifier'] = $identifier;
                }
            }
        }
        return $this->zones;
    }

    /** @return string Gets the deck preview image */
    public function getImageUrl() {
        $zones = array_values($this->zones);
        
        for ($i = 0; $i < count($zones[0]); $i++) {
            $card = $zones[0][$i];
            if (!isset($card['identifier']))
                $card['identifier'] = Identifier::findByName($card['name'])->one();

            if ($card['identifier'] != null)
                return $card['identifier']->getImageUrl();
        }
        return '';
    }

    /** @return int counts the total cards */
    public function getCardCount() {
        $count = 0;
        foreach($this->zones as $cards) {
            foreach($cards as $card) {
                $count += $card['count'];
            }
        }
        return $count;
    }

    /** Finds the decks for the user
     * @param Account $account 
     * @return ActiveQuery|Deck[]
     */
    public static function findByAccount(Account $account) {
        return static::find()->where(['id_user', $account->id]);
    }
}