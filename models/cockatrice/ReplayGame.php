<?php namespace app\models\cockatrice;

use kiss\db\ActiveQuery;
use kiss\db\ActiveRecord;
use kiss\Kiss;

/** 
 */
class ReplayGame extends Game {
    public static function tableName() { return "cockatrice_replays_access"; }
    
    public $id_player;
    public $replay_name;

    /** @var boolean $do_not_hide */
    public $do_not_hide;

    /** @inheritdoc */
    public static function find() {
        return parent::find()->leftJoin('cockatrice_games', [ 'id_game' => 'id' ]);
    }

    /** @return ActiveQuery|Game[] finds all the games by the player */
    public static function findByPlayer($playerName) {
        return static::find()
                        ->leftJoin('cockatrice_games_players', ['id_game' => 'id_game'])
                        ->where(['player_name', $playerName]);
    }

    /** @return ActiveQuery|ReplayAccess[] finds all the games by the player */
    public static function findByAccount(Account $account) {
        return static::find()->where(['id_player', $account->id]);
    }
}