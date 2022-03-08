<?php namespace app\models\cockatrice;

use kiss\db\ActiveRecord;
use kiss\Kiss;

/** @property string[] players */
class Game extends ActiveRecord {
    public static function tableName() { return "cockatrice_games"; }

    public $id;
    public $room_name;
    public $descr;

    public $creator_name;
    /** @var bool $password is the room password protected */
    public $password;
    public $game_types;
    /** @var int $player_count number of players */
    public $player_count;
    public $time_started;
    public $time_ended;

    private $_players;

    /** @var string[] get all the players in the game */
    public function getPlayers() {
        if ($this->_players != null)
            return $this->_players;

        $query = Kiss::$app->db()->createQuery();
        $results  = $query
            ->select('cockatrice_games_players', ['player_name'])
            ->where(['id_game', $this->id])
            ->ttl(60)
            ->execute();

        $this->_players = [];
        foreach($results as $r) 
            $this->_players[] = $r['player_name'];

        return $this->_players;
    }

    /** @return ActiveQuery|Game[] finds all the games by the player */
    public static function findByPlayer($playerName) {
        return self::find()
                        ->leftJoin('cockatrice_games_players', ['id' => 'id_game'])
                        ->where(['player_name', $playerName]);
    }

    /** @return ActiveQuery|Game[] finds all the games by the player */
    public static function findByAccount(Account $account) {
        return self::findByPlayer($account->name);
    }
}