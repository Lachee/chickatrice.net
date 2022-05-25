<?php namespace app\models\cockatrice;

use kiss\db\ActiveQuery;
use kiss\db\ActiveRecord;
use kiss\Kiss;

/** 
 * Replay Access with Game joined onto it.
 * All the properties of both Game and Replay.
 */
class ReplayGame extends Game {
    public static function tableName() { return "cockatrice_replays_access"; }

    public $id_player;
    public $replay_name;

    /** @var boolean $do_not_hide */
    public $do_not_hide;

    public function delete() {
        
        //Cannot delete new records
        if ($this->isNewRecord()) {
            $this->addError('Cannot delete new records');
            return false;
        }
        
        //Prevent
        $this->beforeDelete();

        //Prepare the query and execute
        $query = $this->db->createQuery()
                          ->delete(static::tableName())
                          ->where([ 'id_player', $this->id_player ])
                          ->andWhere(['id_game', $this->id ]);
                    
        $result = $query->execute();
        if ($result === false) {
            $this->addError('Failed to execute the save query.');
            return false;
        }

        //Set everythign as dirty
        $this->markNewRecord();

        //Invoke the post event
        $this->afterDelete();
        return true;
    }

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