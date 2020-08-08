<?php
namespace Coercive\Utility\Router;

use Exception;
use Symfony\Component\Yaml\Parser as YamlParser;

/**
 * Loader
 *
 * @package Coercive\Utility\Router
 * @link https://github.com/Coercive/Router
 *
 * @author Anthony Moral <contact@coercive.fr>
 * @copyright 2020
 * @license MIT
 */
class Loader
{
	/**
	 * CACHE ARRAY
	 *
	 * @param array $data
	 * @return Router
	 * @throws Exception
	 */
	static public function loadByCache(array $data): Router
	{
		# SKIP ON ERROR
		if(!$data) {
			throw new Exception('Cached Routes empty');
		}

		# LOAD ROUTER
		$parser = new Parser;
		$parser->setFromCache($data);
		return new Router($parser);
	}

	/**
	 * ARRAY
	 *
	 * @param array $routes
	 * @param string $basepath [optional]
	 * @return Router
	 * @throws Exception
	 */
	static public function loadByArray(array $routes, string $basepath = ''): Router
	{
		# SKIP ON ERROR
		if(!$routes) {
			throw new Exception('Routes empty or not array');
		}

		# LOAD ROUTER
		$parser = new Parser;
		$parser->addRoutes($routes);
		$parser->setBasePath($basepath);
		return new Router($parser);
	}

	/**
	 * YAML FILES
	 *
	 * @param array $paths List of yaml files paths
	 * @param string $basepath [optional]
	 * @return Router
	 * @throws Exception
	 */
	static public function loadByYaml(array $paths, string $basepath = ''): Router
	{
		# Skip on error
		if(!$paths) {
			throw new Exception('No Yaml files found');
		}

		# Prepare routes
		$routes = [];
		$parser = new YamlParser;
		foreach ($paths as $prefix => $path) {

			# No File : Skip
			if(!is_file($path)) {
				throw new Exception('File does not exist');
			}

			# Parse
			$yaml = $parser->parse(file_get_contents($path));
			if(!$yaml) {
				continue;
			}

			# Add prefix
			if(!is_numeric($prefix)) {
				$prefixed = [];
				foreach ($yaml as $id => $params) {
					$prefixed[$prefix.$id] = $params;
				}
				$yaml = $prefixed;
			}

			# Merge
			$routes = array_merge_recursive($routes, $yaml);
		}
		return self::loadByArray($routes, $basepath);
	}

	/**
	 * JSON FILES
	 *
	 * @param array $paths List of json files paths
	 * @param string $basepath [optional]
	 * @return Router
	 * @throws Exception
	 */
	static public function loadByJson(array $paths, string $basepath = ''): Router
	{
		# SKIP ON ERROR
		if(!$paths) {
			throw new Exception('No Json files found');
		}

		# PREPARE ROUTES
		$routes = [];
		foreach ($paths as $prefix => $path) {

			# No File : Skip
			if(!is_file($path)) {
				throw new Exception('File does not exist');
			}

			# Parse
			$json = json_decode(file_get_contents($path));
			if(!$json || !is_array($json)) {
				continue;
			}

			# Add prefix
			if(!is_numeric($prefix)) {
				$prefixed = [];
				foreach ($json as $id => $params) {
					$prefixed[$prefix.$id] = $params;
				}
				$json = $prefixed;
			}

			# Merge
			$routes = array_merge_recursive($routes, $json);
		}
		return self::loadByArray($routes, $basepath);
	}
}