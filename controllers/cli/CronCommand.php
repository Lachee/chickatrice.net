<?php namespace app\controllers\cli;

use app\models\cockatrice\Account;
use app\models\cockatrice\Deck;
use app\models\cockatrice\ReplayAccess;
use app\models\User;
use Chickatrice;
use kiss\controllers\cli\Command;
use \GuzzleHttp\Client;

class CronCommand extends Command {

    public function cmdAll() {
        self::print('Performing Cron Jobs...');
        $this->cmdDeleteLogs();
        $this->cmdDeleteAccounts();
        $this->cmdDeleteDecks();
        $this->cmdDeleteReplays();
        $this->cmdDeleteOrphanReplays();
        $this->cmdDeleteOrphanGames();
        self::print('Finished Cron Job.');
    }

    /** Clears the logs for the month */
    public function cmdDeleteLogs() {
        self::print('Clearing Logs, Uptimes, and Sessions...');

        $date = date("Y-m-d H:i:s",strtotime("-1 week"));
        Chickatrice::$app->db()->createQuery()->delete('cockatrice_uptime')->where(['timest', '<', $date])->execute();
        Chickatrice::$app->db()->createQuery()->delete('cockatrice_sessions')->where(['start_time', '<', $date])->execute();

        $date = date("Y-m-d H:i:s",strtotime("-2 month"));
        Chickatrice::$app->db()->createQuery()->delete('cockatrice_log')->where(['log_time', '<', $date])->execute();
    }

    /** Delete replays with no access */
    public function cmdDeleteOrphanReplays() {
        self::print('Deleting orphan replays...');
$SQL = <<<SQL
SET foreign_key_checks = 0;
DELETE FROM `cockatrice_replays` WHERE `duration` = 0 ORDER BY `id_game` ASC OR `id_game` NOT IN (SELECT `id_game` FROM `cockatrice_replays_access`);
SET foreign_key_checks = 1;
SQL;

        $stm = Chickatrice::$app->db()->query($SQL);
        $stm->execute();
        self::print('Deleted ' . $stm->rowCount() . 'rows.');
    }   
    
    /** Delete replays with no access */
    public function cmdDeleteOrphanGames() {
        self::print('Deleting orphan games...');

        $date = date("Y-m-d H:i:s",strtotime("-1 month"));
        $rows = Chickatrice::$app->db()->createQuery()
            ->delete('cockatrice_games')
            ->where(['time_started', '<', $date])
            ->andWhere(['`id` NOT IN (SELECT `id_game` FROM `cockatrice_replays`)'])
            ->execute();

        $rows += Chickatrice::$app->db()->createQuery()
            ->delete('cockatrice_games_players')
            ->andWhere(['`id_game` NOT IN (SELECT `id` FROM `cockatrice_games`)'])
            ->execute();
            
        self::print('Deleted ' . $rows . ' rows.');
    }

    /** Deletes excess replays */
    public function cmdDeleteReplays() {
        self::print('Deleting replay access...');
        $maxcount = intval(Chickatrice::$app->unlinkedAllowedReplays);
$SQL = <<<SQL
SELECT 
	COUNT(`cockatrice_replays_access`.`id_game`) AS C, 
   	`cockatrice_users`.`id` as id,
    `cockatrice_users`.name as name,
    IFNULL(`chickatrice_users`.`max_allowed_replays`, $maxcount) as max_allowed_replays
FROM `cockatrice_replays_access` 
JOIN cockatrice_users ON id_player = cockatrice_users.id
LEFT JOIN chickatrice_users ON `cockatrice_users`.`id` = chickatrice_users.cockatrice_id
GROUP BY id HAVING C > max_allowed_replays;
SQL;
        $deleted = 0;
        $exceedList = Chickatrice::$app->db()->query($SQL)->fetchAll();
        foreach($exceedList as $user) {
            self::print(" - {$user['C']} / {$user['max_allowed_replays']} :: {$user['name']}");
            $diff = $user['C'] - $user['max_allowed_replays'];
            $deleted += ReplayAccess::findByAccount($user['id'])->orderByAsc('id_game')->delete()->limit($diff)->execute();
        }

        self::print("Deleted {$deleted} rows.");
    }

    /** Deletes excess decks */
    public function cmdDeleteDecks() {
        self::print('Deleting excess decks...');
        $maxcount = intval(Chickatrice::$app->unlinkedAllowedDecks);
$SQL = <<<SQL
SELECT 
	COUNT(`cockatrice_decklist_files`.`id`) AS C, 
   	`cockatrice_users`.`id` as id,
    `cockatrice_users`.name as name,
    IFNULL(`\$users`.`max_allowed_decks`, $maxcount) as max_allowed_decks
FROM `cockatrice_decklist_files` 
JOIN `cockatrice_users` ON `id_user` = `cockatrice_users`.`id`
LEFT JOIN `\$users` ON `cockatrice_users`.`id` = `\$users`.`cockatrice_id`
GROUP BY id HAVING C > max_allowed_decks;
SQL;
        $deleted = 0;
        $exceedList = Chickatrice::$app->db()->query($SQL)->fetchAll();
        foreach($exceedList as $user) {
            self::print(" - {$user['C']} / {$user['max_allowed_decks']} :: {$user['name']}");

            $diff = $user['C'] - $user['max_allowed_decks'];
            $deleted += Deck::findByAccount($user['id'])->orderByDesc('id')->delete()->limit($diff)->execute();
        }

        self::print("Deleted {$deleted} rows.");
    }

    /** Deletes all inactive accounts */
    public function cmdDeleteAccounts() {
        self::print('Deleting inactive accounts...');
        $deleted = Account::find()->where(['active', 0])->orderByDesc('id')->delete()->execute();
        self::print("Deleted {$deleted} rows.");
    }

}