<?php
/**
 * XMLSoccer.com API Yii2 db initialisation migration
 *
 * @see http://xmlsoccer.wikia.com/wiki/API_Documentation
 * @see http://promo.lviv.ua
 * @author Volodymyr Chukh <vova@promo.lviv.ua>
 * @author Andre Schuurman <andre.schuurman@gmail.com>
 * @author Simon Karlen <simi.albi@gmail.com>
 * @copyright 2014 Volodymyr Chukh
 * @license MIT License
 */

namespace drsdre\yii\xmlsoccer\migrations;

use yii\db\Migration;

/**
 * Initialize soccer db
 * @package drsdre\yii\xmlsoccer\migrations
 */
class m180312_095716_init extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%goal}}', [
            'id' => $this->primaryKey(10)->unsigned(),
            'team_id' => $this->integer(10)->unsigned()->notNull(),
            'player_id' => $this->integer(10)->unsigned()->null()->defaultValue(null),
            'match_id' => $this->integer(10)->unsigned()->notNull(),
            'minute' => $this->tinyInteger(3)->unsigned()->notNull(),
            'owngoal' => $this->boolean()->notNull()->defaultValue(false),
            'penalty' => $this->boolean()->notNull()->defaultValue(false)
        ]);
        $this->createTable('{{%group}}', [
            'id' => $this->primaryKey(10)->unsigned(),
            'interface_id' => $this->integer(10)->unsigned()->null()->defaultValue(null)->comment('XMLSOCCER id'),
            'name' => $this->string(255)->notNull(),
            'season' => $this->char(4)->notNull(),
            'league_id' => $this->integer(10)->unsigned()->notNull(),
            'is_knockout_stage' => $this->boolean()->notNull()->defaultValue(false)
        ]);
        $this->createTable('{{%league}}', [
            'id' => $this->primaryKey(10)->unsigned(),
            'interface_id' => $this->integer(10)->unsigned()->null()->defaultValue(null)->comment('XMLSOCCER id'),
            'name' => $this->string(255)->notNull(),
            'country' => $this->string(255)->notNull()->defaultValue('International'),
            'historical_data' => $this->tinyInteger(1)->notNull()->defaultValue(0)->comment('0=No,1=Yes,2=Partial'),
            'fixtures' => $this->boolean()->notNull()->defaultValue(false),
            'livescore' => $this->boolean()->notNull()->defaultValue(false),
            'number_of_matches' => $this->integer(10)->unsigned()->notNull(),
            'latest_match' => $this->integer(10)->unsigned()->notNull(),
            'is_cup' => $this->boolean()->notNull()->defaultValue(false)
        ]);
        $this->createTable('{{%match}}', [
            'id' => $this->primaryKey(10)->unsigned(),
            'interface_id' => $this->integer(10)->unsigned()->null()->defaultValue(null)->comment('XMLSOCCER id'),
            'date' => $this->integer(10)->unsigned()->notNull(),
            'league_id' => $this->integer(10)->unsigned()->notNull(),
            'round' => $this->tinyInteger(3)->unsigned()->null()->defaultValue(null),
            'home_team_id' => $this->integer(10)->unsigned()->notNull(),
            'away_team_id' => $this->integer(10)->unsigned()->notNull(),
            'group_id' => $this->integer(10)->unsigned()->null()->defaultValue(null),
            'location' => $this->string(255)->null()->defaultValue(null)
        ]);
        $this->createTable('{{%player}}', [
            'id' => $this->primaryKey(10)->unsigned(),
            'interface_id' => $this->integer(10)->unsigned()->null()->defaultValue(null)->comment('XMLSOCCER id'),
            'name' => $this->string(255)->notNull(),
            'height' => $this->decimal(10, 2)->null()->defaultValue(null),
            'weight' => $this->decimal(10, 2)->null()->defaultValue(null),
            'nationality' => $this->string(255)->null()->defaultValue(null),
            'position' => $this->string(255)->null()->defaultValue(null),
            'team_id' => $this->integer(10)->unsigned()->null()->defaultValue(null),
            'player_number' => $this->tinyInteger(3)->unsigned()->notNull(),
            'loan_to' => $this->integer(10)
                ->unsigned()
                ->null()
                ->defaultValue(null)
                ->comment('loaned to team with id'),
            'date_of_birth' => $this->integer(10)->unsigned()->null()->defaultValue(null),
            'date_of_signing' => $this->integer(10)->unsigned()->null()->defaultValue(null),
            'signing' => $this->string(50)
                ->null()
                ->defaultValue(null)
                ->comment('amount of signing (including currency)')
        ]);
        $this->createTable('{{%team}}', [
            'id' => $this->primaryKey(10)->unsigned(),
            'interface_id' => $this->integer(10)->unsigned()->null()->defaultValue(null)->comment('XMLSOCCER id'),
            'name' => $this->string(255)->notNull(),
            'country' => $this->string(255)->notNull(),
            'stadium' => $this->string(255)->null()->defaultValue(null),
            'home_page_url' => $this->string(255)->null()->defaultValue(null),
            'wiki_link' => $this->string(255)->null()->defaultValue(null),
            'coach' => $this->string(255)->null()->defaultValue(null)
        ]);

        $this->addForeignKey(
            '{{%goal_ibfk_1}}',
            '{{%goal}}',
            'match_id',
            '{{%match}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
        $this->addForeignKey(
            '{{%goal_ibfk_2}}',
            '{{%goal}}',
            'team_id',
            '{{%team}}',
            'id',
            'SET NULL',
            'CASCADE'
        );
        $this->addForeignKey(
            '{{%goal_ibfk_3}}',
            '{{%goal}}',
            'player_id',
            '{{%player}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
        $this->addForeignKey(
            '{{%group_ibfk_1}}',
            '{{%group}}',
            'league_id',
            '{{%league}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
        $this->addForeignKey(
            '{{%match_ibfk_1}}',
            '{{%match}}',
            'away_team_id',
            '{{%team}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
        $this->addForeignKey(
            '{{%match_ibfk_2}}',
            '{{%match}}',
            'home_team_id',
            '{{%team}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
        $this->addForeignKey(
            '{{%match_ibfk_3}}',
            '{{%match}}',
            'group_id',
            '{{%group}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
        $this->addForeignKey(
            '{{%match_ibfk_4}}',
            '{{%match}}',
            'league_id',
            '{{%league}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
        $this->addForeignKey(
            '{{%player_ibfk_1}}',
            '{{%player}}',
            'loan_to',
            '{{%team}}',
            'id',
            'SET NULL',
            'CASCADE'
        );
        $this->addForeignKey(
            '{{%player_ibfk_2}}',
            '{{%player}}',
            'team_id',
            '{{%team}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropForeignKey('{{%player_ibfk_1}}', '{{%player}}');
        $this->dropForeignKey('{{%player_ibfk_2}}', '{{%player}}');
        $this->dropForeignKey('{{%match_ibfk_1}}', '{{%match}}');
        $this->dropForeignKey('{{%match_ibfk_2}}', '{{%match}}');
        $this->dropForeignKey('{{%match_ibfk_3}}', '{{%match}}');
        $this->dropForeignKey('{{%match_ibfk_4}}', '{{%match}}');
        $this->dropForeignKey('{{%group_ibfk_1}}', '{{%group}}');
        $this->dropForeignKey('{{%goal_ibfk_1}}', '{{%goal}}');
        $this->dropForeignKey('{{%goal_ibfk_2}}', '{{%goal}}');

        $this->dropTable('{{%team}}');
        $this->dropTable('{{%player}}');
        $this->dropTable('{{%match}}');
        $this->dropTable('{{%league}}');
        $this->dropTable('{{%group}}');
        $this->dropTable('{{%goal}}');
    }
}