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
use drsdre\yii\xmlsoccer\models\Goal;
use drsdre\yii\xmlsoccer\models\Group;
use drsdre\yii\xmlsoccer\models\League;
use drsdre\yii\xmlsoccer\models\Match;
use drsdre\yii\xmlsoccer\models\Player;
use drsdre\yii\xmlsoccer\models\Team;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\console\widgets\Table;
use yii\helpers\ArrayHelper;
use yii\helpers\Console;

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
     */
    public function importLeagues()
    {
        $client = new Client([
            'apiKey' => $this->apiKey
        ]);

        $leagues = $client->getAllLeagues();
        $count = 0;
        foreach ($leagues as $league) {
            $dbLeague = new League([
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
    public function showLeagues()
    {
        $leagues = League::find();

        if (!$leagues->count('id')) {
            $this->stdout("No leagues found. Import by ");
            $this->stdout("{$this->id}/import-leagues", Console::BOLD);
            $this->stdout("\n");
            return ExitCode::OK;
        }

        $first = $leagues->one();

        echo Table::widget([
            'headers' => $first->attributeLabels(),
            'rows' => ArrayHelper::toArray($leagues->all())
        ]);

        return ExitCode::OK;
    }

    /**
     * Create specific league by id. This method imports all league relevant data from interface to db.
     * If league id is not known, please execute
     * ```sh
     * $ ./yii xml-soccer/show-leagues
     * ```
     *      *
     * @param integer $league_id League id to import all data from
     * @param string|null $seasonDateString Season date string to import data from. Defaults to current season.
     * @return int Exit code
     */
    public function createLeague($league_id, $seasonDateString = null)
    {
        $league = League::findOne($league_id);
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
                $dbGroup = new Group([
                    'interface_id' => ArrayHelper::getValue($group, 'Id'),
                    'name' => ArrayHelper::getValue($group, 'Name'),
                    'season' => ArrayHelper::getValue($group, 'Season'),
                    'league_id' => $league->id
                ]);

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
            $dbTeam = new Team([
                'interface_id' => ArrayHelper::getValue($team, 'Team_Id'),
                'name' => ArrayHelper::getValue($team, 'Name'),
                'country' => ArrayHelper::getValue($team, 'Country'),
                'stadium' => ArrayHelper::getValue($team, 'Stadium'),
                'home_page_url' => ArrayHelper::getValue($team, 'HomePageURL'),
                'wiki_link' => ArrayHelper::getValue($team, 'WIKILink'),
                'coach' => ArrayHelper::getValue($team, 'Coach') ?: ArrayHelper::getValue($team, 'Manager')
            ]);

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

        $groups = Group::find()->where(['league_id' => $league->id])->indexBy('interface_id')->all();
        $teams = Team::find()->indexBy('interface_id')->all();

        foreach ($players as $player) {
            $dbPlayer = new Player([
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
            /* @var $homeTeam Team */
            /* @var $awayTeam Team */
            $dbMatch = new Match([
                'interface_id' => ArrayHelper::getValue($match, 'Id'),
                'date' => ArrayHelper::getValue($match, 'Date'),
                'league_id' => $league->id,
                'round' => ArrayHelper::getValue($match, 'Round'),
                'home_team_id' => $homeTeam->id,
                'away_team_id' => $awayTeam->id,
                'group_id' => ArrayHelper::getValue($groups, ArrayHelper::getValue($match, 'Group_Id', -100)),
                'location' => ArrayHelper::getValue($match, 'Location')
            ]);

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

                        $dbGoal = new Goal([
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

                        $dbGoal = new Goal([
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
        }

        return ExitCode::OK;
    }

    /**
     * Update Scores from LiveScore
     */
    public function updateScore()
    {
        $client = new Client([
            'apiKey' => $this->apiKey
        ]);

        $liveScoreData = $client->getLiveScore();
        foreach ($liveScoreData as $match) {
            $dbMatch = Match::findOne(['interface_id' => ArrayHelper::getValue($match, 'Id')]);
            if (!$dbMatch) {
                continue;
            }

            $homeTeam = Team::findOne(['interface_id' => ArrayHelper::getValue($match, 'HomeTeam_Id')]);
            $awayTeam = Team::findOne(['interface_id' => ArrayHelper::getValue($match, 'AwayTeam_Id')]);

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

                    $dbGoal = Goal::findOne([
                        'match_id' => $dbMatch->id,
                        'minute' => $minute
                    ]);

                    if ($dbGoal) {
                        $this->stdout("Goal '$minute' already exists. Continue...\n");
                        continue;
                    }

                    $dbGoal = new Goal([
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

                    $dbGoal = new Goal([
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