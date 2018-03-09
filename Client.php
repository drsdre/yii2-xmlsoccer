<?php
/**
 * XMLSoccer.com API Yii2 Client Component
 *
 * @see http://xmlsoccer.wikia.com/wiki/API_Documentation
 * @see http://promo.lviv.ua
 * @author Volodymyr Chukh <vova@promo.lviv.ua>
 * @author Andre Schuurman <andre.schuurman@gmail.com>
 * @copyright 2014 Volodymyr Chukh
 * @license MIT License
 */

namespace drsdre\yii\xmlsoccer;

use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\helpers\ArrayHelper;
use yii\validators\IpValidator;
use Yii;

/**
 * Class Client
 * @package drsdre\yii\xmlsoccer
 *
 * @method array checkApiKey()
 * @method array getAllGroupsByLeagueAndSeason(string $league, string $seasonDateString)
 * @method array getAllLeagues()
 * @method array getAllOddsByFixtureMatchId(integer $fixtureMatch_Id)
 * @method array getAllTeams()
 * @method array getAllTeamsByLeagueAndSeason(string $league, string $seasonDateString)
 * @method array getCupStandingsByGroupId(integer $group_Id)
 * @method array getEarliestMatchDatePerLeague(string $league)
 * @method array getFixtureMatchByID(integer $Id)
 * @method array getFixturesByDateInterval(string $startDateString, string $endDateString)
 * @method array getFixturesByDateIntervalAndLeague(string $startDateString, string $endDateString, string $league)
 * @method array getFixturesByDateIntervalAndTeam(string $startDateString, string $endDateString, string $teamId)
 * @method array getFixturesByLeagueAndSeason(string $league, string $seasonDateString)
 * @method array getHistoricMatchesByFixtureMatchID(integer $Id)
 * @method array getHistoricMatchesByID(integer $Id)
 * @method array getHistoricMatchesByLeagueAndDateInterval(string $startDateString, string $endDateString, string $league)
 * @method array getHistoricMatchesByLeagueAndSeason(string $league, string $seasonDateString)
 * @method array getHistoricMatchesByTeamAndDateInterval(string $startDateString, string $endDateString, string $teamId)
 * @method array getLeagueStandingsBySeason(string $league, string $seasonDateString)
 * @method array getLiveScore()
 * @method array getNextMatchOddsByLeague(string $league)
 * @method array getOddsByFixtureMatchId(string $fixtureMatch_Id)
 * @method array getOddsByFixtureMatchId2(string $fixtureMatch_Id)
 * @method array getPlayerById(integer $playerId)
 * @method array getPlayersByTeam(string $teamId)
 * @method array getPlayoffFixturesByLeagueAndSeason(string $league, string $seasonDateString)
 * @method array getRescheduleOfMatchDatesByFixtureMatchId(integer $Id)
 * @method array getTeam(string $teamName)
 * @method array getTopScorersByGroupId(integer $groupId)
 * @method array getTopScorersByLeagueAndSeason(string $league, string $seasonDateString)
 * @method array imAlive()
 * @method array isMyApiKeyPutOnSpammersList()
 */
class Client extends Component
{
    /**
     * @var string url API endpoint
     */
    public $serviceUrl = "http://www.xmlsoccer.com/FootballData.asmx";

    /**
     * @var string API key as shown on http://www.xmlsoccer.com/Account.aspx
     */
    public $apiKey;

    /**
     * @var string optional the IP address of interface for originating requests
     */
    public $requestIp;

    /**
     * @var \yii\caching\CacheInterface optional a cache component/name to cache content of requests between time outs
     * (defaults to [[Yii::$app->cache]])
     */
    public $cache;

    /**
     * @var boolean optional generate a content hash to facilitate easier change detection
     */
    public $generateHash = false;

    /**
     * @var array internal method argumentNames (will be parsed from doc comment)
     */
    private $_magicMethodArgumentNames = [];

    /**
     * @var \yii\httpclient\Client Http client to send and parse requests
     */
    private $_client;

    /**
     * Time out to avoid misuse of service for specific calls
     */
    const TIMEOUT_GETLIVESCORE = 25;
    const TIMEOUT_GETLIVESCOREBYLEAGUE = 25;
    const TIMEOUT_GETODDSBYFIXTUREMATCHID = 3600;
    const TIMEOUT_GETHISTORICMATCHESBYLEAGUEANDSEASON = 3600;
    const TIMEOUT_GETALLTEAMS = 3600;
    const TIMEOUT_GETALLTEAMSBYLEAGUEANDSEASON = 3600;
    const TIMEOUT_OTHERS = 300;
    const TIMEOUT_CURL = 30;

    /**
     * Initialize component
     *
     * @throws InvalidConfigException
     */
    public function init()
    {
        if (empty($this->serviceUrl)) {
            throw new InvalidConfigException("service_url cannot be empty. Please configure.");
        }
        if (empty($this->apiKey)) {
            throw new InvalidConfigException("api_key cannot be empty. Please configure.");
        }
        if ($this->cache) {
            // If class was specified as name, try to instantiate on application
            if (is_string($this->cache)) {
                $this->cache = ArrayHelper::getValue(Yii::$app, $this->cache);
            } elseif (is_array($this->cache)) {
                $this->cache = Yii::createObject($this->cache);
            }
        } elseif (Yii::$app->cache) {
            $this->cache = &Yii::$app->cache;
        }
        $this->_client = new \yii\httpclient\Client([
            'baseUrl' => $this->serviceUrl,
            'requestConfig' => [
                'method' => 'POST',
                'data' => [
                    'ApiKey' => $this->apiKey
                ]
            ],
            'responseConfig' => [
                'format' => \yii\httpclient\Client::FORMAT_XML
            ]
        ]);

        if ($this->requestIp) {
            $validator = new IpValidator([
                'ipv6' => true,
                'ipv4' => true,
                'subnet' => false,
                'expandIPv6' => true
            ]);
            if ($validator->validate($this->requestIp)) {
                ArrayHelper::setValue($this->_client->requestConfig, 'options.bindto', $this->requestIp);
            }
        }
    }

    /**
     * List available methods with params.
     *
     * @param string $methodName
     * @param array $params
     * @return array
     *
     * @throws Exception
     */
    public function __call($methodName, $params)
    {
        $mapping = $this->getMethodArguments($methodName);

        $hash = md5($methodName.serialize($params));
        if ($this->cache && (false !== ($array = $this->cache->get($hash)))) {
            return $array;
        }

        $data = [];
        for ($i = 0; $i < count($params); $i++) {
            if (array_key_exists($i, $mapping)) {
                switch (strtolower($mapping[$i]['type'])) {
                    case 'int':
                    case 'integer':
                        $params[$i] = (integer)$params[$i];
                        break;
                    case 'bool':
                    case 'boolean':
                        $params[$i] = (boolean)$params[$i];
                        break;
                    case 'float':
                    case 'double':
                        $params[$i] = (float)$params[$i];
                        break;
                    case 'array':
                        $params[$i] = (array)$params[$i];
                        break;
                    case 'string':
                    default:
                        $params[$i] = (string)$params[$i];
                        break;
                }
                $data[$mapping[$i]['name']] = $params[$i];
            }
        }

        $response = $this->_client
            ->createRequest()
            ->setUrl(ucfirst($methodName))
            ->addData($data)
            ->send();

        if ($response->isOk) {
            if ($this->cache) {
                $this->cache->set($hash, $response->data, $this->getFunctionTimeout($methodName));
            }

            return $response->data;
        } else {
            throw new Exception($response->content);
        }
    }

    /**
     * Get call time-out for function
     *
     * @param string $functionName
     *
     * @return integer|mixed
     */
    protected function getFunctionTimeout($functionName)
    {
        switch ($functionName) {
            case "GetLiveScore":
            case "GetLiveScoreByLeague":
            case "GetOddsByFixtureMatchID":
            case "GetHistoricMatchesByLeagueAndSeason":
            case "GetAllTeams":
            case "GetAllTeamsByLeagueAndSeason":
                return constant("self::TIMEOUT_" . strtoupper($functionName));
            default:
                return self::TIMEOUT_OTHERS;
        }
    }

    /**
     * Get argument names and positions and data types from phpdoc class comment
     *
     * @param string $methodName method to get arguments from
     * @return mixed
     */
    protected function getMethodArguments($methodName)
    {
        if (!empty($this->_magicMethodArgumentNames)) {
            return ArrayHelper::getValue($this->_magicMethodArgumentNames, $methodName, []);
        }
        try {
            $reflectionClass = new \ReflectionClass($this);
            $comment = $reflectionClass->getDocComment();
            $lines = preg_split('/[\r\n]/', $comment);
            $regexp = '#\s*\*\s*@method ((?:.*) )?([a-zA-Z_]+)\(((?:[\\a-zA-Z]+\s+)?\$(?:[a-zA-Z_]+),?\s*)*\)#';

            foreach ($lines as $line) {
                $matches = [];
                if (preg_match($regexp, $line, $matches)) {
                    @list($null, $returnType, $method, $argumentString) = $matches;
                    if (is_null($argumentString)) {
                        $arguments = [];
                    } else {
                        $arguments = array_map('trim', explode(',', $argumentString));

                        foreach ($arguments as $k => $argument) {
                            if (empty($argument)) {
                                continue;
                            }
                            if (strpos($argument, ' ') !== false) {
                                $tmp = explode(' ', $argument);
                                $arguments[$k] = ['name' => ltrim($tmp[1], '$'), 'type' => $tmp[0]];
                            } else {
                                $arguments[$k] = ['name' => ltrim($argument, '$'), 'type' => 'mixed'];
                            }
                        }
                    }

                    $this->_magicMethodArgumentNames[$method] = $arguments;
                }
            }
        } catch (\ReflectionException $e) {

        }

        return ArrayHelper::getValue($this->_magicMethodArgumentNames, $methodName, []);
    }
}