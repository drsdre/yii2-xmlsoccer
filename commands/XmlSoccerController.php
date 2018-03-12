<?php
/**
 * XMLSoccer.com API Yii2 console controller
 *
 * @see http://xmlsoccer.wikia.com/wiki/API_Documentation
 * @see http://promo.lviv.ua
 * @author Volodymyr Chukh <vova@promo.lviv.ua>
 * @author Andre Schuurman <andre.schuurman@gmail.com>
 * @author Simon Karlen <simi.albi@gmail.com>
 * @copyright 2014 Volodymyr Chukh
 * @license MIT License
 */

namespace drsdre\yii\xmlsoccer\commands;

use drsdre\yii\xmlsoccer\Client;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\console\widgets\Table;
use yii\helpers\ArrayHelper;
use yii\helpers\Console;
use Yii;

/**
 * This class allows to use some commands against XMLSoccer application programming interface.
 * It's especially used to import data from XMLSoccer API.
 */
class XmlSoccerController extends Controller
{
    /**
     * @var string API Key to use
     */
    public $apiKey;

    /**
     * {@inheritdoc}
     */
    public $defaultAction = 'show-leagues';

    /**
     * @var string class name representing a Goal
     */
    public $goalClass = '\drsdre\yii\xmlsoccer\models\Goal';
    /**
     * @var string class name representing a Group
     */
    public $groupClass = '\drsdre\yii\xmlsoccer\models\Group';
    /**
     * @var string class name representing a League
     */
    public $leagueClass = '\drsdre\yii\xmlsoccer\models\League';
    /**
     * @var string class name representing a Match
     */
    public $matchClass = '\drsdre\yii\xmlsoccer\models\Match';
    /**
     * @var string class name representing a Player
     */
    public $playerClass = '\drsdre\yii\xmlsoccer\models\Player';
    /**
     * @var string class name representing a Team
     */
    public $teamClass = '\drsdre\yii\xmlsoccer\models\Team';

    /**
     * {@inheritdoc}
     */
    public function options($actionID)
    {
        $options = parent::options($actionID);
        if ($actionID !== 'show-leagues') {
            $options[] = 'apiKey';
        }
        return $options;
    }

    /**
     * Import all leagues from XMLSoccer interface
     * @return integer Exit code
     * @throws \yii\base\InvalidConfigException
     */
    public function actionImportLeagues()
    {
        $client = new Client([
            'apiKey' => $this->apiKey
        ]);

        $leagues = $client->getAllLeagues();
        $count = 0;
        foreach ($leagues as $league) {
            $dbLeague = Yii::createObject([
                'class' => $this->leagueClass,
                'interface_id' => ArrayHelper::getValue($league, 'Id'),
                'name' => ArrayHelper::getValue($league, 'Name'),
                'country' => ArrayHelper::getValue($league, 'Country'),
                'historical_data' => constant(
                    '\drsdre\yii\xmlsoccer\models\League::HISTORICAL_DATA_' .
                    strtoupper(ArrayHelper::getValue($league, 'Historical_Data', 'no'))
                ),
                'fixtures' => filter_var(
                    strtolower(ArrayHelper::getValue($league, 'Fixtures', 'no')),
                    FILTER_VALIDATE_BOOLEAN
                ),
                'livescore' => filter_var(
                    strtolower(ArrayHelper::getValue($league, 'Livescore', 'no')),
                    FILTER_VALIDATE_BOOLEAN
                ),
                'number_of_matches' => ArrayHelper::getValue($league, 'NumberOfMatches', 0),
                'latest_match' => ArrayHelper::getValue($league, 'LatestMatch'),
                'is_cup' => filter_var(
                    strtolower(ArrayHelper::getValue($league, 'IsCup', 'no')),
                    FILTER_VALIDATE_BOOLEAN
                )
            ]);
            /* @var $dbLeague \drsdre\yii\xmlsoccer\models\League */

            if (!$dbLeague->save()) {
                $this->stderr("Failed to import league '{$dbLeague->name}': ", Console::FG_RED);
                $this->stderr("\n");
                foreach ($dbLeague->errors as $attribute => $errors) {
                    foreach ($errors as $error) {
                        $this->stderr("$attribute: $error", Console::BG_YELLOW, Console::FG_BLACK);
                        $this->stderr("\n");
                    }
                }
                $this->stderr("\n");
            } else {
                $count++;
                $this->stdout("League '{$dbLeague->name}' inserted\n");
            }
        }

        $this->stdout("\n");
        $this->stdout("$count leagues imported", Console::FG_GREEN);
        $this->stdout("\n");

        return ExitCode::OK;
    }

    /**
     * Show all leagues
     *
     * @return integer Exit code
     * @throws \Exception
     */
    public function actionShowLeagues()
    {
        $leagues = call_user_func([$this->leagueClass, 'find']);
        /* @var $leagues \yii\db\ActiveQuery */

        if (!$leagues->count('id')) {
            $this->stdout("No leagues found. Import by ");
            $this->stdout("{$this->id}/import-leagues", Console::BOLD);
            $this->stdout("\n");
            return ExitCode::OK;
        }

        $first = $leagues->one();
        $headers = [];
        $rows = [];
        $attributes = [];

        foreach ($first->toArray() as $attribute => $value) {
            $attributes[] = $attribute;
            $headers[] = $first->generateAttributeLabel($attribute);
        }
        foreach ($leagues->all() as $league) {
            $rows[] = array_values($league->toArray($attributes));
        }

        echo Table::widget([
            'headers' => $headers,
            'rows' => $rows
        ]);

        return ExitCode::OK;
    }

    /**
     * Create specific league by id. This method imports all league relevant data from interface to db.
     * If league id is not known, please execute
     * ```sh
     * $ ./yii xml-soccer/show-leagues
     * ```
     *
     * @param integer $league_id League id to import all data from
     * @param string|null $seasonDateString Season date string to import data from. Defaults to current season.
     * @return int Exit code
     * @throws \yii\base\InvalidConfigException
     */
    public function actionCreateLeague($league_id, $seasonDateString = null)
    {
        $league = call_user_func([$this->leagueClass, 'findOne'], $league_id);
        /* @var $league \drsdre\yii\xmlsoccer\models\League */
        if (empty($seasonDateString)) {
            $year = intval(date('y'));
            $seasonDateString = sprintf('%02s%02s', $year - 1, $year);
        }

        if (!$league) {
            $this->stderr("League with id '$league_id' not found", Console::FG_RED);
            $this->stderr("\n");

            return ExitCode::DATAERR;
        }

        $client = new Client([
            'apiKey' => $this->apiKey
        ]);

        $teams = $client->getAllTeamsByLeagueAndSeason($league->interface_id, $seasonDateString);
        $matches = $client->getFixturesByLeagueAndSeason($league->interface_id, $seasonDateString);
        if ($league->is_cup) {
            $groups = $client->getAllGroupsByLeagueAndSeason($league->interface_id, $seasonDateString);

            foreach ($groups as $group) {
                $dbGroup = Yii::createObject([
                    'class' => $this->groupClass,
                    'interface_id' => ArrayHelper::getValue($group, 'Id'),
                    'name' => ArrayHelper::getValue($group, 'Name'),
                    'season' => ArrayHelper::getValue($group, 'Season'),
                    'league_id' => $league->id
                ]);
                /* @var $dbGroup \drsdre\yii\xmlsoccer\models\Group */

                if (!$dbGroup->save()) {
                    $this->stderr("Failed to save group '{$dbGroup->name}': ", Console::FG_RED);
                    $this->stderr("\n");
                    foreach ($dbGroup->errors as $attribute => $errors) {
                        foreach ($errors as $error) {
                            $this->stderr("$attribute: $error", Console::BG_YELLOW, Console::FG_BLACK);
                            $this->stderr("\n");
                        }
                    }
                    $this->stderr("\n");

                    return ExitCode::IOERR;
                } else {
                    $this->stdout("Group '{$dbGroup->name}' saved", Console::FG_GREEN);
                    $this->stdout("\n");
                }
            }
        }

        $players = [];
        foreach ($teams as $team) {
            $dbTeam = Yii::createObject([
                'class' => $this->teamClass,
                'interface_id' => ArrayHelper::getValue($team, 'Team_Id'),
                'name' => ArrayHelper::getValue($team, 'Name'),
                'country' => ArrayHelper::getValue($team, 'Country'),
                'stadium' => ArrayHelper::getValue($team, 'Stadium'),
                'home_page_url' => ArrayHelper::getValue($team, 'HomePageURL'),
                'wiki_link' => ArrayHelper::getValue($team, 'WIKILink'),
                'coach' => ArrayHelper::getValue($team, 'Coach') ?: ArrayHelper::getValue($team, 'Manager')
            ]);
            /* @var $dbTeam \drsdre\yii\xmlsoccer\models\Team */

            if (!$dbTeam->save()) {
                $this->stderr("Failed to save team '{$dbTeam->name}': ", Console::FG_RED);
                $this->stderr("\n");
                foreach ($dbTeam->errors as $attribute => $errors) {
                    foreach ($errors as $error) {
                        $this->stderr("$attribute: $error", Console::BG_YELLOW, Console::FG_BLACK);
                        $this->stderr("\n");
                    }
                }
                $this->stderr("\n");

                return ExitCode::IOERR;
            } else {
                $this->stdout("Team '{$dbTeam->name}' saved", Console::FG_GREEN);
                $this->stdout("\n");

                $players = array_merge($players, $client->getPlayersByTeam(ArrayHelper::getValue($team, 'Team_Id')));
            }
        }

        $groups = call_user_func([
            $this->groupClass,
            'find'
        ])->where(['league_id' => $league->id])->indexBy('interface_id')->all();
        $teams = call_user_func([$this->teamClass, 'find'])->indexBy('interface_id')->all();
        /* @var $groups \drsdre\yii\xmlsoccer\models\Group[] */
        /* @var $teams \drsdre\yii\xmlsoccer\models\Team[] */

        foreach ($players as $player) {
            $dbPlayer = Yii::createObject([
                'class' => $this->playerClass,
                'interface_id' => ArrayHelper::getValue($player, 'Id'),
                'name' => ArrayHelper::getValue($player, 'Name'),
                'nationality' => ArrayHelper::getValue($player, 'Nationality'),
                'position' => ArrayHelper::getValue($player, 'Position'),
                'team_id' => ArrayHelper::getValue($teams, [ArrayHelper::getValue($player, 'Team_Id'), 'id']),
                'loan_to' => ArrayHelper::getValue($teams, [ArrayHelper::getValue($player, 'LoanTo'), 'id']),
                'player_number' => intval(ArrayHelper::getValue($player, 'PlayerNumber')),
                'date_of_birth' => ArrayHelper::getValue($player, 'DateOfBirth'),
                'date_of_signing' => ArrayHelper::getValue($player, 'DateOfSigning'),
                'signing' => html_entity_decode(ArrayHelper::getValue($player, 'Signing'), ENT_COMPAT | ENT_HTML5,
                    'utf-8')
            ]);
            /* @var $dbPlayer \drsdre\yii\xmlsoccer\models\Player */

            if (!$dbPlayer->save()) {
                $this->stderr("Failed to save player '{$dbPlayer->name}': ", Console::FG_RED);
                $this->stderr("\n");
                foreach ($dbPlayer->errors as $attribute => $errors) {
                    foreach ($errors as $error) {
                        $this->stderr("$attribute: $error", Console::BG_YELLOW, Console::FG_BLACK);
                        $this->stderr("\n");
                    }
                }
                $this->stderr("\n");

                return ExitCode::IOERR;
            } else {
                $this->stdout("Player '{$dbPlayer->name}' saved", Console::FG_GREEN);
                $this->stdout("\n");
            }
        }

        foreach ($matches as $match) {
            $homeTeam = ArrayHelper::getValue($teams, ArrayHelper::getValue($match, 'HomeTeam_Id'));
            $awayTeam = ArrayHelper::getValue($teams, ArrayHelper::getValue($match, 'AwayTeam_Id'));
            /* @var $homeTeam \drsdre\yii\xmlsoccer\models\Team */
            /* @var $awayTeam \drsdre\yii\xmlsoccer\models\Team */
            $dbMatch = Yii::createObject([
                'class' => $this->matchClass,
                'interface_id' => ArrayHelper::getValue($match, 'Id'),
                'date' => ArrayHelper::getValue($match, 'Date'),
                'league_id' => $league->id,
                'round' => ArrayHelper::getValue($match, 'Round'),
                'home_team_id' => $homeTeam->id,
                'away_team_id' => $awayTeam->id,
                'group_id' => ArrayHelper::getValue($groups, ArrayHelper::getValue($match, 'Group_Id', -100)),
                'location' => ArrayHelper::getValue($match, 'Location')
            ]);
            /* @var $dbMatch \drsdre\yii\xmlsoccer\models\Match */

            if (!$dbMatch->save()) {
                $this->stderr("Failed to save match '{$dbMatch->location}-{$dbMatch->date}': ", Console::FG_RED);
                $this->stderr("\n");
                foreach ($dbMatch->errors as $attribute => $errors) {
                    foreach ($errors as $error) {
                        $this->stderr("$attribute: $error", Console::BG_YELLOW, Console::FG_BLACK);
                        $this->stderr("\n");
                    }
                }
                $this->stderr("\n");

                return ExitCode::IOERR;
            } else {
                $this->stdout("Match '{$dbMatch->location}-{$dbMatch->date}' saved", Console::FG_GREEN);
                $this->stdout("\n");

                if (ArrayHelper::keyExists('HomeGoalDetails', $match)) {
                    $homeGoals = explode(';', ArrayHelper::getValue($match, 'HomeGoalDetails', ''));
                    $homePlayers = $homeTeam->getPlayers()->indexBy('name')->all();
                    $awayGoals = explode(';', ArrayHelper::getValue($match, 'AwayGoalDetails', ''));
                    $awayPlayers = $awayTeam->getPlayers()->indexBy('name')->all();

                    foreach ($homeGoals as $homeGoal) {
                        @list($minute, $player) = @array_map('trim', @explode(':', $homeGoal));
                        $isPenalty = substr($player, 0, 7) === 'penalty';
                        if ($isPenalty) {
                            $player = ltrim(substr($player, 8));
                        }
                        $isOwn = substr($player, 0, 3) === 'Own';
                        if ($isOwn) {
                            $player = ltrim(substr($player, 4));
                        }
                        $minute = rtrim($minute, '\'');

                        $dbGoal = Yii::createObject([
                            'class' => $this->goalClass,
                            'match_id' => $dbMatch->id,
                            'minute' => $minute,
                            'player_id' => ArrayHelper::getValue(
                                ($isOwn ? $awayPlayers : $homePlayers),
                                [$player, 'id']
                            ),
                            'owngoal' => $isOwn,
                            'penalty' => $isPenalty
                        ]);
                        /* @var $dbGoal \drsdre\yii\xmlsoccer\models\Goal */
                        if (!$dbGoal->save()) {
                            $this->stderr("Failed to save goal '$minute': ", Console::FG_RED);
                            $this->stderr("\n");
                            foreach ($dbGoal->errors as $attribute => $errors) {
                                foreach ($errors as $error) {
                                    $this->stderr("$attribute: $error", Console::BG_YELLOW, Console::FG_BLACK);
                                    $this->stderr("\n");
                                }
                            }
                            $this->stderr("\n");
                        } else {
                            $this->stdout("Goal '$minute' saved", Console::FG_GREEN);
                            $this->stdout("\n");
                        }
                    }
                    foreach ($awayGoals as $awayGoal) {
                        @list($minute, $player) = @array_map('trim', @explode(':', $awayGoal));
                        $isPenalty = substr($player, 0, 7) === 'penalty';
                        if ($isPenalty) {
                            $player = ltrim(substr($player, 8));
                        }
                        $isOwn = substr($player, 0, 3) === 'Own';
                        if ($isOwn) {
                            $player = ltrim(substr($player, 4));
                        }
                        $minute = rtrim($minute, '\'');

                        $dbGoal = Yii::createObject([
                            'class' => $this->goalClass,
                            'match_id' => $dbMatch->id,
                            'minute' => $minute,
                            'player_id' => ArrayHelper::getValue(
                                ($isOwn ? $homePlayers : $awayPlayers),
                                [$player, 'id']
                            ),
                            'owngoal' => $isOwn,
                            'penalty' => $isPenalty
                        ]);
                        /* @var $dbGoal \drsdre\yii\xmlsoccer\models\Goal */
                        if (!$dbGoal->save()) {
                            $this->stderr("Failed to save goal '$minute': ", Console::FG_RED);
                            $this->stderr("\n");
                            foreach ($dbGoal->errors as $attribute => $errors) {
                                foreach ($errors as $error) {
                                    $this->stderr("$attribute: $error", Console::BG_YELLOW, Console::FG_BLACK);
                                    $this->stderr("\n");
                                }
                            }
                            $this->stderr("\n");
                        } else {
                            $this->stdout("Goal '$minute' saved", Console::FG_GREEN);
                            $this->stdout("\n");
                        }
                    }
                }
            }
        }

        return ExitCode::OK;
    }

    /**
     * Update Scores from LiveScore
     *
     * @return integer Exit code
     * @throws \yii\base\InvalidConfigException
     */
    public function actionUpdateScore()
    {
        $client = new Client([
            'apiKey' => $this->apiKey
        ]);

        $liveScoreData = $client->getLiveScore();
        foreach ($liveScoreData as $match) {
            $dbMatch = call_user_func(
                [$this->matchClass, 'findOne'],
                ['interface_id' => ArrayHelper::getValue($match, 'Id')]
            );
            /* @var $dbMatch \drsdre\yii\xmlsoccer\models\Match */
            if (!$dbMatch) {
                continue;
            }

            $homeTeam = call_user_func(
                [$this->teamClass, 'findOne'],
                ['interface_id' => ArrayHelper::getValue($match, 'HomeTeam_Id')]
            );
            $awayTeam = call_user_func(
                [$this->teamClass, 'findOne'],
                ['interface_id' => ArrayHelper::getValue($match, 'AwayTeam_Id')]
            );
            /* @var $homeTeam \drsdre\yii\xmlsoccer\models\Team */
            /* @var $awayTeam \drsdre\yii\xmlsoccer\models\Team */

            if (ArrayHelper::keyExists('HomeGoalDetails', $match)) {
                $homeGoals = explode(';', ArrayHelper::getValue($match, 'HomeGoalDetails', ''));
                $homePlayers = $homeTeam->getPlayers()->indexBy('name')->all();
                $awayGoals = explode(';', ArrayHelper::getValue($match, 'AwayGoalDetails', ''));
                $awayPlayers = $awayTeam->getPlayers()->indexBy('name')->all();

                foreach ($homeGoals as $homeGoal) {
                    @list($minute, $player) = @array_map('trim', @explode(':', $homeGoal));
                    $isPenalty = substr($player, 0, 7) === 'penalty';
                    if ($isPenalty) {
                        $player = ltrim(substr($player, 8));
                    }
                    $isOwn = substr($player, 0, 3) === 'Own';
                    if ($isOwn) {
                        $player = ltrim(substr($player, 4));
                    }
                    $minute = rtrim($minute, '\'');

                    $dbGoal = call_user_func([$this->goalClass, 'findOne'], [
                        'match_id' => $dbMatch->id,
                        'minute' => $minute
                    ]);
                    /* @var $dbGoal \drsdre\yii\xmlsoccer\models\Goal */

                    if ($dbGoal) {
                        $this->stdout("Goal '$minute' already exists. Continue...\n");
                        continue;
                    }

                    $dbGoal = Yii::createObject([
                        'class' => $this->goalClass,
                        'match_id' => $dbMatch->id,
                        'minute' => $minute,
                        'player_id' => ArrayHelper::getValue(
                            ($isOwn ? $awayPlayers : $homePlayers),
                            [$player, 'id']
                        ),
                        'owngoal' => $isOwn,
                        'penalty' => $isPenalty
                    ]);
                    if (!$dbGoal->save()) {
                        $this->stderr("Failed to save goal '$minute': ", Console::FG_RED);
                        $this->stderr("\n");
                        foreach ($dbGoal->errors as $attribute => $errors) {
                            foreach ($errors as $error) {
                                $this->stderr("$attribute: $error", Console::BG_YELLOW, Console::FG_BLACK);
                                $this->stderr("\n");
                            }
                        }
                        $this->stderr("\n");
                    } else {
                        $this->stdout("Goal '$minute' saved", Console::FG_GREEN);
                        $this->stdout("\n");
                    }
                }
                foreach ($awayGoals as $awayGoal) {
                    @list($minute, $player) = @array_map('trim', @explode(':', $awayGoal));
                    $isPenalty = substr($player, 0, 7) === 'penalty';
                    if ($isPenalty) {
                        $player = ltrim(substr($player, 8));
                    }
                    $isOwn = substr($player, 0, 3) === 'Own';
                    if ($isOwn) {
                        $player = ltrim(substr($player, 4));
                    }
                    $minute = rtrim($minute, '\'');

                    $dbGoal = Yii::createObject([
                        'class' => $this->goalClass,
                        'match_id' => $dbMatch->id,
                        'minute' => $minute,
                        'player_id' => ArrayHelper::getValue(
                            ($isOwn ? $homePlayers : $awayPlayers),
                            [$player, 'id']
                        ),
                        'owngoal' => $isOwn,
                        'penalty' => $isPenalty
                    ]);
                    if (!$dbGoal->save()) {
                        $this->stderr("Failed to save goal '$minute': ", Console::FG_RED);
                        $this->stderr("\n");
                        foreach ($dbGoal->errors as $attribute => $errors) {
                            foreach ($errors as $error) {
                                $this->stderr("$attribute: $error", Console::BG_YELLOW, Console::FG_BLACK);
                                $this->stderr("\n");
                            }
                        }
                        $this->stderr("\n");
                    } else {
                        $this->stdout("Goal '$minute' saved", Console::FG_GREEN);
                        $this->stdout("\n");
                    }
                }
            }
        }

        return ExitCode::OK;
    }
}