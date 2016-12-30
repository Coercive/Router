<?php
namespace Coercive\Utility\Router;

use \Coercive\Utility\Globals\Globals;
use \Symfony\Component\Yaml\Parser;
use \Exception;

/**
 * Router
 * PHP Version 	7
 *
 * (FR) La simplicité est la sophistication suprême.
 * Léonard de Vinci
 *
 * @version		2.0.2
 * @package		Coercive\Utility\Router
 * @link		@link https://github.com/Coercive/Router
 *
 * @author  	Anthony Moral <contact@coercive.fr>
 * @copyright   (c) 2016 - 2017 Anthony Moral
 * @license 	http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License
 */
class Router {

	# PROPERTIES
	const YAML_CONTROLLER = '__';

	# REGEX
	const DEFAULT_MATCH_REGEX = '[^/]+';
	const REGEX_PATH = '`\{([a-z_][a-z0-9_-]*)(?::([^{}]*(?:\{(?-1)\}[^{}]*)*))?\}`i';
	const REGEX_OPTION = '`\[(.*#[0-9]+#.*)?\]`i';
	const REGEX_OPTION_NUMBER = '`#([0-9]+)#`i';

	/** @var string INPUT_SERVER */
	private $_REQUEST_SCHEME,
		$_DOCUMENT_ROOT,
		$_HTTP_HOST,
		$_REQUEST_METHOD,
		$_HTTP_USER_AGENT,

		$_REQUEST_URI,
		$_QUERY_STRING;

	/** @var array $_aFilesRoutes */
	private $_aFilesRoutes = [];

	/** @var array $_aRoutes */
	private $_aRoutes = [];

	/** @var string $_sUrl */
	private $_sUrl = null;

	/** @var string $_sPath */
	private $_sPath = null;

	/** @var string $_sMatchedPath */
	private $_sMatchedPath = null;

	/** @var array $aParamsGet */
	private $_aParamsGet = [];

	/** @var array $_aRouteParamsGet */
	private $_aRouteParamsGet = [];

	/** @var array $_aParsedRouteParams */
	private $_aParsedRouteParams = [];

	/** @var array Overload params for switch url lang */
	private $_aTranslateRouteParams = [];

	/** @var string Current Language set for switch url lang */
	private $_sSwitchCurrentLang = '';

	/** @var string $_sId */
	private $_sId = null;

	/** @var string $_sLang */
	private $_sLang = null;

	/** @var string $_sController */
	private $_sController = null;

	/** @var bool $_bAjaxDemand */
	private $_bAjaxDemand = false;

	/** @var string $_sHttpAccept */
	private $_sHttpAccept = null;

	/** @var bool $_bIsOfficalBot */
	private $_bIsOfficalBot = false;

	/**
	 * EXCEPTION
	 *
	 * @param string $sMessage
	 * @param int $sLine
	 * @param string $sMethod
	 * @throws Exception
	 */
	static private function _exception($sMessage, $sLine = __LINE__, $sMethod = __METHOD__) {
		throw new Exception("$sMessage \nMethod : $sMethod \nLine : $sLine");
	}

	/**
	 * INIT INPUT SERVER
	 *
	 * @return void
	 */
	private function _initInputServer() {

		# INPUT_SERVER
		$this->_REQUEST_SCHEME = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
		$this->_DOCUMENT_ROOT = (string) filter_input(INPUT_SERVER, 'DOCUMENT_ROOT', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
		$this->_HTTP_HOST = (string) filter_input(INPUT_SERVER, 'HTTP_HOST', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
		$this->_REQUEST_METHOD = (string) filter_input(INPUT_SERVER, 'REQUEST_METHOD', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
		$this->_HTTP_USER_AGENT = (string) filter_input(INPUT_SERVER, 'HTTP_USER_AGENT', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

		# INPUT SERVER REQUEST
		$this->_REQUEST_URI = (string) urldecode(filter_input(INPUT_SERVER, 'REQUEST_URI', FILTER_SANITIZE_URL));
		$this->_QUERY_STRING = (string) urldecode(filter_input(INPUT_SERVER, 'QUERY_STRING', FILTER_SANITIZE_URL));

	}

	/**
	 * INIT PARAM QUERY STRING
	 *
	 * @return void
	 */
	private function _initQueryString() {

		# Array of params
		parse_str($this->_QUERY_STRING, $aGet);
		$this->_aParamsGet = $aGet;

	}

	/**
	 * INIT AJAX REQUEST DETECTION
	 *
	 * @return void
	 */
	private function _initAjaxDetection() {

		/** @var bool */
		$this->_bAjaxDemand = filter_input(INPUT_SERVER, 'HTTP_X_REQUESTED_WITH', FILTER_SANITIZE_FULL_SPECIAL_CHARS) === 'XMLHttpRequest';

		/** @var string $sAccept */
		$sAccept = (string) filter_input(INPUT_SERVER, 'HTTP_ACCEPT', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
		if (false !== strpos($sAccept, 'text/html')) {
			$this->_sHttpAccept = 'html';
		}
		elseif (false !== strpos($sAccept, 'application/json')) {
			$this->_sHttpAccept = 'json';
		}
		elseif (false !== strpos($sAccept, 'application/xml')) {
			$this->_sHttpAccept = 'xml';
		}
		else {
			$this->_sHttpAccept = 'html';
		}

	}

	/**
	 * INIT BOT DETECTION
	 *
	 * @return void
	 */
	private function _initBotDetection() {
		$this->_bIsOfficalBot = (bool) preg_match('/(bot|google|googlebot|spider|yahoo)/i', $this->_HTTP_USER_AGENT);
	}

	/**
	 * INIT ROUTES
	 *
	 * @return void
	 * @throws Exception
	 */
	private function _initRoutes() {

		# Security
		if(empty($this->_aFilesRoutes) || !is_array($this->_aFilesRoutes)) {
			self::_exception('No Routes Founds', __LINE__, __METHOD__);
		}

		/** @var \Symfony\Component\Yaml\Parser $oYamlParser */
		$oYamlParser = new Parser;

		# Récupération des données et merge
		foreach ($this->_aFilesRoutes as $sFile) {

			# No File => Skip
			if(!file_exists($sFile)) { self::_exception('File does not exist', __LINE__, __METHOD__); }

			# Parse
			$aCurrentYaml = $oYamlParser->parse(file_get_contents($sFile));
			if(empty($aCurrentYaml) || !is_array($aCurrentYaml)) { continue; }

			# Merge
			$this->_aRoutes = array_merge_recursive($this->_aRoutes, $aCurrentYaml);

		}

	}

	/**
	 * INIT SUPER GLOBAL $_GET MERGE
	 *
	 * @return void
	 */
	private function _initSuperGlobalGET() {

		/** @var array $aInputGets */
		$aInputGets = array_replace_recursive([], $this->_aParamsGet, $this->_aRouteParamsGet);

		# Filter
		$aGet = (new Globals)->autoFilterManualVar($aInputGets);

		# MERGE
		$_GET = array_replace_recursive([], $_GET, $aGet);

	}

	/**
	 * Init
	 * Coercive Router constructor.
	 *
	 * @param array $aRoutes
	 */
	public function __construct($aRoutes) {

		# Bind user routes
		$this->_aFilesRoutes = (array) $aRoutes;

		# INPUT SERVER
		$this->_initInputServer();

		# AJAX
		$this->_initAjaxDetection();

		# BOT
		$this->_initBotDetection();

		# PARAMS GET
		$this->_initQueryString();

		# URL
		$this->_sUrl = $this->_clean($this->_REQUEST_URI);

		# ROUTES
		$this->_initRoutes();

		# RUN
		$this->_run();

		# SET _GET
		$this->_initSuperGlobalGET();
	}

	/** GETTERS @return string ID - LANG - CONTROLLER */
	public function getId() { return $this->_sId; }
	public function getLang() { return $this->_sLang; }
	public function getController() { return $this->_sController; }

	/** GETTERS SPECIAL @return string|array|bool */
	public function getAccessMode() { return $this->_REQUEST_METHOD; }
	public function getHttpMode() { return $this->_REQUEST_SCHEME; }
	public function getNoRewritedMatchedPath() { return $this->_sMatchedPath; }
	public function getTranslateRouteParams() { return $this->_aTranslateRouteParams; }
	public function isAjaxRequest() { return $this->_bAjaxDemand; }
	public function setAjaxRequest($bBool) { $this->_bAjaxDemand = $bBool; return $this; }
	public function getHttpAccept() { return $this->_sHttpAccept; }
	public function isOfficialBot() { return $this->_bIsOfficalBot; }
	public function getCurrentURL() { return $this->_REQUEST_URI; }
	public function getServerRootPath() { return $this->_DOCUMENT_ROOT; }

	/**
	 * FORCE LANGUAGE
	 *
	 * @param string $sLang
	 * @return void
	 */
	public function forceLang($sLang) {
		$this->_sLang = $sLang;
	}

	/**
	 * FORCE GET PARAM EXIST
	 *
	 * @param array $aGET
	 * @return void
	 */
	public function forceExistGET($aGET) {
		foreach ($aGET as $sName) {
			if(!isset($_GET[$sName])) { $_GET[$sName] = null; }
		}
	}

	/**
	 * TRANSLATE PARAM FOR URL SWITCH
	 *
	 * @param array $aTranslatedParam
	 * @return Router
	 */
	public function overloadParam($aTranslatedParam) {
		if($aTranslatedParam && is_array($aTranslatedParam)) {
			foreach($aTranslatedParam as $sLang => $aLangParams) {
				foreach($aLangParams as $sIdParam => $sTranslatedValue) {
					$this->_aTranslateRouteParams[$sLang][$sIdParam] = urlencode($sTranslatedValue);
				}
			}
		}
		return $this;
	}

	/**
	 * CLEARS TRANSLATED PARAMS
	 *
	 * @return Router
	 */
	public function resetOverload() {
		$this->_sSwitchCurrentLang = '';
		$this->_aTranslateRouteParams = [];
		return $this;
	}

	/**
	 * GIVE ACTUAL URL IN OTHER LANG
	 *
	 * @param string $sLang
	 * @param bool $bFullUrl [optional]
	 * @return string
	 */
	public function switchLang($sLang, $bFullUrl = false) {

		# REQUESTED URL
		if(empty($this->_aRoutes[$this->_sId][$sLang])) return '';
		$this->_sSwitchCurrentLang = $sLang;
		$sUrl = $this->_clean($this->_aRoutes[$this->_sId][$sLang]);

		# PARAM GET
		$sParamGet = empty($this->_aParamsGet) ? '' : http_build_query($this->_aParamsGet);

		# REWRITE PARAM GET
		if($this->_aParsedRouteParams) {
			$sUrl = $this->_rewriteUrlParams($sUrl);
		}

		# RECOMPOSED URL
		$sUrl = $sParamGet ? "$sUrl?$sParamGet" : $sUrl;
		$sUrl = trim($sUrl, '/-');
		return $bFullUrl ? "{$this->_REQUEST_SCHEME}://{$this->_HTTP_HOST}/$sUrl" : $sUrl;
	}

	/**
	 * URL FABRIC
	 *
	 * @param string $sId
	 * @param string $sLang [optional]
	 * @param array $aRewriteParam [optional]
	 * @param array $aGetParam [optional]
	 * @param string $sFullUrlSheme [optional] (http, https)
	 * @return string
	 */
	public function url($sId, $sLang = '', $aRewriteParam = [], $aGetParam = [], $sFullUrlSheme = '') {

		# AUTO LANG
		if(!$sLang) { $sLang = $this->_sLang; }

		# REQUESTED URL
		if(empty($this->_aRoutes[$sId][$sLang])) return '';
		$sUrl = $this->_clean($this->_aRoutes[$sId][$sLang]);

		# PARAM GET
		$sParamGet = $aGetParam ? http_build_query($aGetParam) : '';

		# REWRITE PARAM
		if($aRewriteParam && is_array($aRewriteParam)) {
			$sUrl = $this->_rewriteUrlParams($sUrl, $aRewriteParam);
		}

		# RECOMPOSED URL
		$sUrl = $sParamGet ? "$sUrl?$sParamGet" : $sUrl;
		$sUrl = trim($sUrl, '/-');
		return $sFullUrlSheme ? "$sFullUrlSheme://{$this->_HTTP_HOST}/$sUrl" : $sUrl;
	}

	/**
	 * Lancement de la détection des routes
	 *
	 * @return void
	 * @throws Exception
	 */
	private function _run() {

		# Security
		if(!$this->_aRoutes) { self::_exception('Router cant start. No routes avialable.', __LINE__, __METHOD__); }

		# Pour chaque ID
		foreach($this->_aRoutes as $sId => $aRoutes) {

			# Pour chaque langue
			foreach($aRoutes as $sLang => $sRoute) {

				# Controller Route
				if($sLang === self::YAML_CONTROLLER) { continue; }

				# Lang Route
				if($this->_match($sRoute)) {
					$this->_sId = $sId;
					$this->_sLang = $sLang;
					$this->_sController = $aRoutes[self::YAML_CONTROLLER];
					$this->_sMatchedPath = $sRoute;
					return;
				}

			}
		}

	}

	/**
	 * Nettoyage des urls
	 *
	 * Détruit les espaces, les slahs en début et fin de chaine, et tous les paramètres get (après '?')
	 *
	 * @param string $sUrl
	 * @return string
	 */
	private function _clean($sUrl) {
		$sUrl = str_replace(' ', '', $sUrl);
		$sUrl = trim($sUrl, '/-');
		$sUrl = preg_replace('#\?.*#', null, $sUrl);
		return $sUrl;
	}

	/**
	 * Correspondance des urls
	 *
	 * @param string $sPath
	 * @return bool
	 */
	private function _match($sPath) {

		# CLEAN
		$this->_sPath = $this->_clean($sPath);

		if(!preg_match("`^{$this->_prepareRoute($this->_sPath)}$`i", $this->_sUrl, $aMatches)) { return false; }
		$aIntKeys = array_filter(array_keys($aMatches), 'is_numeric');
		$this->_aRouteParamsGet = array_diff_key($aMatches, array_flip($aIntKeys));

		return true;
	}

	/**
	 * PARSE ROUTE PARAMS
	 *
	 * @param string $sPath
	 * @return array
	 */
	private function _parseRouteParams($sPath) {

		$aRouteParams = [];

		if(!preg_match_all(self::REGEX_PATH, $sPath, $aMatches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
			return $aRouteParams;
		}

		foreach ($aMatches as $aSet) {
			$aRouteParams[] = [
				'subject' => $aSet[0][0],
				'name' => $aSet[1][0],
				'regex' => isset($aSet[2]) ? $aSet[2][0] : self::DEFAULT_MATCH_REGEX
			];
		}

		return $aRouteParams;
	}

	/**
	 * PREPARE ROUTE
	 *
	 * @param string $sPath
	 * @return string
	 */
	private function _prepareRoute($sPath) {

		if(!$this->_aParsedRouteParams = $this->_parseRouteParams($sPath)) {
			return (string) $sPath;
		}

		foreach ($this->_aParsedRouteParams as $iKey => $aParam) {
			$sPath = str_replace($aParam['subject'], "#$iKey#", $sPath);
		}

		if(preg_match_all(self::REGEX_OPTION, $sPath, $aMatches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {

			foreach ($aMatches as $aMatche) {

				preg_match_all(self::REGEX_OPTION_NUMBER, $aMatche[1][0], $aSubMatches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

				$sRecurseReplace = $aMatche[0][0];
				foreach ($aSubMatches as $aSubMatche) {
					$sRecurseReplace = str_replace($aSubMatche[0][0], $this->_aParsedRouteParams[$aSubMatche[1][0]]['subject'], $sRecurseReplace);
				}

				foreach ($aSubMatches as $aSubMatche) {
					$this->_aParsedRouteParams[$aSubMatche[1][0]]['optional'] = $sRecurseReplace;
				}

			}

		}

		$sPath = preg_replace(self::REGEX_OPTION, '(?:$1)?', $sPath);

		foreach ($this->_aParsedRouteParams as $iKey => $aParam) {
			$sPath = str_replace("#$iKey#", "(?P<$aParam[name]>$aParam[regex])", $sPath);
		}

		return (string) $sPath;
	}

	/**
	 * REWRITE URL WITH PARAMS
	 *
	 * @param string $sUrl
	 * @param array $aInjectedParams [optional]
	 * @return string
	 */
	private function _rewriteUrlParams($sUrl, $aInjectedParams = []) {

		foreach ($this->_aParsedRouteParams as $iKey => $aParam) {
			if ($aInjectedParams && isset($aInjectedParams[$aParam['name']])) {
				$sValue = $aInjectedParams[$aParam['name']];
			}
			elseif (!$aInjectedParams && $this->_sSwitchCurrentLang
				&& isset($this->_aTranslateRouteParams[$this->_sSwitchCurrentLang])
				&& isset($this->_aTranslateRouteParams[$this->_sSwitchCurrentLang][$aParam['name']])) {
				$sValue = $this->_aTranslateRouteParams[$this->_sSwitchCurrentLang][$aParam['name']];
			}
			elseif (!$aInjectedParams && isset($this->_aRouteParamsGet[$aParam['name']])) {
				$sValue = $this->_aRouteParamsGet[$aParam['name']];
			}
			elseif(!empty($aParam['optional'])) {
				$sUrl = str_replace($aParam['optional'], '', $sUrl);
				continue;
			}
			else {
				self::_exception('Route required param not found for switch : ' . $aParam['name'], __LINE__, __METHOD__);
			}

			if(!preg_match("`$aParam[regex]`i", $sValue)) {
				self::_exception("Regex not match the actual route param : $aParam[name], regex : $aParam[regex], value : $sValue", __LINE__, __METHOD__);
			}

			if(!empty($aParam['optional'])) {
				$sUrl = str_replace($aParam['optional'], trim($aParam['optional'], '[]'), $sUrl);
			}

			$sUrl = str_replace($aParam['subject'], $sValue, $sUrl);
		}

		return $sUrl;

	}

}
