<?php
namespace Coercive\Utility\Router;

use Coercive\Utility\Router\Exception\LoaderException;
use Symfony\Component\Yaml\Parser as YamlParser;

/**
 * Loader
 *
 * @package		Coercive\Utility\Router
 * @link		@link https://github.com/Coercive/Router
 *
 * @author  	Anthony Moral <contact@coercive.fr>
 * @copyright   (c) 2016 - 2018 Anthony Moral
 * @license 	http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License
 */
class Loader {

	/**
	 * CACHE ARRAY
	 *
	 * @param array $aCachedRoutes
	 * @return Router
	 * @throws LoaderException
	 */
	static public function loadByCache($aCachedRoutes) {

		# SKIP ON ERROR
		if(!$aCachedRoutes || !is_array($aCachedRoutes)) { throw new LoaderException('Cached Routes empty or not array'); }

		# LOAD ROUTER
		$oParser = new Parser;
		$oParser->setFromCache($aCachedRoutes);
		return new Router($oParser);
	}

	/**
	 * ARRAY
	 *
	 * @param array $aRoutes
	 * @param string $sBasePath [optional]
	 * @return Router
	 * @throws LoaderException
	 */
	static public function loadByArray($aRoutes, $sBasePath = '') {

		# SKIP ON ERROR
		if(!$aRoutes || !is_array($aRoutes)) { throw new LoaderException('Routes empty or not array'); }

		# LOAD ROUTER
		$oParser = new Parser;
		$oParser->addRoutes($aRoutes);
		$oParser->setBasePath($sBasePath);
		return new Router($oParser);
	}

	/**
	 * YAML FILES
	 *
	 * @param mixed $mYamlFilePathList List of files paths
	 * @param string $sBasePath [optional]
	 * @return Router
	 * @throws LoaderException
	 */
	static public function loadByYaml($mYamlFilePathList, $sBasePath = '') {

		# SKIP ON ERROR
		if(!$mYamlFilePathList) { throw new LoaderException('No Yaml files found'); }

		# ARRAY
		if(is_string($mYamlFilePathList)) { $mYamlFilePathList = [$mYamlFilePathList]; }

		# PREPARE ROUTES
		$aRoutes = [];
		$oYamlParser = new YamlParser;
		foreach ($mYamlFilePathList as $mPrefix => $sYamlFilePath) {

			# No File : Skip
			if(!file_exists($sYamlFilePath)) { throw new LoaderException('File does not exist'); }

			# Parse
			$aCurrentYaml = $oYamlParser->parse(file_get_contents($sYamlFilePath));
			if(empty($aCurrentYaml) || !is_array($aCurrentYaml)) { continue; }

			# Add prefix
			if(!is_numeric($mPrefix)) {
				$aNew = [];
				foreach ($aCurrentYaml as $sKey => $aContent) {
					$aNew[$mPrefix.$sKey] = $aContent;
				}
				$aCurrentYaml = $aNew;
			}

			# Merge
			$aRoutes = array_merge_recursive($aRoutes, $aCurrentYaml);
		}

		# LOAD ROUTER
		return self::loadByArray($aRoutes, $sBasePath);

	}

	/**
	 * JSON FILES
	 *
	 * @param mixed $mJsonFilePathList List of files paths
	 * @param string $sBasePath [optional]
	 * @return Router
	 * @throws LoaderException
	 */
	static public function loadByJson($mJsonFilePathList, $sBasePath = '') {

		# SKIP ON ERROR
		if(!$mJsonFilePathList) { throw new LoaderException('No Json files found'); }

		# ARRAY
		if(is_string($mJsonFilePathList)) { $mJsonFilePathList = [$mJsonFilePathList]; }

		# PREPARE ROUTES
		$aRoutes = [];
		foreach ($mJsonFilePathList as $mPrefix => $sJsonFilePath) {

			# No File : Skip
			if(!file_exists($sJsonFilePath)) { throw new LoaderException('File does not exist'); }

			# Parse
			$aCurrentJson = json_decode(file_get_contents($sJsonFilePath));
			if(empty($aCurrentJson) || !is_array($aCurrentJson)) { continue; }

			# Add prefix
			if(!is_numeric($mPrefix)) {
				$aNew = [];
				foreach ($aCurrentJson as $sKey => $aContent) {
					$aNew[$mPrefix.$sKey] = $aContent;
				}
				$aCurrentJson = $aNew;
			}

			# Merge
			$aRoutes = array_merge_recursive($aRoutes, $aCurrentJson);
		}

		# LOAD ROUTER
		return self::loadByArray($aRoutes, $sBasePath);

	}

}
