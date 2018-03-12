<?php
/**
 * XMLSoccer.com API Yii2 League Model
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
 * Class League
 * @package drsdre\yii\xmlsoccer\models
 *
 * @property integer $id
 * @property integer $interface_id
 * @property string $name
 * @property string $country
 * @property integer $historical_data
 * @property boolean $fixtures
 * @property boolean $livescore
 * @property integer $number_of_matches
 * @property string $latest_match
 * @property boolean $is_cup
 *
 * @property-read Match[] $matches
 * @property-read Group[] $groups
 */
class League extends ActiveRecord
{
    const HISTORICAL_DATA_NO = 0;
    const HISTORICAL_DATA_YES = 1;
    const HISTORICAL_DATA_PARTIAL = 2;

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id', 'interface_id', 'number_of_matches'], 'integer'],
            [['name', 'country'], 'string', 'max' => 255],
            [['fixtures', 'livescore', 'is_cup'], 'boolean'],
            [['latest_match'], 'datetime'],
            [
                'historical_data',
                'in',
                'range' => [self::HISTORICAL_DATA_NO, self::HISTORICAL_DATA_YES, self::HISTORICAL_DATA_PARTIAL]
            ],

            ['country', 'default', 'value' => 'International'],
            [['fixtures', 'livescore', 'is_cup'], 'default', 'value' => false],

            [
                [
                    'name',
                    'country',
                    'historical_data',
                    'fixtures',
                    'livescore',
                    'number_of_matches',
                    'latest_match',
                    'is_cup'
                ],
                'required'
            ]
        ];
    }

    /**
     * Get associated matches
     * @return \yii\db\ActiveQuery
     */
    public function getMatches()
    {
        return $this->hasMany(Match::class, ['league_id' => 'id']);
    }

    /**
     * Get associated groups
     * @return \yii\db\ActiveQuery
     */
    public function getGroups()
    {
        return $this->hasMany(Group::class, ['league_id' => 'id']);
    }

    /**
     * {@inheritdoc}
     */
    public function beforeSave($insert)
    {
        $this->latest_match = strtotime($this->latest_match);
        return parent::beforeSave($insert);
    }

    /**
     * {@inheritdoc}
     * @throws \yii\base\InvalidConfigException
     */
    public function afterFind()
    {
        if (\Yii::$app->has('formatter')) {
            $this->latest_match = \Yii::$app->formatter->asDatetime($this->latest_match);
        }
        return parent::afterFind();
    }
}