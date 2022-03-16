<?php namespace app\controllers\cli;

use Chickatrice;
use kiss\controllers\cli\Command;
use \GuzzleHttp\Client;
use kiss\helpers\HTTP;

class CardsetCommand extends Command {

    const CARDSET_URL = 'https://mtgjson.com/api/v5/AllPrintings.sql';
    const IDENTIFIER_URL = 'https://mtgjson.com/api/v5/AllIdentifiers.json';

    public function cmdDefault() {
        self::print('Welcome to the default command');
    }

    /** Downloads the cards */
    public function cmdDownload() {
        // Create stage directory
        $stageDir =  Chickatrice::$app->baseDir() . '/_staging';
        $stageFile = $stageDir . '/identifiers.json';
        if (!file_exists($stageDir))
            mkdir($stageDir);

        if (!$this->download($stageFile)) {
            return false;
        }

        // Convert to SQL
        self::print('Converting...');
        exec('npx mtgjson-identifier-sql "'.$stageFile.'"');
        self::print('done.');
    }

    public function cmdImport() {

        // Create stage directory
        $stageDir =  Chickatrice::$app->baseDir() . '/_staging';
        $stageFile = $stageDir . '/identifiers.json';
        if (!file_exists($stageDir))
            mkdir($stageDir);

        if (!$this->download($stageFile)) {
            return false;
        }

        // Convert to SQL
        self::print('Converting...');
        exec('npx mtgjson-identifier-sql "'.$stageFile.'"');

        self::print('Importing SQL... ');
        
        $resource = fopen($stageFile . '.sql', 'r');
        try {
            $query = '';
            $line = '';
            $statements = [];
            do {
                $line = fgets($resource);
                $indexOfSemicolon = stripos($line, ';');
                if ($indexOfSemicolon) {
                    // Slap the last bit on
                    $query .= substr($line, 0, $indexOfSemicolon +1);
                    Chickatrice::$app->db()->exec($query);

                    // Reset the query
                    $query = substr($line, $indexOfSemicolon +1);                    
                } else {
                    $query .= $line . "\n";
                }
            } while($line !== false);

        } finally {
            fclose($resource);
        }
        
        self::print('Done.');
    }

    function download($destination, $attempt = 1) {        
        $guzzle = new Client();

        // Download the resource
        if (!KISS_DEBUG || !file_exists($destination)) {
            self::print('Downloading Identifier Database...');
            $resource = fopen($destination, 'w');
            $guzzle->request('GET', self::IDENTIFIER_URL, [ 'sink' => $resource ]);
        }

        // Verify the download
        self::print('Verifying Download...');
        $response = $guzzle->request('GET', self::IDENTIFIER_URL . '.sha256');
        $expected_sha = $response->getBody()->getContents();~
        $resulting_sha = hash_file('sha256', $destination);
        if ($expected_sha != $resulting_sha) {
            self::print('Failed to download file. Mismatch SHA signatures. Expected ' . $expected_sha . ' but got ' . $resulting_sha);

            unlink($destination);

            self::print('Attempting Download aggain... ' . $attempt . ' remaining');
            return $this->download($destination, $attempt - 1);
        }

        return true;
    }

    function fetchTag($card) {
        $guzzle = new Client();
        // await fetch("https://tagger.scryfall.com/graphql", {
        //     "credentials": "include",
        //     "headers": {
        //         "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:98.0) Gecko/20100101 Firefox/98.0",
        //         "Accept": "*/*",
        //         "Accept-Language": "en-GB,en;q=0.5",
        //         "content-type": "application/json",
        //         "X-CSRF-Token": "rwL/R/l9d+Z/EW1Rc+YKAU1nUz7+Pc7EYOgQ5DVxk5wm+e8NgmN9aNyWlekRjmDBgmJbJVMBDkbl7TukAibOwQ==",
        //         "Alt-Used": "tagger.scryfall.com",
        //         "Sec-Fetch-Dest": "empty",
        //         "Sec-Fetch-Mode": "cors",
        //         "Sec-Fetch-Site": "same-origin",
        //         "Pragma": "no-cache",
        //         "Cache-Control": "no-cache"
        //     },
        //     "referrer": "https://tagger.scryfall.com/card/a25/101",
        //     "body": "{\"operationName\":\"FetchCard\",\"variables\":{\"back\":false,\"set\":\"a25\",\"number\":\"101\"},\"query\":\"query FetchCard($set: String!, $number: String!, $back: Boolean = false) {\\n  card: cardBySet(set: $set, number: $number, back: $back) {\\n    ...CardAttrs\\n    backside\\n    layout\\n    scryfallUrl\\n    sideNames\\n    twoSided\\n    rotatedLayout\\n    taggings {\\n      ...TaggingAttrs\\n      tag {\\n        ...TagAttrs\\n        ancestorTags {\\n          ...TagAttrs\\n          __typename\\n        }\\n        __typename\\n      }\\n      __typename\\n    }\\n    relationships {\\n      ...RelationshipAttrs\\n      __typename\\n    }\\n    __typename\\n  }\\n}\\n\\nfragment CardAttrs on Card {\\n  artImageUrl\\n  backside\\n  cardImageUrl\\n  collectorNumber\\n  id\\n  illustrationId\\n  name\\n  oracleId\\n  printingId\\n  set\\n  __typename\\n}\\n\\nfragment RelationshipAttrs on Relationship {\\n  classifier\\n  classifierInverse\\n  annotation\\n  contentId\\n  contentName\\n  createdAt\\n  creatorId\\n  foreignKey\\n  id\\n  name\\n  relatedId\\n  relatedName\\n  status\\n  type\\n  __typename\\n}\\n\\nfragment TagAttrs on Tag {\\n  category\\n  createdAt\\n  creatorId\\n  id\\n  name\\n  slug\\n  status\\n  type\\n  typeSlug\\n  __typename\\n}\\n\\nfragment TaggingAttrs on Tagging {\\n  annotation\\n  contentId\\n  createdAt\\n  creatorId\\n  foreignKey\\n  id\\n  type\\n  status\\n  weight\\n  __typename\\n}\\n\"}",
        //     "method": "POST",
        //     "mode": "cors"
        // });
    }
}