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

    /** Queries all the tags from Scryfall and saves them to the database. */
    public function buildTags(\GuzzleHttp\Client $guzzle = null) {
        if ($guzzle === null) 
            $guzzle = new \GuzzleHttp\Client([ 'cookies' => true ]);

        // Prepare the code
        $code = Strings::toLowerCase($this->set_code);
        $numb = strval($this->set_number);

        // Fetch the CSRF token for this page
        $url = "https://tagger.scryfall.com/card/{$code}/{$numb}";
        $response = $guzzle->request('GET', $url);
        $content = GuzzleUtils::chunkReadUntil($response->getBody(), function($partial) {
            return strripos($partial, '</head>') >= 0;
        });        
        preg_match('/<meta name="csrf-token" content="(.*)"\s?\/>/', $content, $matches);
        $csrf_token = $matches[1];

        // Fetch the tag list fromm the GraphQL. This is very hacky.
        $response = $guzzle->request('POST', 'https://tagger.scryfall.com/graphql', [
            'headers' => [
                "User-Agent"        => "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv =>98.0) Gecko/20100101 Firefox/98.0",
                "Accept"            => "*/*",
                "Accept-Language"   => "en-GB,en;q=0.5",
                "content-type"      => "application/json",
                "X-CSRF-Token"      => $csrf_token,
                "Alt-Used"          => "tagger.scryfall.com",
                "Sec-Fetch-Dest"    => "empty",
                "Sec-Fetch-Mode"    => "cors",
                "Sec-Fetch-Site"    => "same-origin",
                "Pragma"            => "no-cache",
                "Cache-Control"     => "no-cache",
                "Origin"            => "https://tagger.scryfall.com",
                "Referrer"          => $url
            ],
            'body' => json_encode([
                'operationName' => 'FetchCard',
                'variables' => [
                    'back' => false,
                    'set' => $code,
                    'number' => $numb
                ],
                'query' => "query FetchCard(\$set: String!, \$number: String!, \$back: Boolean = false) {\n  card: cardBySet(set: \$set, number: \$number, back: \$back) {\n    ...CardAttrs\n    backside\n    layout\n    scryfallUrl\n    sideNames\n    twoSided\n    rotatedLayout\n    taggings {\n      ...TaggingAttrs\n      tag {\n        ...TagAttrs\n        ancestorTags {\n          ...TagAttrs\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    relationships {\n      ...RelationshipAttrs\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment CardAttrs on Card {\n  artImageUrl\n  backside\n  cardImageUrl\n  collectorNumber\n  id\n  illustrationId\n  name\n  oracleId\n  printingId\n  set\n  __typename\n}\n\nfragment RelationshipAttrs on Relationship {\n  classifier\n  classifierInverse\n  annotation\n  contentId\n  contentName\n  createdAt\n  creatorId\n  foreignKey\n  id\n  name\n  relatedId\n  relatedName\n  status\n  type\n  __typename\n}\n\nfragment TagAttrs on Tag {\n  category\n  createdAt\n  creatorId\n  id\n  name\n  slug\n  status\n  type\n  typeSlug\n  __typename\n}\n\nfragment TaggingAttrs on Tagging {\n  annotation\n  contentId\n  createdAt\n  creatorId\n  foreignKey\n  id\n  type\n  status\n  weight\n  __typename\n}\n"
            ])
        ]);

        // Parse the JSON data
        $data = json_decode($response->getBody()->getContents(), true);
        $tags = $data['data']['card']['taggings'];

        function process_tag($data, $db) {
            // Create the tag record if it doesnt exist
            if (!Tag::findByKey($data['id'])->any()) {
                $tag = new Tag([
                    'uuid'          => $data['id'],
                    'category'      => $data['category'],
                    'name'          => $data['name'],
                    'type'          => $data['type'],
                    'status'        => $data['status']
                ]);
                $tag->createdAt = DateTime::createFromFormat(DateTimeInterface::ATOM, $data['createdAt']);

                if (!$tag->save()) {
                    throw new Exception('Failed to save');
                }
            }

            // Check the ancestors
            if (isset($data['ancestorTags'])) {
                foreach($data['ancestorTags'] as $ancestor) {
                    process_tag($ancestor, $db);
                    try {
                        $db->createQuery()
                            ->insert([
                                'uuid'      => $data['id'],
                                'ancestor'  => $ancestor['id']
                            ], '$scryfall_tags_ancestory')
                                ->execute();
                    }catch(SQLDuplicateException $_) {
                        /** do nothing, means we already have that ancestor */
                    }
                }
            }
        }

        // Create tags
        $this->db->beginTransaction();
        try {
            foreach($tags as $tag) {
                // Ensure the tag exist
                process_tag($tag['tag'], $this->db);

                try {
                    $this->db->createQuery()
                        ->insert([
                            'uuid'      => $this->uuid,
                            'tag'       => $tag['tag']['id']
                        ], '$cards_tags')
                            ->execute();
                }catch(SQLDuplicateException $_) {
                    /** do nothing, means we already have that tag */
                }
            }
            $this->db->commit();
        }catch(\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }


        return $this->getTags()->all();

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