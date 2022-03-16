<?php

namespace app\components;

use app\models\Identifier;
use app\models\scryfall\Tag;
use DateTime;
use DateTimeInterface;
use Exception;
use kiss\models\BaseObject;
use \GuzzleHttp\Client;
use kiss\exception\SQLDuplicateException;
use kiss\helpers\GuzzleUtils;
use kiss\helpers\Strings;
use kiss\Kiss;
use RuntimeException;

/**
 * @property \kiss\db\Connection DB connection to use
 * @package app\components
 */
class ScryfallTagger extends BaseObject
{

    /** @var \GuzzleHttp\Client $_guzzle */
    private $_guzzle = null;

    private $headers;
    private $_csrf = null;

    /** Initializes the guzzle and gets initial authorization */
    protected function initializeGuzzle()
    {
        $this->_guzzle = new \GuzzleHttp\Client(['cookies' => true]);

        // Fetch the CSRF token for this page
        $response = $this->_guzzle->request('GET', "https://tagger.scryfall.com/card/plist/109");
        $content = GuzzleUtils::chunkReadUntil($response->getBody(), function ($partial) {
            return strripos($partial, '</head>') >= 0;
        });
        preg_match('/<meta name="csrf-token" content="(.*)"\s?\/>/', $content, $matches);
        $this->_csrf = $matches[1];

        $this->headers =  [
            "User-Agent"        => "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv =>98.0) Gecko/20100101 Firefox/98.0",
            "Accept"            => "*/*",
            "Accept-Language"   => "en-GB,en;q=0.5",
            "content-type"      => "application/json",
            "X-CSRF-Token"      => $this->_csrf,
            "Alt-Used"          => "tagger.scryfall.com",
            "Sec-Fetch-Dest"    => "empty",
            "Sec-Fetch-Mode"    => "cors",
            "Sec-Fetch-Site"    => "same-origin",
            "Pragma"            => "no-cache",
            "Cache-Control"     => "no-cache",
            "Origin"            => "https://tagger.scryfall.com",
        ];
        return $this->_guzzle;
    }

    /** @return \kiss\db\Connection DB connection to use */
    public function getDb() {
        return Kiss::$app->db();
    }

    /** @return \GuzzleHttp\Client current guzzle instance */
    public function getGuzzle()
    {
        if ($this->_guzzle == null)
            $this->initializeGuzzle();
        return $this->_guzzle;
    }

    /**
     * Downloads and stores the tags for the given card
     * @param Identifier $card
     * @throws RuntimeException 
     * @throws Exception 
     */
    public function downloadTags($card)
    {

        // Prepare the code
        $code = Strings::toLowerCase($card->set_code);
        $numb = strval($card->set_number);
        $url = "https://tagger.scryfall.com/card/{$code}/{$numb}";

        // Fetch the tag list fromm the GraphQL. This is very hacky.
        $guzzle = $this->getGuzzle();        
        $headers = array_merge($this->headers, [
            "Referrer" => $url
        ]);

        $response = $guzzle->request('POST', 'https://tagger.scryfall.com/graphql', [
            'headers' => $headers,
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
        if (isset($data['errors']) && count($data['errors']) > 0) {
            throw new RuntimeException('Failed to process card ');
        }

        // Create tags
        $tags = $data['data']['card']['taggings'];
        $this->db->beginTransaction();
        try {
            foreach ($tags as $tag) {
                // Ensure the tag exist
                static::process_tag($tag['tag'], $this->db);

                try {
                    $this->db->createQuery()
                        ->insert([
                            'uuid'      => $card->uuid,
                            'tag'       => $tag['tag']['id']
                        ], '$cards_tags')
                        ->execute();
                } catch (SQLDuplicateException $_) {
                    /** do nothing, means we already have that tag */
                }
            }
            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private static function process_tag($data, $db)
    {
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
            foreach ($data['ancestorTags'] as $ancestor) {
                static::process_tag($ancestor, $db);
                try {
                    $db->createQuery()
                        ->insert([
                            'uuid'      => $data['id'],
                            'ancestor'  => $ancestor['id']
                        ], '$scryfall_tags_ancestory')
                        ->execute();
                } catch (SQLDuplicateException $_) {
                    /** do nothing, means we already have that ancestor */
                }
            }
        }
    }
}
