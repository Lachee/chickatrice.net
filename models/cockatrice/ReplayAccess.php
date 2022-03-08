<?php namespace app\models\cockatrice;

use kiss\db\ActiveQuery;
use kiss\db\ActiveRecord;
use kiss\Kiss;

/** 
 * @property Replay $replay
 * @property Game $game
 */
class ReplayAccess extends ActiveRecord {
    public static function tableName() { return "cockatrice_replays_access"; }

    public $id_game;
    public $id_player;
    public $replay_name;

    /** @var boolean $do_not_hide */
    public $do_not_hide;
    
    private $_replay;
    public function getReplay() {
        if ($this->_replay != null)
            return $this->_replay;

        return $this->_replay = Replay::findByGame($this->id_game)->one();
    }

    private $_game;
    public function getGame() {
        if ($this->_game != null) 
            return $this->_game;
        return $this->_game = Game::findByKey($this->id_game)->one();
    }

    /** @return ActiveQuery|ReplayAccess[] finds all the games by the player */
    public static function findByAccount(Account $account) {
        return self::find()->where(['id_player', $account->id]);
    }

    /** @return ActiveQuery|ReplayAccess[] finds all the games by the player */
    public static function findByGame($game) {
        $gameId = $game instanceof Game ? $game->id : $game;
        return self::find()->where(['id_game', $gameId ]);
    }
}