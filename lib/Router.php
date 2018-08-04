<?php
namespace Coercive\Utility\Router;

use Exception;
use Coercive\Utility\Globals\Globals;
use Coercive\Utility\Router\Exception\RouterException;

/**
 * Router
 *
 * La simplicité est la sophistication suprême.
 * Léonard de Vinci
 *
 * @package		Coercive\Utility\Router
 * @link		https://github.com/Coercive/Router
 *
 * @author  	Anthony Moral <contact@coercive.fr>
 * @copyright   (c) 2018 Anthony Moral
 * @license 	MIT
 */
class Router
{
	const REQUEST_SCHEME = [
		'http', 'https', 'ftp'
	];

	/** @var string INPUT_SERVER */
	private
		$REQUEST_SCHEME,
		$DOCUMENT_ROOT,
		$HTTP_HOST,
		$REQUEST_METHOD,
		$REQUEST_URI,
		$QUERY_STRING;

	/** @var Globals */
	private $Globals = null;

	/** @var Parser */
	private $_oParser = null;

	/** @var array From Parser */
	private $routes = [];

	/** @var string Current URI */
	private $url = null;

	/** @var array Not rewritten GET params (after '?' in url) */
	private $queryParamsGet = [];

	/** @var array Rewritten route GET params */
	private $routeParamsGet = [];

	/** @var array Overload params for switch url lang */
	private $overloadedRouteParams = [];

	/** @var string Current matched route ID */
	private $id = '';

	/** @var string Current matched route LANG */
	private $lang = '';

	/** @var string Current matched route CONTROLLER */
	private $ctrl = '';

	/** @var bool is an ajax request */
	private $ajax = false;

	/** @var string request type accepted */
	private $httpAccept = '';

	/**
	 * INIT INPUT SERVER
	 *
	 * @return void
	 */
	private function initInputServer()
	{
		# INPUT_SERVER
		$this->REQUEST_SCHEME = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
		$this->DOCUMENT_ROOT = (string) filter_input(INPUT_SERVER, 'DOCUMENT_ROOT', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
		$this->HTTP_HOST = (string) filter_input(INPUT_SERVER, 'HTTP_HOST', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
		$this->REQUEST_METHOD = (string) filter_input(INPUT_SERVER, 'REQUEST_METHOD', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

		# INPUT SERVER REQUEST
		$this->REQUEST_URI = trim(urldecode(filter_input(INPUT_SERVER, 'REQUEST_URI', FILTER_SANITIZE_URL)), '/');
		$this->QUERY_STRING = urldecode(filter_input(INPUT_SERVER, 'QUERY_STRING', FILTER_SANITIZE_URL));
	}

	/**
	 * INIT PARAM QUERY STRING
	 *
	 * @return void
	 */
	private function initQueryString()
	{
		# Array of params
		parse_str($this->QUERY_STRING, $array);
		$this->queryParamsGet = $array;
	}

	/**
	 * INIT AJAX REQUEST DETECTION
	 *
	 * @return void
	 */
	private function initAjaxDetection()
	{
		# The request is ajax
		$this->ajax = 'XMLHttpRequest' === filter_input(INPUT_SERVER, 'HTTP_X_REQUESTED_WITH', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

		# The return type allowed by request
		$type = (string) filter_input(INPUT_SERVER, 'HTTP_ACCEPT', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
		if (false !== strpos($type, 'text/html')) {
			$this->httpAccept = 'html';
		}
		elseif (false !== strpos($type, 'application/json')) {
			$this->httpAccept = 'json';
		}
		elseif (false !== strpos($type, 'application/xml')) {
			$this->httpAccept = 'xml';
		}
		else {
			$this->httpAccept = 'html';
		}
	}

	/**
	 * Start process : launch of routes detection
	 *
	 * @return void
	 * @throws RouterException
	 */
	private function run()
	{
		# SECURITY
		if(!$this->routes) { throw new RouterException('Router cant start. No routes avialable.'); }

		# PREPARE EACH ROUTE
		foreach($this->routes as $id => $item) {

			# EACH LANGUAGE
			foreach($item['routes'] as $lang => $datas) {

				# MATCH
				if($this->match($datas['regex'])) {
					$this->id = $id;
					$this->lang = $lang;
					$this->ctrl = $item['controller'];
					return;
				}
			}
		}
	}

	/**
	 * MATCH URL / ROUTE
	 *
	 * @param string $pattern
	 * @return bool
	 */
	private function match(string $pattern): bool
	{
		if(!preg_match("`^$pattern$`i", $this->url, $matches)) { return false; }
		$intKeys = array_filter(array_keys($matches), 'is_numeric');
		$this->routeParamsGet = array_diff_key($matches, array_flip($intKeys));
		return true;
	}

	/**
	 * INIT SUPER GLOBAL $_GET MERGE
	 *
	 * @return void
	 */
	private function initSuperGlobalGET()
	{
		$this->queryParamsGet = $this->Globals->autoFilterManualVar($this->queryParamsGet);
		$this->routeParamsGet = $this->Globals->autoFilterManualVar($this->routeParamsGet);
		$_GET = array_replace_recursive([], $_GET, $this->queryParamsGet, $this->routeParamsGet);
	}

	/**
	 * REWRITE URL WITH PARAMS
	 *
	 * @param string $id
	 * @param string $lang
	 * @param array $injectedParams
	 * @return string
	 * @throws RouterException
	 */
	private function rewriteUrl(string $id, string $lang, array $injectedParams): string
	{
		# Original url to rewrite
		$url = $this->routes[$id]['routes'][$lang]['original'];

		# Rewrite params if needed
		foreach ($this->routes[$id]['routes'][$lang]['params'] as $key => $param) {

			# EXIST
			if (isset($injectedParams[$param['name']]) && !is_bool($injectedParams[$param['name']]) && '' !== $injectedParams[$param['name']]) {

				# PARAM VAUE
				$value = $injectedParams[$param['name']];
				if(!preg_match("`^$param[regex]$`i", $value)) {
					throw new RouterException("Route param regex not match : $param[name], regex : $param[regex], value : $value");
				}

				# TRIM OPTIONAL BRACKETS
				if($param['optional']) {
					$url = str_replace($param['optional'], trim($param['optional'], '[]'), $url);
				}

				# INJECT PARAM
				$url = str_replace($param['subject'], $value, $url);
			}

			# OPTIONAL EMPTY PARAM
			elseif(!empty($param['optional'])) {
				$url = str_replace($param['optional'], '', $url);
				continue;
			}

			# FORGOTTEN PARAM
			else {
				throw new RouterException("Route required param not found for rewrite url : $param[name]");
			}

		}

		# Builded url
		return $url;
	}

	/**
	 * Coercive Router constructor.
	 *
	 * @param Parser $parser
	 * @return void
	 * @throws Exception
	 */
	public function __construct(Parser $parser)
	{
		# Bind user routes
		$this->_oParser = $parser;
		$this->routes = $parser->get();
		$this->Globals = new Globals;

		# INPUT SERVER
		$this->initInputServer();

		# AJAX
		$this->initAjaxDetection();

		# PARAMS GET
		$this->initQueryString();

		# URL
		$this->url = $parser->clean($this->REQUEST_URI);

		# RUN
		$this->run();

		# SET _GET
		$this->initSuperGlobalGET();
	}

	/**
	 * THE ID OF THE CURRENT ROUTE
	 *
	 * @return string
	 */
	public function getId(): string
	{
		return $this->id;
	}

	/**
	 * THE LANGUAGE OF THE CURRENT ROUTE
	 *
	 * @return string
	 */
	public function getLang(): string
	{
		return $this->lang;
	}

	/**
	 * THE CONTROLLER OF THE CURRENT ROUTE
	 *
	 * @return string
	 */
	public function getController(): string
	{
		return $this->ctrl;
	}

	/**
	 * THE CURRENT HOST
	 *
	 * @return string
	 */
	public function getHost(): string
	{
		return $this->HTTP_HOST;
	}

	/**
	 * THE CURRENT REQUEST METHOD
	 *
	 * @return string
	 */
	public function getAccessMode(): string
	{
		return $this->REQUEST_METHOD;
	}

	/**
	 * THE CURRENT REQUEST SCHEME
	 *
	 * @return string
	 */
	public function getHttpMode(): string
	{
		return $this->REQUEST_SCHEME;
	}

	/**
	 * ROUTE PARAMS
	 *
	 * @return array
	 */
	public function getCurrentRouteParams(): array
	{
		return $this->routeParamsGet;
	}

	/**
	 * TRANSLATED ROUTE PARAMS
	 *
	 * @return array
	 */
	public function getOverloadedRouteParams(): array
	{
		return $this->overloadedRouteParams;
	}

	/**
	 * IS THE CURRENT REQUEST FROM AJAX METHOD
	 *
	 * @return bool
	 */
	public function isAjaxRequest(): bool
	{
		return $this->ajax;
	}

	/**
	 * SET CUSTOM AJAX RESQUEST MODE
	 *
	 * @param $status
	 * @return $this
	 */
	public function setAjaxRequest(bool $status): Router
	{
		$this->ajax = $status;
		return $this;
	}

	/**
	 * HTTP ACCEPT
	 *
	 * @return string
	 */
	public function getHttpAccept(): string
	{
		return $this->httpAccept;
	}

	/**
	 * SERVER ROOT PATH
	 *
	 * @return string
	 */
	public function getServerRootPath(): string
	{
		return $this->DOCUMENT_ROOT;
	}

	/**
	 * EXPORT PREPARED ROUTES
	 *
	 * For log debug or cache injection
	 *
	 * @return array
	 */
	public function getPreparedRoutesForCache(): array
	{
		return $this->routes;
	}

	/**
	 * GET RAW CURRENT URL
	 *
	 * @param bool $full [optional]
	 * @return string
	 */
	public function getRawCurrentURL(bool $full = false): string
	{
		return $full ? $this->getBaseUrl() . '/' . $this->REQUEST_URI : $this->REQUEST_URI;
	}

	/**
	 * GET CURRENT URL
	 *
	 * @param bool $full [optional]
	 * @return string
	 */
	public function getCurrentURL(bool $full = false): string
	{
		return htmlspecialchars($this->getRawCurrentURL($full));
	}

	/**
	 * GET CURRENT BASE URL
	 *
	 * @param string $sheme [optional]
	 * @return string
	 */
	public function getBaseUrl(string $sheme = 'auto'): string
	{
		# Self detect
		if($sheme === '1' || $sheme === 'auto') {
			return $this->getHttpMode() . '://' . $this->HTTP_HOST;
		}
		# Automatic
		elseif($sheme === '//') {
			return '//' . $this->HTTP_HOST;
		}
		# User set
		else {
			$sheme = rtrim(strtolower($sheme), '/ ');
			return in_array($sheme, self::REQUEST_SCHEME, true) ? $sheme . '://' . $this->HTTP_HOST : $sheme;
		}
	}
	
	/**
	 * FORCE HOST
	 *
	 * @param string $host
	 * @return $this
	 */
	public function forceHost(string $host): Router
	{
		$this->HTTP_HOST = $host;
		return $this;
	}

	/**
	 * FORCE SHEME
	 *
	 * @param string $sheme
	 * @return $this
	 */
	public function forceSheme(string $sheme): Router
	{
		$this->REQUEST_SCHEME = $sheme;
		return $this;
	}

	/**
	 * FORCE LANGUAGE
	 *
	 * @param string $lang
	 * @return $this
	 */
	public function forceLang(string $lang): Router
	{
		$this->lang = $lang;
		return $this;
	}

	/**
	 * FORCE GET PARAM EXIST
	 *
	 * @param array $list
	 * @return $this
	 */
	public function forceExistGET(array $list): Router
	{
		foreach ($list as $name) {
			if(!isset($_GET[$name])) { $_GET[$name] = null; }
		}
		return $this;
	}

	/**
	 * TRANSLATE PARAM FOR URL SWITCH
	 *
	 * @param array $list
	 * @return Router
	 */
	public function overloadParams(array $list): Router
	{
		if($list && is_array($list)) {
			foreach($list as $lang => $params) {
				foreach($params as $id => $param) {
					$this->overloadedRouteParams[$lang][$id] = urlencode($param);
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
	public function resetOverloadedParams(): Router
	{
		$this->overloadedRouteParams = [];
		return $this;
	}

	/**
	 * GIVE ACTUAL URL IN OTHER LANG
	 *
	 * @param string $lang
	 * @param bool $full [optional]
	 * @return string
	 * @throws Exception
	 */
	public function switchLang(string $lang, bool $full = false): string
	{
		# REQUESTED URL
		if(!$this->id || !isset($this->routes[$this->id])) { return ''; }
		if(!in_array($lang, $this->routes[$this->id]['langs'])) { return ''; }
		$url = $this->routes[$this->id]['routes'][$lang]['rewrite'];

		# PARAM GET
		$paramGet = empty($this->queryParamsGet) ? '' : http_build_query($this->queryParamsGet);

		# REWRITE PARAM GET
		if($this->routeParamsGet) {
			$params = $this->routeParamsGet;
			if(isset($this->overloadedRouteParams[$lang])) {
				$params = array_replace_recursive($params, $this->overloadedRouteParams[$lang]);
			}
			$url = $this->rewriteUrl($this->id, $lang, $params);
		}

		# DELETE LOST PARAMS
		$url = $this->_oParser->deleteLostParams($url);

		# RECOMPOSED URL
		$url = $paramGet ? $url . '?' . $paramGet : $url;
		$url = trim($url, '/-');
		return $full ? $this->getBaseUrl() . '/' . $url : $url;
	}

	/**
	 * URL FABRIC
	 *
	 * @param string $id
	 * @param string $lang [optional]
	 * @param array $rewriteParams [optional]
	 * @param array $queryParams [optional]
	 * @param mixed $fullUrlScheme [optional] (http, https, auto, true)
	 * @return string
	 * @throws Exception
	 */
	public function url(string $id, string $lang = '', array $rewriteParams = [], array $queryParams = [], $fullUrlScheme = ''): string
	{
		# AUTO LANG
		if(!$lang) { $lang = $this->lang; }

		# REQUESTED URL
		if(!isset($this->routes[$id]['langs']) || !in_array($lang, $this->routes[$id]['langs'])) { return ''; }
		$url = $this->routes[$id]['routes'][$lang]['rewrite'];

		# PARAM GET
		$paramGet = $queryParams ? http_build_query($queryParams) : '';

		# REWRITE PARAM
		if($rewriteParams && is_array($rewriteParams)) {
			$url = $this->rewriteUrl($id, $lang, $rewriteParams);
		}

		# FULL SCHEME
		if($fullUrlScheme) {
			$fullUrlScheme = $this->getBaseUrl() . '/';
		}

		# DELETE LOST PARAMS
		$url = $this->_oParser->deleteLostParams($url);

		# RECOMPOSED URL
		$url = $paramGet ? $url . '?' . $paramGet : $url;
		$url = trim($url, '/-');
		return $fullUrlScheme . $url;
	}
}