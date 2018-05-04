<?php
/**
 * XMLSoccer.com API Yii2 Match Model
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
 * Class Match
 * @package drsdre\yii\xmlsoccer\models
 *
 * @property integer $id
 * @property integer $interface_id
 * @property string $date
 * @property integer $league_id
 * @property integer $round
 * @property integer $home_team_id
 * @property integer $away_team_id
 * @property integer $group_id
 * @property string $location
 *
 * @property-read League $league
 * @property-read Team $homeTeam
 * @property-read Team $awayTeam
 * @property-read Group $group
 */
class Match extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id', 'interface_id', 'league_id', 'home_team_id', 'away_team_id', 'group_id', 'round'], 'integer'],
            ['date', 'datetime', 'format' => 'yyyy-MM-dd\'T\'HH:mm:ssxxx'],
            ['location', 'string', 'max' => 255],

            [['date', 'league_id', 'home_team_id', 'away_team_id'], 'required']
        ];
    }

    /**
     * Get associated league
     * @return \yii\db\ActiveQuery
     */
    public function getLeague()
    {
        return $this->hasOne(League::class, ['id' => 'league_id']);
    }

    /**
     * Get associated home team
     * @return \yii\db\ActiveQuery
     */
    public function getHomeTeam()
    {
        return $this->hasOne(Team::class, ['id' => 'home_team_id']);
    }

    /**
     * Get associated away team
     * @return \yii\db\ActiveQuery
     */
    public function getAwayTeam()
    {
        return $this->hasOne(Team::class, ['id' => 'away_team_id']);
    }

    /**
     * Get associated group
     * @return \yii\db\ActiveQuery
     */
    public function getGroup()
    {
        return $this->hasOne(Group::class, ['id' => 'group_id']);
    }

    /**
     * {@inheritdoc}
     */
    public function beforeSave($insert)
    {
        $this->date = strtotime($this->date);
        return parent::beforeSave($insert);
    }

    /**
     * {@inheritdoc}
     * @throws \yii\base\InvalidConfigException
     */
    public function afterFind()
    {
        if (\Yii::$app->has('formatter')) {
            $this->date = \Yii::$app->formatter->asDatetime($this->date, 'yyyy-MM-dd\'T\'HH:mm:ssxxx');
        }
        return parent::afterFind();
    }
}