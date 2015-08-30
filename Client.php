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

namespace XMLSoccer;

use yii\base\Component;
use yii\base\InvalidConfigException;

class Client extends Component {
	/**
	 * @var string url API endpoint
	 */
	public $service_url = "http://www.xmlsoccer.com/FootballData.asmx";

	/**
	 * @var string API key as shown on http://www.xmlsoccer.com/Account.aspx
	 */
	public $api_key;

	/**
	 * @var string optional the IP address of interface for originating requests
	 */
	public $request_ip;

	/**
	 * @var string optional a cache component/name to cache content of requests between time outs
	 */
	public $cache;

	/**
	 * @var boolean optional generate a content hash to facilitate easier change detection
	 */
	public $generate_hash = false;

	/**
	 * Time out to avoid misuse of service for specific calls
	 */
	const TIMEOUT_GetLiveScore = 25;
	const TIMEOUT_GetLiveScoreByLeague = 25;
	const TIMEOUT_GetOddsByFixtureMatchID = 3600;
	const TIMEOUT_GetHistoricMatchesByLeagueAndSeason = 3600;
	const TIMEOUT_GetAllTeams = 3600;
	const TIMEOUT_GetAllTeamsByLeagueAndSeason = 3600;
	const TIMEOUT_Others = 300;
	const TIMEOUT_CURL = 30;

	/**
	 * Initialize component
	 *
	 * @throws InvalidConfigException
	 */
	public function init() {
		if ( empty( $this->service_url ) ) {
			throw new InvalidConfigException( "service_url cannot be empty. Please configure." );
		}
		if ( empty( $this->api_key ) ) {
			throw new InvalidConfigException( "api_key cannot be empty. Please configure." );
		}
		if ($this->cache) {
			// If class was specified as name, try to instantiate on application
			if (is_string($this->cache)) {
				$this->cache = \yii::$app->{$this->cache};
			}
		}
	}

	/**
	 * Set the IP address of specific interface to be used for API calls
	 *
	 * @param $ip
	 *
	 * @throws InvalidConfigException
	 */
	public function setRequestIp( $ip ) {
		if ( empty( $ip ) ) {
			throw new InvalidConfigException( "IP parameter cannot be empty.", E_USER_WARNING );
		}
		$this->request_ip = $ip;
	}

	/**
	 *	list available methods with params.
	 */
	public function __call( $name, $params ) {

		$url = $this->buildUrl( $name, $params );

		// If caching is available try to return results from cache
		if ( $this->cache && $xml = $this->xmlCacheGet( $url ) ) {
			return $xml;
		}

		// Retrieve the data from API
		$data = $this->request( $url );

		// Convert and check if data is valid XML
		if ( false === ( $xml = simplexml_load_string( $data ) ) ) {
			throw new Exception( "Invalid XML", Exception::E_API_INVALID_RESPONSE );
		}

		// Check if time-out is given for call
		if ( strstr( $xml[0], "To avoid misuse of the service" ) ) {
			// Check if API key was added to spammers list
			$spam_result = $this->IsMyApiKeyPutOnSpammersList();
			if ( strstr( $spam_result, "Yes" ) ) {
				throw new Exception( $spam_result, Exception::E_API_SPAM_LIST );
				// $this->getFunctionTimeout( $name )
			}
			else {
				throw new Exception( $xml[0], Exception::E_API_RATE_LIMIT );
				// $this->getFunctionTimeout( $name )
			}
		}

		// If requested generate a content hash and source url
		if ($this->generate_hash) {
			// Hash an XML copy without account information to prevent wrongly hash mismatches
			$xml_hashing = clone $xml;
			unset($xml_hashing->AccountInformation);

			$xml->addChild( 'contentHash', md5($xml_hashing->asXML()) );
			$xml->addChild( 'sourceUrl', htmlspecialchars( $url ) );
		}

		// If caching is available put results in cache
		if ( $this->cache ) {
			// Add cache information
			$xml->addChild('cached', date('Y-m-d h:m:s'));
			if (!$this->xmlCacheSet( $url, $xml, $this->getFunctionTimeout( $name ) )) {
				throw new Exception('Failed to cache results for '.$name, Exception::E_API_GENERAL);
			}
		}

		return $xml;
	}

	/**
	 * Get call time-out for function
	 *
	 * @param $function
	 *
	 * @return int|mixed
	 */
	protected function getFunctionTimeout($function) {
		switch ( $function ) {
			case "GetLiveScore":
			case "GetLiveScoreByLeague":
			case "GetOddsByFixtureMatchID":
			case "GetHistoricMatchesByLeagueAndSeason":
			case "GetAllTeams":
			case "GetAllTeamsByLeagueAndSeason":
				return constant( "self::TIMEOUT_" . $function );
			default:
				return self::TIMEOUT_Others;
		}
	}

	/**
	 * Build URL for API call
	 *
	 * @param $method
	 * @param $params
	 *
	 * @return string
	 * @throws Exception
	 */
	protected function buildUrl( $method, $params ) {
		$url = $this->service_url . "/" . $method . "?apikey=" . $this->api_key;
		for ( $i = 0; $i < count( $params ); $i ++ ) {
			if ( is_array( $params[ $i ] ) ) {
				foreach ( $params[ $i ] as $key => $value ) {
					$url .= "&" . strtolower( $key ) . "=" . rawurlencode( $value );
				}
			} else {
				throw new Exception( "Arguments must be an array", Exception::E_API_INVALID_PARAMETER );
			}
		}

		return $url;
	}

	/**
	 * Execute API request
	 *
	 * @param $url
	 *
	 * @return mixed
	 * @throws Exception
	 */
	protected function request( $url ) {
		$curl = curl_init();
		curl_setopt( $curl, CURLOPT_URL, $url );
		curl_setopt( $curl, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $curl, CURLOPT_TIMEOUT, self::TIMEOUT_CURL );
		curl_setopt( $curl, CURLOPT_CONNECTTIMEOUT, self::TIMEOUT_CURL );
		if ( !empty($this->request_ip) ) {
			curl_setopt( $curl, CURLOPT_INTERFACE, $this->request_ip );
		}
		$data      = curl_exec( $curl );
		$cerror    = curl_error( $curl );
		$cerrno    = curl_errno( $curl );
		$http_code = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
		curl_close( $curl );

		if ( $cerrno != 0 ) {
			throw new Exception( "Curl error: $cerror ($cerrno)\nURL: $url", E_USER_WARNING );
		}

		if ( $http_code <> 200 ) {
			throw new Exception( "Wrong HTTP status code: $http_code - $data\nURL: $url" );
		}
		return $data;
	}

	/**
	 * Cache result
	 *
	 * @param $key
	 * @param $xml
	 * @param $timeout
	 */
	protected function xmlCacheSet($key, $xml, $timeout)
	{
		return $this->cache->set($key, $xml->asXML(), $timeout);
	}

	/**
	 * Retrieve from cache
	 *
	 * @param $key
	 *
	 * @return SimpleXMLElement an object of class SimpleXMLElement
	 */
	protected function xmlCacheGet($key)
	{
		if ($xml = $this->cache->get($key)) {
			return simplexml_load_string($xml);
		}
	}
}