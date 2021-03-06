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
    private $controller = self::DEFAULT_CONTROLLER_LABEL;

    /** @var string */
    private $options = self::DEFAULT_OPTIONS_LABEL;

    /** @var string */
    private $methods = self::DEFAULT_OPTION_METHODS_LABEL;

    /** @var string Additional path between the route and the domain or ip */
    private $basepath = '';

    /** @var array External Source Routes */
    private $source = [];

    /** @var array Internal Prepared Routes */
    private $routes = [];

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
        $url = trim($url, " \t\n\r\0\x0B/");
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
        $this->basepath = $basepath;
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

        # Prepare basepath
        $basepath = Parser::clean($this->basepath);

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
				$this->routes[$id]['methods'] = explode(' ', $methods);
			}

            # Add each language
            foreach($routes as $lang => $route) {

				# Exclude controller / options entry
				if($lang === $this->controller || $lang === $this->options) {
					continue;
				}

                # Clean routes
                $path = Parser::clean($route);
                $path = $path ? ($basepath ? $basepath . '/' : '') . $path : $basepath;

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