<?php
/**
 * XMLSoccer.com API Yii2 Team Model
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
use yii\helpers\ArrayHelper;

/**
 * Class Team
 * @package drsdre\yii\xmlsoccer\models
 *
 * @property integer $id
 * @property integer $interface_id
 * @property string $name
 * @property string $country
 * @property string $stadium
 * @property string $home_page_url
 * @property string $wiki_link
 * @property string $coach
 *
 * @property-read Player[] $players
 * @property-read Player[] $loans
 * @property-read Match[] $homeMatches Matches as home team
 * @property-read Match[] $awayMatches Matches as away team
 * @property-read Match[] $matches All matches
 */
class Team extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id', 'interface_id'], 'integer'],
            [['name', 'country', 'stadium', 'coach'], 'string'],
            [['home_page_url', 'wiki_link'], 'url', 'enableIDN' => true],

            ['country', 'default', 'value' => 'International'],

            [['name', 'country'], 'required']
        ];
    }

    /**
     * Get associated players
     * @return \yii\db\ActiveQuery
     */
    public function getPlayers()
    {
        return $this->hasMany(Player::class, ['team_id' => 'id']);
    }

    /**
     * Get associated loans
     * @return \yii\db\ActiveQuery
     */
    public function getLoans()
    {
        return $this->hasMany(Player::class, ['loan_to' => 'id']);
    }

    /**
     * Get associated matches as home team
     * @return \yii\db\ActiveQuery
     */
    public function getHomeMatches()
    {
        return $this->hasMany(Match::class, ['home_team_id' => 'id']);
    }

    /**
     * Get associated matches as away team
     * @return \yii\db\ActiveQuery
     */
    public function getAwayMatches()
    {
        return $this->hasMany(Match::class, ['away_team_id' => 'id']);
    }

    /**
     * Get all matches
     * @return array
     */
    public function getMatches()
    {
        $matches = ArrayHelper::merge($this->homeMatches, $this->awayMatches);
        ArrayHelper::multisort($matches, 'date', SORT_ASC);
        return $matches;
    }
}