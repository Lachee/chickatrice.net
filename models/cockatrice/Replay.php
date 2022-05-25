<?php namespace app\models\cockatrice;

use kiss\db\ActiveQuery;
use kiss\db\ActiveRecord;
use kiss\Kiss;

/** 
 * Replay data from a game
 * @var string[] players 
*/
class Replay extends ActiveRecord {
    public static function tableName() { return "cockatrice_replays"; }

    /** @var int ID of the replay */
    public $id;
    
    /** @var int ID of the game this replay belongs to */
    public $id_game;

    /** @var int duration in seconds */
    public $duration;

    /** @var mixed binary blob of the replay */
    public $replay;
    
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

    /** @return ActiveQuery|ReplayAccess[] finds all the games by the player */
    public static function findByGame($game) {
        $gameId = $game instanceof Game ? $game->id : $game;
        return self::find()->where(['id_game', $gameId ]);
    }
}