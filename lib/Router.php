<?php
namespace Coercive\Utility\Router;

use Closure;
use Exception;
use Coercive\Security\Xss\XssUrl;
use Coercive\Utility\Router\Entity\Route;

/**
 * Router
 *
 * La simplicité est la sophistication suprême.
 * Léonard de Vinci
 *
 * @package Coercive\Utility\Router
 * @link https://github.com/Coercive/Router
 *
 * @author Anthony Moral <contact@coercive.fr>
 * @copyright 2020
 * @license MIT
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

	/** @var Exception[] */
	private $exceptions = [];

	/** @var Closure customer debug function that get Exception as parameter like : function(Exception $e) { ... } */
	private $debug = null;

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
	 * @throws Exception
	 */
	private function run()
	{
		foreach($this->routes as $id => $item) {
			foreach($item['routes'] as $lang => $datas) {
				if($this->match($datas['regex'], $datas['methods'])) {
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
	 * @param array $methods
	 * @return bool
	 */
	private function match(string $pattern, array $methods): bool
	{
		if($methods && !in_array($this->getMethod(), $methods)) {
			return false;
		}
		if(!preg_match("`^$pattern$`i", $this->url, $matches)) {
			return false;
		}
		$intKeys = array_filter(array_keys($matches), 'is_numeric');
		$this->routeParamsGet = array_diff_key($matches, array_flip($intKeys));
		return true;
	}

	/**
	 * Add Exception for external debug handler
	 *
	 * @param Exception $e
	 * @return $this
	 */
	private function addException(Exception $e): Router
	{
		$this->exceptions[] = $e;
		if(null !== $this->debug) {
			($this->debug)($e);
		}
		return $this;
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

		# INPUT SERVER
		$this->initInputServer();

		# AJAX
		$this->initAjaxDetection();

		# PARAMS GET
		$this->initQueryString();

		# URL
		$this->url = Parser::clean($this->REQUEST_URI);

		# RUN
		$this->run();
	}

	/**
	 * Set a debug function
	 *
	 * It will log all given exceptions like :
	 * function(Exception $e) { ... }
	 *
	 * Can be reset with give no parameter
	 *
	 * @param Closure|null $function
	 * @return $this
	 */
	public function debug(Closure $function = null): Router
	{
		$this->debug = $function;
		return $this;
	}

	/**
	 * Get Exception list for external debug
	 *
	 * @return Exception[]
	 */
	public function getExceptions(): array
	{
		return $this->exceptions;
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
	public function getMethod(): string
	{
		return $this->REQUEST_METHOD;
	}

	/**
	 * THE CURRENT REQUEST SCHEME
	 *
	 * @return string
	 */
	public function getProtocol(): string
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
		return (new XssUrl)->setUrl($this->getRawCurrentURL($full))->getFiltered();
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
		if($sheme === 'auto') {
			return ($this->getProtocol() ? $this->getProtocol() . '://' : '') . $this->HTTP_HOST;
		}
		# Automatic
		elseif($sheme === '//') {
			return '//' . $this->HTTP_HOST;
		}
		# User set
		else {
			$sheme = rtrim(strtolower($sheme), '/ ');
			return in_array($sheme, self::REQUEST_SCHEME, true) ? $sheme . '://' . $this->HTTP_HOST : $this->HTTP_HOST;
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
		$this->HTTP_HOST = (string) preg_replace('`^('.implode('|', self::REQUEST_SCHEME).')://`', '', $host);
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
		$this->REQUEST_SCHEME = in_array($sheme, self::REQUEST_SCHEME, true) ? $sheme : '';
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
	 * INIT SUPER GLOBAL $_GET MERGE
	 *
	 * @return Router
	 */
	public function overloadGET(): Router
	{
		$_GET = array_replace_recursive([], $_GET, $this->queryParamsGet, $this->routeParamsGet);
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
		foreach($list as $lang => $params) {
			foreach($params as $id => $param) {
				$this->overloadedRouteParams[$lang][$id] = urlencode($param);
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
	 * @return Route
	 * @throws Exception
	 */
	public function switchLang(string $lang, bool $full = false): Route
	{
		# Load entity
		$route = $this->route($this->id, $lang);
		$route->setQueryParams($this->queryParamsGet);
		$route->setFullScheme($full);
		$route->setBaseUrl($this->getBaseUrl());

		# Rewrite params (with overload if setted)
		if($data = $this->routeParamsGet) {
			if(isset($this->overloadedRouteParams[$lang])) {
				$data = array_replace_recursive($data, $this->overloadedRouteParams[$lang]);
			}
		}
		$route->setRewriteParams($data);
		return $route;
	}

	/**
	 * Give actual url (or in other lang) and replace rewrite and get params
	 *
	 * @param string $lang
	 * @param array $rewrite
	 * @param array $get
	 * @param bool $full [optional]
	 * @return Route
	 * @throws Exception
	 */
	public function switch(string $lang, array $rewrite, array $get, bool $full = false): Route
	{
		# Load entity
		$route = $this->route($this->id, $lang);
		$route->setFullScheme($full);

		# Query params (delete null values)
		$data = array_replace($this->queryParamsGet, $get);
		$data = array_filter($data, function ($v) { return null !== $v; } );
		$route->setQueryParams($data);

		# Rewrite params (with overload and current if setted)
		if($data = $this->routeParamsGet) {
			if(isset($this->overloadedRouteParams[$lang])) {
				$data = array_replace($data, $this->overloadedRouteParams[$lang]);
			}
			$data = array_replace($data, $rewrite);
		}
		$route->setRewriteParams($data);
		return $route;
	}

	/**
	 * Url fabric
	 *
	 * @param string $id
	 * @param string $lang [optional]
	 * @param array $rewrite [optional]
	 * @param array $get [optional]
	 * @param bool $full [optional]
	 * @return Route
	 * @throws Exception
	 */
	public function url(string $id, string $lang = '', array $rewrite = [], array $get = [], bool $full = false): Route
	{
		$route = $this->route($id, $lang);
		$route->setQueryParams($get);
		$route->setRewriteParams($rewrite);
		$route->setFullScheme($full);
		return $route;
	}

	/**
	 * Get Route
	 *
	 * @param string $id
	 * @param string $lang
	 * @return Route
	 */
	public function route(string $id, string $lang = ''): Route
	{
		$lang = $lang ?: $this->lang;
		$route = new Route($lang, $this->routes[$id] ?? []);
		$route->debug($this->debug);
		$route->setBaseUrl($this->getBaseUrl());
		return $route;
	}
}