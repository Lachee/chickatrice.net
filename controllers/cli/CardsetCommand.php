<?php namespace app\controllers\cli;

use Chickatrice;
use kiss\controllers\cli\Command;
use \GuzzleHttp\Client;

class CardsetCommand extends Command {

    const CARDSET_URL = 'https://mtgjson.com/api/v5/AllPrintings.sql';
    const IDENTIFIER_URL = 'https://mtgjson.com/api/v5/AllIdentifiers.json';

    public function cmdDefault() {
        self::print('Welcome to the default command');
    }

    public function cmdImport() {
        // Create stage directory
        $stageDir =  Chickatrice::$app->baseDir() . '/_staging';
        $stageFile = $stageDir . '/identifiers.json';
        if (!file_exists($stageDir))
            mkdir($stageDir);

        $guzzle = new Client();
        
        // Download the resource
        self::print('Downloading Identifier Database...');
        $resource = fopen($stageFile, 'w');
        $guzzle->request('GET', self::IDENTIFIER_URL, [ 'sink' => $resource ]);
        
        // Verify the download
        self::print('Verifying Download...');
        $response = $guzzle->request('GET', self::IDENTIFIER_URL . '.sha256');
        $expected_sha = $response->getBody()->getContents();~
        $resulting_sha = hash_file('sha256', $stageFile);
        if ($expected_sha != $resulting_sha) {
            self::print('Failed to download file. Mismatch SHA signatures. Expected ' . $expected_sha . ' but got ' . $resulting_sha);
        }

        // Convert to SQL
        self::print('Converting...');
        exec('npx mtgjson-identifier-sql "'.$stageFile.'"');

        self::print('Importing SQL... ');
        $resource = fopen($stageFile . '.sql', 'r');
        try {
            $query = '';
            $line = '';
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

            printf($line);
        } finally {
            fclose($resource);
        }
        self::print('Done.');
    }

    public function cmdImportSQL() {        
        // 
        self::print('Downloading Card Database...');

        // Create stage directory
        $stageDir =  Chickatrice::$app->baseDir() . '/_staging';
        $stageFile = $stageDir . '/cardset.sql';
        if (!file_exists($stageDir))
            mkdir($stageDir);


        $guzzle = new Client();

        // Download the resource
        if (!KISS_DEBUG || !file_exists($stageFile)) {
            $resource = fopen($stageFile, 'w');
            $guzzle->request('GET', self::CARDSET_URL, [ 'sink' => $resource ]);
        }

        // Verify the download
        $response = $guzzle->request('GET', self::CARDSET_URL . '.sha256');
        $expected_sha = $response->getBody()->getContents();
        $resulting_sha = hash_file('sha256', $stageFile);
        if ($expected_sha != $resulting_sha) {
            self::print('Failed to download file. Mismatch SHA signatures. Expected ' . $expected_sha . ' but got ' . $resulting_sha);
        }

        // Drop existing tables
        // $query = Chickatrice::$app->db()->createQuery();
        
        // Run the import
        self::print('Importing SQL... ');
        $resource = fopen($stageFile, 'r');
        try {
            $query = '';
            $line = '';
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

            printf($line);
        } finally {
            fclose($resource);
        }
        self::print('Done.');
    }
}