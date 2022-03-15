<?php

namespace app\models\cockatrice;

use app\models\Identifier;
use Chickatrice;
use Exception;
use kiss\db\ActiveQuery;
use kiss\db\ActiveRecord;
use kiss\helpers\HTML;
use kiss\helpers\HTTP;
use kiss\helpers\Strings;
use SimpleXMLElement;

class Deck extends ActiveRecord
{
    const ZONE_MAIN = 'main';
    const ZONE_SIDE = 'side';
    const ZONE_TOKENS = 'tokens';

    public static function tableName()
    {
        return "cockatrice_decklist_files";
    }

    public $id;
    public $id_user;
    public $id_folder;
    public $name;
    public $upload_time;
    public $content;
    
    public $comment;
    public $zones;
    public $publicUrl;

    protected $isDecomposed = false;

    /** @inheritdoc */
    public function afterQueryLoad($data)
    {
        parent::afterQueryLoad($data);
        $this->decomposeData();
    }

    /** decomposes the data into its basic form */
    protected function decomposeData()
    {
        $this->isDecomposed = true;

        $xml = new SimpleXMLElement($this->content);
        $this->comment = $xml->comments;
        $this->publicUrl = strval($xml->url);

        $this->zones = [];
        foreach ($xml->zone as $zone) {
            // Decompose the zone
            $name = strval($zone['name']);
            $this->zones[$name] = [];

            // Add the card names
            foreach ($zone->card as $card) {
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
    public function loadIdentifiers()
    {
        foreach ($this->zones as $name => $cards) {
            foreach ($cards as $index => $card) {
                $identifier = Identifier::findByName($card['name'])->one();
                if ($identifier != null) {
                    $this->zones[$name][$index]['identifier'] = $identifier;
                }
            }
        }
        return $this->zones;
    }

    /** @return string Gets the deck preview image */
    public function getImageUrl()
    {
        $redisKey = "decks:{$this->id}:image";
        $image = Chickatrice::$app->redis()->get($redisKey);
        if (!empty($image)) 
            return $image;

        $zones = array_values($this->zones);
        for ($i = 0; $i < count($zones[0]); $i++) {
            $card = $zones[0][$i];
            if (!isset($card['identifier']))
                $card['identifier'] = Identifier::findByName($card['name'])->one();

            if ($card['identifier'] != null) {
                $image = $card['identifier']->getImageUrl();
                break;
            }
        }

        Chickatrice::$app->redis()->set($redisKey, $image);
        Chickatrice::$app->redis()->expire($redisKey, (empty($image) ? 60 : 24 * 60 * 60));
        return $image;
    }

    /** @return int counts the total cards */
    public function getCardCount()
    {
        $count = 0;
        foreach ($this->zones as $cards) {
            foreach ($cards as $card) {
                $count += $card['count'];
            }
        }
        return $count;
    }

    /** calculates the hash of the deck 
     * @return string Deck's hash
     * @see https://github.com/Cockatrice/Cockatrice/blob/88a8ee09bdb18a59025a6ce0e4153b7208aaadb4/common/decklist.cpp#L800-L833
    */
    public function calculateHash() {
        if (!$this->isDecomposed)
            $this->decomposeData();

        $isValidDeckList    = true;
        $cardList           = [];
        $hashZones          = [ self::ZONE_MAIN, self::ZONE_SIDE ];
        $optionalZones      = [ self::ZONE_TOKENS ];

        foreach($this->zones as $zoneName => $zoneList) {
            foreach($zoneList as $j => $card) {
                if (in_array($zoneName, $hashZones)) {
                    for ($k = 0; $k < $card['count']; $k++) {
                        $cardList[] = ($zoneName === self::ZONE_SIDE ? 'SB:' : '') . Strings::toLowerCase($card['name']);
                    }
                } else if (!in_array($zoneName, $optionalZones)) {
                    $isValidDeckList = false;
                }
            }
        }

        sort($cardList);
        $deckHashArray = sha1(join(';', $cardList), true);
        $deckHashBinary = unpack('C*', $deckHashArray);
        $number =   (($deckHashBinary[1+0]) << 32) +
                    (($deckHashBinary[1+1]) << 24) +
                    (($deckHashBinary[1+2]) << 16) +
                    (($deckHashBinary[1+3]) << 8) +
                    (($deckHashBinary[1+4]));
                    
        $number = base_convert($number, 10, 32);
        $deckHash = $isValidDeckList ? str_pad(strval($number), 8, "0", STR_PAD_RIGHT) : 'INVALID';
        return $deckHash;
    }

    /** Finds the decks for the user
     * @param Account|int $account 
     * @return ActiveQuery|Deck[]
     */
    public static function findByAccount($account)
    {
        $accountId = $account instanceof Account ? $account->id : $account;
        return static::find()->where(['id_user', $accountId]);
    }

    /** Downloads the Moxfield data and creates a new Deck.
     * @return Deck a new unsaved deck with no owner.
      */
    public static function importMoxfield($moxfieldId, \GuzzleHttp\Client $guzzle = null)
    {
        if ($guzzle == null)
            $guzzle = new \GuzzleHttp\Client();

        $guzzle = new \GuzzleHttp\Client();
        $response = $guzzle->request('GET', "https://api.moxfield.com/v2/decks/all/{$moxfieldId}");
        if ($response->getStatusCode() != HTTP::OK)
            throw new Exception('Deck was not found');

        $mox = json_decode($response->getBody()->getContents());

        // Parse the zones
        $zones = [
            'mainboard' => [],
            'sideboard' => [],
            'maybeboard' => [],
            'commanders' => []
        ];
        foreach ($zones as $zoneName => $zoneList) {
            foreach ($mox->{$zoneName} as $cardInfo) {
                $qty    = $cardInfo->quantity;
                $card   = $cardInfo->card;
                $name   = $card->name;
                $scryid = $card->scryfall_id;
                $price  = 0;

                if (property_exists($card->prices, 'usd'))
                    $price = $card->prices->usd;

                $zones[$zoneName][] = [
                    'number'        => $qty,
                    'price'         => $price,
                    'name'          => $name,
                    'scryfall_id'   => $scryid
                ];
            }
        }

        // Convert to XML, renaming the boards and culling the empty
        foreach ($zones as $zoneName => $zoneList) {
            if (count($zoneList) == 0) {
                unset($zones[$zoneName]);
                continue;
            }

            if (Strings::endsWith($zoneName, 'board')) {
                $zoneNewName = substr($zoneName, 0, -5);
                $zones[$zoneNewName] = $zones[$zoneName];
                unset($zones[$zoneName]);
                $zoneName = $zoneNewName;
            }

            // convert to xml
            $xml = "";
            foreach ($zones[$zoneName] as $card) {
                $attributes = [];
                foreach ($card as $attribute => $value) {
                    $attributes[] = HTML::encode($attribute) . '="' . HTML::encode($value) . '"';
                }

                $xml .= "<card " . join(' ', $attributes) . '/>';
            }

            $name = HTML::encode(Strings::trim(Strings::printable($zoneName)));
            $zones[$zoneName] = "<zone name=\"{$name}\">$xml</zone>";
        }

        // Generate the XML
        $deckName       = Strings::trim(Strings::printable($mox->name));
        $deckComment    = Strings::trim(Strings::printable($mox->description));
        $deckSource     = $mox->publicUrl;

        $zones = join($zones);
$XML = <<<XML
<?xml version="1.0"?>
<cockatrice_deck version="1">
    <deckname>$deckName</deckname>
    <comments>$deckComment</comments>
    <url>$deckSource</url>
    $zones
</cockatrice_deck>
XML;

        // Return the new unsaved deck file
        return new Deck([
            'name'      => $deckName,
            'id_folder' => 0,
            'upload_time' => date("Y-m-d H:i:s"),
            'content'   => $XML
        ]);
    }
}
