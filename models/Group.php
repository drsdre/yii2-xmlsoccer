<?php
/**
 * XMLSoccer.com API Yii2 Group Model
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
 * Class Group
 * @package drsdre\yii\xmlsoccer\models
 *
 * @property integer $id
 * @property integer $interface_id
 * @property string $name
 * @property string $season
 * @property integer $league_id
 * @property boolean $is_knockout_stage
 *
 * @property-read League $league
 * @property-read Match[] $getMatches
 */
class Group extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id', 'interface_id', 'league_id'], 'integer'],
            ['name', 'string', 'max' => 255],
            ['season', 'string', 'length' => 4],
            ['is_knockout_stage', 'boolean'],
            ['is_knockout_stage', 'default', 'value' => false],

            [['name', 'season', 'league_id', 'is_knockout_stage'], 'required']
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
     * Get associated matches
     * @return \yii\db\ActiveQuery
     */
    public function getMatches()
    {
        return $this->hasMany(Match::class, ['group_id' => 'id']);
    }
}