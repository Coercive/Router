<?php
namespace Coercive\Utility\Router\Entity;

use Closure;
use Coercive\Utility\Router\Parser;
use Exception;

/**
 * Class Route
 *
 * @package Coercive\Utility\Router\Entity
 * @link https://github.com/Coercive/Router
 *
 * @author Anthony Moral <contact@coercive.fr>
 * @copyright 2022
 * @license MIT
 */
class Route
{
	/** @var string Route ID */
	private string $id;

	/** @var string Targeted language */
	private string $lang;

	/** @var string Targeted controller */
	private string $controller;

	/** @var array Parameters to rewrite */
	private array $rewrites = [];

	/** @var array Query parameters (not rewritten) */
	private array $queries = [];

	/** @var bool Fullscheme Url */
	private bool $full = false;

	/** @var array Route raw data */
	private array $route;

	/** @var string */
	private string $baseUrl = '';

	/** @var Exception[] */
	private array $exceptions = [];

	/** @var Closure|null customer debug function that get Exception as parameter like : function(Exception $e) { ... } */
	private ? Closure $debug = null;

	/**
	 * Route accessor : original path
	 *
	 * @return string
	 */
	private function getDataOriginal(): string
	{
		return $this->route['routes'][$this->lang]['original'] ?? '';
	}

	/**
	 * Route accessor : rewrite path
	 *
	 * @return string
	 */
	private function getDataRewrite(): string
	{
		return $this->route['routes'][$this->lang]['rewrite'] ?? '';
	}

	/**
	 * Route accessor : params list
	 * @return array
	 */
	private function getDataParams(): array
	{
		return $this->route['routes'][$this->lang]['params'] ?? [];
	}

	/**
	 * REWRITE URL WITH PARAMS
	 *
	 * @return string
	 */
	private function rewrite(): string
	{
		# Original url to rewrite
		$url = $this->getDataOriginal();

		# Rewrite params if needed
		foreach ($this->getDataParams() as $key => $param) {

			# If parameter is set
			$value = $this->rewrites[$param['name']] ?? null;
			if (null !== $value && !is_bool($value) && '' !== $value) {

				# Check value format
				if(!preg_match("`^$param[regex]$`i", $value)) {
					$e = new Exception("Route param regex not match, name: $param[name], regex: $param[regex], value: $value, lang: {$this->lang}, id: {$this->id}");
					$this->addException($e);
					return '';
				}

				# Trim optional brackets
				if($param['optional']) {
					$url = str_replace($param['optional'], trim($param['optional'], '[]'), $url);
				}

				# Inject param
				$url = str_replace($param['subject'], $value, $url);
			}

			# Clear optional empty param
			elseif(!empty($param['optional'])) {
				$url = str_replace($param['optional'], '', $url);
				continue;
			}

			# Error : forgotten param
			else {
				$e = new Exception("Route required param not found for rewrite url : $param[name], lang: {$this->lang}, id: {$this->id}");
				$this->addException($e);
				return '';
			}
		}

		# Builded url
		return $url;
	}

	/**
	 * Add Exception for external debug handler
	 *
	 * @param Exception $e
	 * @return $this
	 */
	private function addException(Exception $e): Route
	{
		$this->exceptions[] = $e;
		if(null !== $this->debug) {
			($this->debug)($e);
		}
		return $this;
	}

	/**
	 * Route constructor.
	 *
	 * @param string $id
	 * @param string $lang
	 * @param array $route
	 * @return void
	 */
	public function __construct(string $id, string $lang, array $route)
	{
		$this->id = $id;
		$this->lang = $lang;
		$this->route = $route;

		# Récupération automatique depuis le build
		$this->controller = strval($route['controller'] ?? '');
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
	public function debug(Closure $function = null): Route
	{
		$this->debug = $function;
		return $this;
	}

	/**
	 * Some errors
	 *
	 * @return bool
	 */
	public function hasErrors(): bool
	{
		return (bool) $this->exceptions;
	}

	/**
	 * Export errors
	 *
	 * @return Exception[]
	 */
	public function getExceptions(): array
	{
		return $this->exceptions;
	}

	/**
	 * Autoformated url
	 *
	 * @return string
	 */
	public function __toString(): string
	{
		return $this->getUrl();
	}

	/**
	 * Build url
	 *
	 * @return string
	 */
	public function getUrl(): string
	{
		# Empty / no route / default object
		if(!$this->id) {
			return '';
		}

		# No route given for this language
		if(!in_array($this->lang, $this->route['langs'] ?? [])) {
			$e = new Exception('No route defined for language "' . $this->lang . '" for id ' . $this->id);
			$this->addException($e);
			return '';
		}

		# Basic / or has parameters to rewrite
		$url = $this->rewrite();

		# Params in query (not rewritten)
		$query = $this->queries ? http_build_query($this->queries) : '';

		# Url Full scheme
		$base = $this->full ? $this->baseUrl : '';

		# Delete lost params
		$url = Parser::deleteLostParams($url);

		# Recomposed url
		$url = $query ? $url . '?' . $query : $url;
		$url = trim($url, '/-');
		return $base . '/' . $url;
	}

	/**
	 * Set base url for fullscheme
	 *
	 * @param string $url
	 * @return Route
	 */
	public function setBaseUrl(string $url): Route
	{
		$this->baseUrl = $url;
		return $this;
	}

	/**
	 * Get current ID
	 *
	 * @return string
	 */
	public function getId(): string
	{
		return $this->id;
	}

	/**
	 * Get lang
	 *
	 * @return string
	 */
	public function getLang(): string
	{
		return $this->lang;
	}

	/**
	 * Set lang
	 *
	 * @param string $data
	 * @return $this
	 */
	public function setLang(string $data): Route
	{
		$this->lang = $data;
		return $this;
	}

	/**
	 * Controller
	 *
	 * @return string
	 */
	public function getController(): string
	{
		return $this->controller;
	}

	/**
	 * Get rewrite url parameters
	 *
	 * @return array
	 */
	public function getRewriteParams(): array
	{
		return $this->rewrites;
	}

	/**
	 * Set rewrite url parameters
	 *
	 * @param array $data
	 * @return $this
	 */
	public function setRewriteParams(array $data): Route
	{
		$this->rewrites = $data;
		return $this;
	}

	/**
	 * Get query url parameters
	 * (no rewrite : after '?')
	 *
	 * @return array
	 */
	public function getQueryParams(): array
	{
		return $this->queries;
	}

	/**
	 * Set query url parameters
	 * (no rewrite : after '?')
	 *
	 * @param array $data
	 * @return $this
	 */
	public function setQueryParams(array $data): Route
	{
		$this->queries = $data;
		return $this;
	}

	/**
	 * Auto retrieve param
	 *
	 * @param string $name
	 * @return mixed
	 */
	public function getParam(string $name)
	{
		if(isset($this->rewrites[$name])) {
			return $this->rewrites[$name];
		}
		if(isset($this->queries[$name])) {
			return $this->queries[$name];
		}
		return null;
	}

	/**
	 * Get full scheme url status
	 *
	 * @return bool
	 */
	public function isFullScheme(): bool
	{
		return $this->full;
	}

	/**
	 * Activate full scheme url
	 *
	 * @param bool $status
	 * @return $this
	 */
	public function setFullScheme(bool $status): Route
	{
		$this->full = $status;
		return $this;
	}

	/**
	 * Route accessor : options
	 *
	 * @return array
	 */
	public function getOptions(): array
	{
		return $this->route['routes'][$this->lang]['options'] ?? [];
	}

	/**
	 * Route accessor : one option by name
	 *
	 * @param string $name
	 * @return mixed
	 */
	public function getOption(string $name)
	{
		return $this->route['routes'][$this->lang]['options'][$name] ?? null;
	}
}