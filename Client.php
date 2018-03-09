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
use Yii;

/**
 * Class Client
 * @package drsdre\yii\xmlsoccer
 *
 * @method \SimpleXMLElement checkApiKey()
 * @method \SimpleXMLElement getAllGroupsByLeagueAndSeason(string $league, string $seasonDateString)
 * @method \SimpleXMLElement getAllLeagues()
 * @method \SimpleXMLElement getAllOddsByFixtureMatchId(integer $fixtureMatch_Id)
 * @method \SimpleXMLElement getAllTeams()
 * @method \SimpleXMLElement getAllTeamsByLeagueAndSeason(string $league, string $seasonDateString)
 * @method \SimpleXMLElement getCupStandingsByGroupId(integer $group_Id)
 * @method \SimpleXMLElement getEarliestMatchDatePerLeague(string $league)
 * @method \SimpleXMLElement getFixtureMatchByID(integer $Id)
 * @method \SimpleXMLElement getFixturesByDateInterval(string $startDateString, string $endDateString)
 * @method \SimpleXMLElement getFixturesByDateIntervalAndLeague(string $startDateString, string $endDateString, string $league)
 * @method \SimpleXMLElement getFixturesByDateIntervalAndTeam(string $startDateString, string $endDateString, string $teamId)
 * @method \SimpleXMLElement getFixturesByLeagueAndSeason(string $league, string $seasonDateString)
 * @method \SimpleXMLElement getHistoricMatchesByFixtureMatchID(integer $Id)
 * @method \SimpleXMLElement getHistoricMatchesByID(integer $Id)
 * @method \SimpleXMLElement getHistoricMatchesByLeagueAndDateInterval(string $startDateString, string $endDateString, string $league)
 * @method \SimpleXMLElement getHistoricMatchesByLeagueAndSeason(string $league, string $seasonDateString)
 * @method \SimpleXMLElement getHistoricMatchesByTeamAndDateInterval(string $startDateString, string $endDateString, string $teamId)
 * @method \SimpleXMLElement getLeagueStandingsBySeason(string $league, string $seasonDateString)
 * @method \SimpleXMLElement getLiveScore()
 * @method \SimpleXMLElement getNextMatchOddsByLeague(string $league)
 * @method \SimpleXMLElement getOddsByFixtureMatchId(string $fixtureMatch_Id)
 * @method \SimpleXMLElement getOddsByFixtureMatchId2(string $fixtureMatch_Id)
 * @method \SimpleXMLElement getPlayerById(integer $playerId)
 * @method \SimpleXMLElement getPlayersByTeam(string $teamId)
 * @method \SimpleXMLElement getPlayoffFixturesByLeagueAndSeason(string $league, string $seasonDateString)
 * @method \SimpleXMLElement getRescheduleOfMatchDatesByFixtureMatchId(integer $Id)
 * @method \SimpleXMLElement getTeam(string $teamName)
 * @method \SimpleXMLElement getTopScorersByGroupId(integer $groupId)
 * @method \SimpleXMLElement getTopScorersByLeagueAndSeason(string $league, string $seasonDateString)
 * @method \SimpleXMLElement imAlive()
 * @method \SimpleXMLElement isMyApiKeyPutOnSpammersList()
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
        }
    }

    /**
     * Set the IP address of specific interface to be used for API calls
     *
     * @param string $ip
     *
     * @throws InvalidConfigException
     */
    public function setRequestIp($ip)
    {
        if (empty($ip)) {
            throw new InvalidConfigException("IP parameter cannot be empty.", E_USER_WARNING);
        }
        $this->requestIp = $ip;
    }

    /**
     * Check if API account is on spam list
     *
     * @return false|string error message
     * @throws \drsdre\yii\xmlsoccer\Exception
     */
    public function onSpamlist()
    {
        $url = $this->buildUrl('IsMyApiKeyPutOnSpammersList', []);

        // Retrieve the data from API
        $result = $this->request($url);

        if (strstr($result, "Yes")) {
            return $result;
        } else {
            return false;
        }
    }

    /**
     * List available methods with params.
     *
     * @return \SimpleXMLElement
     *
     * @throws \drsdre\yii\xmlsoccer\Exception
     */
    public function __call($name, $params)
    {

        $url = $this->buildUrl($name, $params);

        // If caching is available try to return results from cache
        if ($this->cache && $xml = $this->xmlCacheGet($url)) {
            return $xml;
        }

        // Retrieve the data from API
        $data = $this->request($url);

        // Convert and check if data is valid XML
        if (false === ($xml = simplexml_load_string($data))) {
            throw new Exception("$url: Invalid XML given back", Exception::E_API_INVALID_RESPONSE);
        }

        // Check if API-key is valid
        if (strstr($xml[0], "unable to verify your API-key")) {
            throw new Exception("$url: {$xml[0]}", Exception::E_API_INVALID_PARAMETER);
        }

        // Check if time-out is given for call
        if (strstr($xml[0], "To avoid misuse of the service")) {
            // Check if API key was added to spammers list
            if ($spam_result = $this->onSpamlist() !== false) {
                throw new Exception("$url: $spam_result", Exception::E_API_SPAM_LIST);
                // $this->getFunctionTimeout( $name )
            } else {
                throw new Exception("$url: {$xml[0]}", Exception::E_API_RATE_LIMIT);
                // $this->getFunctionTimeout( $name )
            }
        }

        // If requested generate a content hash and source url
        if ($this->generateHash) {
            // Hash an XML copy without account information to prevent wrongly hash mismatches
            $xml_hashing = clone $xml;
            unset($xml_hashing->AccountInformation);

            $xml->addChild('contentHash', md5($xml_hashing->asXML()));
            $xml->addChild('sourceUrl', htmlspecialchars($url));
        }

        // If caching is available put results in cache
        if ($this->cache) {
            // Add cache information
            $xml->addChild('cached', date('Y-m-d h:m:s'));
            if (!$this->xmlCacheSet($url, $xml, $this->getFunctionTimeout($name))) {
                throw new Exception("$url: Failed to cache results", Exception::E_API_GENERAL);
            }
        }

        return $xml;
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
     * Build URL for API call
     *
     * @param string $methodName
     * @param array $params
     *
     * @return string
     */
    protected function buildUrl($methodName, $params)
    {
        $mapping = $this->getMethodArguments($methodName);

        $url = $this->serviceUrl . "/" . ucfirst($methodName) . "?ApiKey=" . $this->apiKey;
        for ($i = 0; $i < count($params); $i++) {
            if (array_key_exists($i, $mapping)) {
                $url .= "&" . $mapping[$i]['name'] . "=" . rawurlencode($params[$i]);
            }
        }

        return $url;
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
                    @list($null, $returnType, $methodName, $argumentString) = $matches;
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

                    $this->_magicMethodArgumentNames[$methodName] = $arguments;
                }
            }
        } catch (\ReflectionException $e) {

        }

        return ArrayHelper::getValue($this->_magicMethodArgumentNames, $methodName, []);
    }

    /**
     * Execute API request
     *
     * @param string $url
     *
     * @return mixed
     * @throws \drsdre\yii\xmlsoccer\Exception
     */
    protected function request($url)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_TIMEOUT, self::TIMEOUT_CURL);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, self::TIMEOUT_CURL);
        if (!empty($this->requestIp)) {
            curl_setopt($curl, CURLOPT_INTERFACE, $this->requestIp);
        }
        $data = curl_exec($curl);
        $cerror = curl_error($curl);
        $cerrno = curl_errno($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($cerrno != 0) {
            throw new Exception("Curl error: $cerror ($cerrno)\nURL: $url", E_USER_WARNING);
        }

        if ($http_code <> 200) {
            throw new Exception("Wrong HTTP status code: $http_code - $data\nURL: $url", Exception::E_API_GENERAL);
        }
        return $data;
    }

    /**
     * Cache result
     *
     * @param string $key
     * @param \SimpleXMLElement $xml
     * @param integer $timeout
     *
     * @return boolean
     */
    protected function xmlCacheSet($key, $xml, $timeout)
    {
        return $this->cache->set($key, $xml->asXML(), $timeout);
    }

    /**
     * Retrieve from cache
     *
     * @param string $key
     *
     * @return \SimpleXMLElement|null an object of class SimpleXMLElement
     */
    protected function xmlCacheGet($key)
    {
        if ($xml = $this->cache->get($key)) {
            return simplexml_load_string($xml);
        }

        return null;
    }
}