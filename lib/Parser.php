<?php
namespace Coercive\Utility\Router;

use Exception;

/**
 * Parser
 *
 * @package Coercive\Utility\Router
 * @link https://github.com/Coercive/Router
 *
 * @author Anthony Moral <contact@coercive.fr>
 * @copyright 2020
 * @license MIT
 */
class Parser
{
    # PROPERTIES
    const DEFAULT_CONTROLLER_LABEL = '__';
    const DEFAULT_OPTIONS_LABEL = 'options';
    const DEFAULT_OPTION_METHODS_LABEL = 'methods';

    # REGEX
    const DEFAULT_MATCH_REGEX = '[^/]+';
    const REGEX_PARAM = '`\{([a-z_][a-z0-9_-]*)(?::([^{}]*(?:\{(?-1)\}[^{}]*)*))?\}`i';
	const REGEX_OPTION = '`\[([^\[\]]*#[0-9]+#[^\[\]]*)?\]`';
    const REGEX_OPTION_NUMBER = '`#([0-9]+)#`';
    const REGEX_LOST_OPTION = '`\[[^\]]*\]`';

    /** @var string */
    private string $controller = self::DEFAULT_CONTROLLER_LABEL;

    /** @var string */
    private string $options = self::DEFAULT_OPTIONS_LABEL;

    /** @var string */
    private string $methods = self::DEFAULT_OPTION_METHODS_LABEL;

    /** @var string Additional path between the route and the domain or ip */
    private string $basepath = '';

    /** @var array External Source Routes */
    private array $source = [];

    /** @var array Internal Prepared Routes */
    private array $routes = [];

	/**
	 * VALIDATE HOST NAME
	 *
	 * @source Sakari A. Maaranen https://stackoverflow.com/questions/106179/regular-expression-to-match-dns-hostname-or-ip-address/3824105#3824105
	 *
	 * @param string $host
	 * @return string
	 */
	static public function validateHostName(string $host): string
	{
		return (255 > strlen($host) && preg_match('`^(?:[a-z\d]|[a-z\d][a-z\d-]{0,61}[a-z\d])(?:\.(?:[a-z\d]|[a-z\d][a-z\d-]{0,61}[a-z\d]))*$`i', $host));
	}

    /**
     * URLS CLEANER
     *
     * Destroys spaces and slashes
     *
     * @param string $url
     * @return string
     */
    static private function fix(string $url): string
    {
		# DELETE INTERNAL SPACES
		$url = str_replace(' ', '', $url);

		# DELETE PARASITICS & SLASH
		return trim($url, " \t\n\r\0\x0B/");
    }

    /**
     * URLS CLEANER
     *
     * Destroys spaces and slashes at start and end, and all query parameters (after '?')
     *
     * @param string $url
     * @return string
     */
    static public function clean(string $url): string
    {
		$url = parse_url($url)['path'] ?? '';
        $url = self::fix($url);
        return false !== ($pos = strpos($url, '?')) ? substr($url, 0, $pos) : $url;
    }

    /**
     * DELETE LOST URL PARAMS
     *
     * @param string $url
     * @return string
     */
    static public function deleteLostParams(string $url): string
	{
        return (string) preg_replace(self::REGEX_LOST_OPTION, '', preg_replace(self::REGEX_PARAM, '', $url));
    }

	/**
	 * Get query params as array from query string or full url
	 *
	 * @param string $query
	 * @param bool $extract [optional] Extract query string from full url
	 * @return array
	 */
	static public function queryParams(string $query, bool $extract = false): array
	{
		if($extract) {
			if (false === ($pos = strpos($query, '?'))) {
				return [];
			}
			$query = substr($query, $pos + 1);
		}
		parse_str($query, $array);
		return $array;
	}

	/**
	 * PARSE ROUTE PARAMS
	 *
	 * @param string $path
	 * @return array
	 */
	private function build(string $path): array
	{
		# INIT
		$params = [];

		# GET PARAMS (skip if not)
		if(!preg_match_all(self::REGEX_PARAM, $path, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
			return [
				'regex' => $path,
				'rewrite' => $path,
				'params' => $params
			];
		}

		# PREPARE PARAMS & INIT CURRENT PREPARED ROOT
		foreach ($matches as $key => $set) {
			$params[$key] = [
				'key' => $key,
				'tag' => "#$key#",
				'subject' => $set[0][0],
				'name' => $set[1][0],
				'regex' => isset($set[2]) ? $set[2][0] : self::DEFAULT_MATCH_REGEX,
				'optional' => '',
				'optional_tag'=> ''
			];
			$path = str_replace($set[0][0], "#$key#", $path);
		}

		# PREPARE OPTIONALS PARAMS
		if(preg_match_all(self::REGEX_OPTION, $path, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
			foreach ($matches as $match) {
				preg_match_all(self::REGEX_OPTION_NUMBER, $match[1][0], $subs, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
				$replace = $match[0][0];
				foreach ($subs as $sub) {
					$replace = str_replace($sub[0][0], $params[$sub[1][0]]['subject'], $replace);
				}
				foreach ($subs as $sub) {
					$params[$sub[1][0]]['optional'] = $replace;
					$params[$sub[1][0]]['optional_tag'] = $match[0][0];
				}
			}
		}

		# PREPARE REWRITED PATH
		$rewrited = preg_replace(self::REGEX_OPTION, '(?:$1)?', $path);
		foreach ($params as $key => $param) {
			$rewrited = str_replace("#$key#", "(?P<$param[name]>$param[regex])", $rewrited);
		}

		# PREPARED ITEM
		return [
			'regex' => $rewrited,
			'rewrite' => $path,
			'params' => $params
		];
	}

	/**
	 * Customise controller label
	 *
	 * @param string $controller
	 * @return $this
	 */
	public function setControllerLabel(string $controller): Parser
	{
		$this->controller = $controller;
		return $this;
	}

	/**
	 * Customise options label
	 *
	 * @param string $options
	 * @return $this
	 */
	public function setOptionsLabel(string $options): Parser
	{
		$this->options = $options;
		return $this;
	}

	/**
	 * Customise options label
	 *
	 * @param string $methods
	 * @return $this
	 */
	public function setOptionMethodsLabel(string $methods): Parser
	{
		$this->options = $methods;
		return $this;
	}

	/**
     * SETTER BASE PATH
     * Additional path between the route and the domain or ip
     *
     * @param string $basepath
     * @return Parser
     */
    public function setBasePath(string $basepath): Parser
	{
        $this->basepath = Parser::clean($basepath);
        return $this;
    }

    /**
     * SET ALREADY PREPARED ROUTES (from cache)
     *
     * @param array $data
     * @return Parser
     */
    public function setFromCache(array $data): Parser
	{
		$this->routes = $data;
        return $this;
    }

    /**
     * ADD ROUTES
     *
     * @param array $data
     * @return Parser
     */
    public function addRoutes(array $data): Parser
	{
        $this->source = array_merge_recursive($this->source, $data);
        return $this;
    }

    /**
     * Start process : launch of routes detection
     *
     * @return array
     * @throws Exception
     */
    public function get(): array
	{
        # Already prepared or cached
        if($this->routes) {
        	return $this->routes;
        }

        # Error : not prepared and no source data
        if(!$this->source) {
        	throw new Exception('Parser cant start. No routes avialable.');
        }

        # Prepare each route
        foreach($this->source as $id => $routes) {

            # Skip on error
            if(empty($routes[$this->controller])) {
            	throw new Exception('Controller not found in : ' . $id);
            }

            # Prepare properties
            $this->routes[$id]['id'] = $id;
            $this->routes[$id]['controller'] = $routes[$this->controller];
            $this->routes[$id]['langs'] = [];
            $this->routes[$id]['routes'] = [];
            $this->routes[$id]['options'] = $routes[$this->options] ?? [];

            # Prepare methods
            $this->routes[$id]['methods'] = [];
            if($methods = $routes[$this->options][$this->methods] ?? '') {
				$this->routes[$id]['methods'] = explode(' ', strtoupper($methods));
			}

            # Add each language
            foreach($routes as $lang => $route) {

				# Exclude controller / options entry
				if($lang === $this->controller || $lang === $this->options) {
					continue;
				}

                # Clean routes
                $path = Parser::fix($route);
                $path = $path ? ($this->basepath ? $this->basepath . '/' : '') . $path : $this->basepath;

                # Prepare properties
                $this->routes[$id]['langs'][] = $lang;
                $this->routes[$id]['routes'][$lang]['lang'] = $lang;
                $this->routes[$id]['routes'][$lang]['original'] = $path;
                $this->routes[$id]['routes'][$lang] += $this->build($path);

            }
        }
        return $this->routes;
    }
}