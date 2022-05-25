<?php namespace app\models\cockatrice;

use kiss\db\ActiveQuery;
use kiss\db\ActiveRecord;
use kiss\Kiss;

/** 
 * A game that has been played.
 * @property string[] players 
 * @property Replay $replay
 * @property mixed $replayData The replay data
 * 
 * @property string $description
*/
class Game extends ActiveRecord {
    public static function tableName() { return "cockatrice_games"; }

    public $id;
    public $room_name;
    protected $descr;

    public $creator_name;
    /** @var bool $password is the room password protected */
    public $password;
    public $game_types;
    /** @var int $player_count number of players */
    public $player_count;
    public $time_started;
    public $time_ended;

    private $_players;

    /** @var string gets the description */
    public function getDescription() {
        return $this->descr;
    }

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

        
    private $_replay;

    /** @return Replay replay */
    public function getReplay() {
        if ($this->_replay != null)
            return $this->_replay;

        return $this->_replay = Replay::findByGame($this->id)->one();
    }

    /** @return mixed Gets the raw replay data */
    public function getReplayData() {
        return $this->getReplay()->replay;
    }

    /** @return ActiveQuery|Game[] finds all the games by the player */
    public static function findByPlayer($playerName) {
        return static::find()
                        ->leftJoin('cockatrice_games_players', ['id' => 'id_game'])
                        ->where(['player_name', $playerName]);
    }

    /** @return ActiveQuery|Game[] finds all the games by the player */
    public static function findByAccount(Account $account) {
        return static::findByPlayer($account->name);
    }
}