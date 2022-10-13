<?php
namespace Coercive\Utility\Router;

use Closure;
use Coercive\Utility\Router\Entity\Filter;
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
 * @copyright 2022
 * @license MIT
 */
class Router
{
	const REQUEST_SCHEME = [
		'http', 'https', 'ftp'
	];

	/** @var string from INPUT_SERVER data */
	private string $REQUEST_SCHEME;
	private string $DOCUMENT_ROOT;
	private string $HTTP_HOST;
	private string $REQUEST_METHOD;
	private string $REQUEST_URI;
	private string $SCRIPT_URI;
	private string $SCRIPT_URL;
	private string $QUERY_STRING;
	private string $BASE_URL;

	/** @var Route[][]  */
	private array $multiton = [
		'find' => []
	];

	/** @var array From Parser */
	private array $routes;

	/** @var array Overload params for switch url lang */
	private array $overloadedRouteParams = [];

	/** @var Route The current detected route for current url */
	private Route $current;

	/** @var bool is an ajax request based on HTTP_X_REQUESTED_WITH */
	private bool $ajax = false;

	/** @var string request type accepted */
	private string $httpAccept = '';

	/** @var Exception[] list of errors throwed in process */
	private array $exceptions = [];

	/** @var Closure|null customer debug function that get Exception as parameter like : function(Exception $e) { ... } */
	private ? Closure $debug = null;

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
		$this->REQUEST_METHOD = strtoupper(filter_input(INPUT_SERVER, 'REQUEST_METHOD', FILTER_SANITIZE_FULL_SPECIAL_CHARS));

		# INPUT SERVER REQUEST
		$this->REQUEST_URI = trim(urldecode(filter_input(INPUT_SERVER, 'REQUEST_URI', FILTER_SANITIZE_URL)), '/');
		$this->SCRIPT_URI = trim(urldecode(filter_input(INPUT_SERVER, 'SCRIPT_URI', FILTER_SANITIZE_URL)), '/');
		$this->SCRIPT_URL = trim(urldecode(filter_input(INPUT_SERVER, 'SCRIPT_URL', FILTER_SANITIZE_URL)), '/');
		$this->QUERY_STRING = urldecode(filter_input(INPUT_SERVER, 'QUERY_STRING', FILTER_SANITIZE_URL));
		$this->setBaseUrl();
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
	 * @param string $defaultLang [optional]
	 * @return void
	 */
	public function __construct(Parser $parser, string $defaultLang = '')
	{
		# Default empty route
		$this->current = new Route('', $defaultLang, []);

		# Init input server data
		$this->initInputServer();

		# Init ajax detection from input server
		$this->initAjaxDetection();

		# Bind user routes
		try {
			$this->routes = $parser->get();
		}
		catch (Exception $e) {
			$this->addException($e);
		}
	}

	/**
	 * Start process : launch of routes detection
	 *
	 * @return $this
	 */
	public function run(): Router
	{
		# Start route processing
		$route = $this->find($this->REQUEST_URI);
		if($route->getId()) {
			$this->current = $route;
			$this->current->setQueryParams(Parser::queryParams($this->QUERY_STRING));
		}
		return $this;
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
	 * The current detected route for current url
	 *
	 * @return Route
	 */
	public function current(): Route
	{
		return $this->current;
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
	 * @param bool $status
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
	public function export(): array
	{
		return $this->routes;
	}

	/**
	 * SERVER SCRIPT URI
	 *
	 * @return string
	 */
	public function getScriptUri(): string
	{
		return $this->SCRIPT_URI;
	}

	/**
	 * SERVER SCRIPT URL
	 *
	 * @return string
	 */
	public function getScriptUrl(): string
	{
		return $this->SCRIPT_URL;
	}

	/**
	 * GET RAW CURRENT URL
	 *
	 * @param bool $full [optional]
	 * @return string
	 */
	public function getRawCurrentURL(bool $full = false): string
	{
		$uri = '/' . $this->REQUEST_URI;
		return $full ? $this->getBaseUrl() . $uri : $uri;
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
	 * @return string
	 */
	public function getBaseUrl(): string
	{
		return $this->BASE_URL;
	}

	/**
	 * SET BASE URL
	 *
	 * @param string|null $custom [optional]
	 * @return $this
	 */
	public function setBaseUrl(? string $custom = null): Router
	{
		if(null !== $custom) {
			$this->BASE_URL = $custom;
		}
		else {
			$this->BASE_URL = $this->buildBaseUrl();
		}
		return $this;
	}

	/**
	 * GET CURRENT BASE URL
	 *
	 * @param bool $inheritProtocol [optional]
	 * @param string|null $customProtocol [optional]
	 * @return string
	 */
	public function buildBaseUrl(bool $inheritProtocol = false, ? string $customProtocol = null): string
	{
		$protocol = $this->getProtocol();
		if($inheritProtocol) {
			$protocol = '//';
		}
		if(null !== $customProtocol && ('//' === $customProtocol || in_array($customProtocol, self::REQUEST_SCHEME, true))) {
			$protocol = $customProtocol;
		}
		return $this->BASE_URL = $protocol . ('//' === $protocol ? '' : '://') . trim($this->getHost(), '/ ');
	}

	/**
	 * FORCE HOST
	 *
	 * @param string $host
	 * @return $this
	 */
	public function setHost(string $host): Router
	{
		if(false !== $pos = strpos($host, '//')) {
			$host = substr($host, $pos + 2);
		}

		$host = trim($host, '/ ');

		if(Parser::validateHostName($host)) {
			$this->HTTP_HOST = $host;
		}
		return $this;
	}

	/**
	 * FORCE PROTOCOL
	 *
	 * @param string $sheme
	 * @return $this
	 */
	public function setProtocol(string $sheme): Router
	{
		if('//' === $sheme || in_array($sheme, self::REQUEST_SCHEME, true)) {
			$this->REQUEST_SCHEME = $sheme;
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
		$_GET = array_replace_recursive([], $_GET, $this->current->getQueryParams(), $this->current->getRewriteParams());
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
	 * Search route from input url
	 *
	 * @param string $url
	 * @return Route
	 */
	public function find(string $url): Route
	{
		$hash = sha1($url);
		if(array_key_exists($hash, $this->multiton['find'])) {
			return $this->multiton['find'][$hash];
		}

		# Filter get parameter
		$queryParamsGet = Parser::queryParams($url, true);

		# Clean input url
		$url = Parser::clean($url);

		# Compare with all routes
		$route = null;
		foreach($this->routes as $id => $item) {

			# Comparison of access methods.
			if($item['methods'] && !in_array($this->getMethod(), $item['methods'])) {
				continue;
			}

			# Check by lang
			foreach($item['routes'] as $lang => $datas) {

				# Match route and retrieve parameters
				if(!preg_match("`^$datas[regex]$`i", $url, $matches)) {
					continue;
				}

				# Filter rewritten parameters
				$intKeys = array_filter(array_keys($matches), 'is_numeric');
				$routeParamsGet = array_diff_key($matches, array_flip($intKeys));

				# Prepare route
				$route = new Route($id, $lang, $item);
				$route->debug($this->debug);
				$route->setBaseUrl($this->getBaseUrl());
				$route->setRewriteParams($routeParamsGet);
				$route->setQueryParams($queryParamsGet);
				break;
			}
		}

		# Memorizes in multiton and return loaded or empty route
		return $this->multiton['find'][$hash] = null === $route ? new Route('', '', []) : $route;
	}

	/**
	 * GIVE ACTUAL URL IN OTHER LANG
	 *
	 * @param string $lang
	 * @param bool $full [optional]
	 * @return Route
	 */
	public function switchLang(string $lang, bool $full = false): Route
	{
		# Load entity
		$route = $this->route($this->current->getId(), $lang);
		$route->setQueryParams($this->current->getQueryParams());
		$route->setFullScheme($full);
		$route->setBaseUrl($this->getBaseUrl());

		# Rewrite params (with overload if setted)
		if($data = $this->current->getRewriteParams()) {
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
	 */
	public function switch(string $lang, array $rewrite, array $get, bool $full = false): Route
	{
		# Load entity
		$route = $this->route($this->current->getId(), $lang);
		$route->setFullScheme($full);

		# Query params (delete null values)
		$data = array_replace($this->current->getQueryParams(), $get);
		$data = array_filter($data, function ($v) { return null !== $v; } );
		$route->setQueryParams($data);

		# Rewrite params (with overload and current if setted)
		if($data = $this->current->getRewriteParams()) {
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
		$lang = $lang ?: $this->current->getLang();
		$route = new Route($id, $lang, $this->routes[$id] ?? []);
		$route->debug($this->debug);
		$route->setBaseUrl($this->getBaseUrl());
		return $route;
	}

	/**
	 * Search routes from options field
	 *
	 * @param Filter $filter
	 * @return Route[]
	 */
	public function filter(Filter $filter): array
	{
		$filters = $filter->export();
		$lang = $filters['lang'] ?: $this->current->getLang();

		# Compare with all routes
		$routes = [];
		foreach($this->routes as $id => $item) {

			# Check access method
			if($filters['methods']) {
				$founded = false;
				foreach($filters['methods'] as $method) {
					if(in_array($filters['methods'], $item['methods'])) {
						$founded = true;
					}
				}
				if(!$founded) {
					continue;
				}
			}

			# Check options
			foreach ($filters['options'] as $option) {
				$el = $item['options'][$option['label']] ?? null;
				if(null === $el) {
					continue 2;
				}
				if(is_array($el)) {
					continue 2;
				}
				settype($el, $option['type']);
				if($el !== $option['value']) {
					continue 2;
				}
			}

			# Prepare route
			$route = new Route($id, $lang, $item);
			$route->debug($this->debug);
			$route->setBaseUrl($this->getBaseUrl());
			$routes[] = $route;
		}
		return $routes;
	}
}