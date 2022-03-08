<?php namespace app\models\cockatrice;

use kiss\db\ActiveQuery;
use kiss\db\ActiveRecord;
use kiss\Kiss;

/** @var string[] players */
class Replay extends ActiveRecord {
    public static function tableName() { return "cockatrice_games"; }

    public $id;
    public $id_game;
    public $duration;
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