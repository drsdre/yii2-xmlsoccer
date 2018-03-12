<?php
/**
 * XMLSoccer.com API Yii2 Player Model
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
 * Class Player
 * @package drsdre\yii\xmlsoccer\models
 *
 * @property integer $id
 * @property integer $interface_id
 * @property string $name
 * @property double $height
 * @property double $weight
 * @property string $nationality
 * @property string $position
 * @property integer $team_id
 * @property integer $player_number
 * @property integer $loan_to
 * @property string $date_of_birth
 * @property string $date_of_signing
 * @property string $signing
 *
 * @property-read Team $team
 * @property-read Team $loanTeam
 */
class Player extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id', 'interface_id', 'team_id', 'player_number', 'loan_to'], 'integer'],
            [['height', 'weight'], 'number'],
            [['name', 'nationality'], 'string', 'max' => 255],
            [['position', 'signing'], 'string', 'max' => 50],
            [['date_of_birth', 'date_of_signing'], 'date'],

            [['name', 'player_number'], 'required']
        ];
    }

    /**
     * Get associated team
     * @return \yii\db\ActiveQuery
     */
    public function getTeam()
    {
        return $this->hasOne(Team::class, ['id' => 'team_id']);
    }

    /**
     * Get associated team player loaned to
     * @return \yii\db\ActiveQuery
     */
    public function getLoanTeam()
    {
        return $this->hasOne(Team::class, ['id' => 'loan_to']);
    }

    /**
     * {@inheritdoc}
     */
    public function beforeSave($insert)
    {
        $this->date_of_birth = (empty($this->date_of_birth)) ? null : strtotime($this->date_of_birth);
        $this->date_of_signing = (empty($this->date_of_signing)) ? null : strtotime($this->date_of_signing);
        return parent::beforeSave($insert);
    }

    /**
     * {@inheritdoc}
     * @throws \yii\base\InvalidConfigException
     */
    public function afterFind()
    {
        if (\Yii::$app->has('formatter')) {
            if (!empty($this->date_of_birth)) {
                $this->date_of_birth = \Yii::$app->formatter->asDate($this->date_of_birth);
            }
            if (!empty($this->date_of_signing)) {
                $this->date_of_signing = \Yii::$app->formatter->asDate($this->date_of_signing);
            }
        }
        return parent::afterFind();
    }
}