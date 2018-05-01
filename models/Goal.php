<?php
/**
 * XMLSoccer.com API Yii2 Goal Model
 *
 * @see http://xmlsoccer.wikia.com/wiki/API_Documentation
 * @see http://promo.lviv.ua
 * @author Volodymyr Chukh <vova@promo.lviv.ua>
 * @author Andre Schuurman <andre.schuurman@gmail.com>
 * @author Simon Karlen <simi.albi@gmail.com>
 * @copyright 2014 Volodymyr Chukh
 * @license MIT License
 */

namespace drsdre\yii\xmlsoccer\models;

use yii\db\ActiveRecord;

/**
 * Class Goal
 * @package drsdre\yii\xmlsoccer\models
 *
 * @property integer $id
 * @property integer $team_id
 * @property integer $player_id
 * @property integer $match_id
 * @property integer $minute
 * @property boolean $owngoal
 * @property boolean $penalty
 *
 * @property-read Player $player
 * @property-read Match $match
 * @property-read Team $team
 */
class Goal extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id', 'team_id', 'player_id', 'match_id'], 'integer'],
            ['minute', 'integer', 'min' => 1, 'max' => 255],

            [['team_id', 'match_id', 'minute'], 'required']
        ];
    }

    /**
     * Get associated player
     * @return \yii\db\ActiveQuery
     */
    public function getPlayer()
    {
        return $this->hasOne(Player::class, ['id' => 'player_id']);
    }

    /**
     * Get associated match
     * @return \yii\db\ActiveQuery
     */
    public function getMatch()
    {
        return $this->hasOne(Match::class, ['id' => 'match_id']);
    }

    /**
     * Get associated team
     * @return \yii\db\ActiveQuery
     */
    public function getTeam()
    {
        return $this->hasOne(Team::class, ['id' => 'team_id']);
    }
}