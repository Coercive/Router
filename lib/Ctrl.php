<?php
namespace Coercive\Utility\Router;

use Coercive\Utility\Router\Exception\CtrlException;
use ReflectionMethod;

/**
 * Ctrl
 *
 * @package		Coercive\Utility\Router
 * @link		@link https://github.com/Coercive/Router
 *
 * @author  	Anthony Moral <contact@coercive.fr>
 * @copyright   (c) 2016 - 2017 Anthony Moral
 * @license 	http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License
 */
class Ctrl {

	/** @var string */
	private $_sDefaultController = '';

	/** @var object */
	private $_oApp = null;

	/**
	 * SET DEFAULT CONTROLLER (ERROR 500)
	 *
	 * @param string $sDefault
	 * @return Ctrl
	 */
    public function setDefault($sDefault) {
		$this->_sDefaultController = $sDefault;
		return $this;
	}

	/**
	 * SET APP TO INJECT
	 *
	 * @param object $oApp
	 * @return Ctrl
	 */
	public function setApp($oApp) {
		$this->_oApp = $oApp;
		return $this;
	}

	/**
	 * Ctrl loader
	 *
	 * @param string $sControllerPath : ProjectCode\Controller::Method
	 * @return object
	 * @throws CtrlException
	 */
	public function load($sControllerPath) {

		# Verify Path
		if(!preg_match('`^(?P<project>[a-z0-9_]+)\\\(?P<controller>[\\\a-z0-9_]+)::(?P<method>[a-z0-9_]+)$`i', $sControllerPath, $aMatches)) {
			throw new CtrlException(CtrlException::CONTROLLER_PATTERN_ERROR . $sControllerPath);
		}
		$sController = $aMatches['project'] . '\\' . $aMatches['controller'];
		$sMethod = $aMatches['method'];

		# Not callable : 500
		if(!is_callable([$sController, $sMethod])) {
			if($sControllerPath === $this->_sDefaultController || !$this->_sDefaultController) {
				throw new CtrlException(CtrlException::DEFAULT_CONTROLLER_ERROR . $sControllerPath);
			}
			return $this->load($this->_sDefaultController);
		}

		# Call
		return (new ReflectionMethod($sController, $sMethod))->isStatic() ? $sController::{$sMethod}($this->_oApp) : (new $sController($this->_oApp))->{$sMethod}($this->_oApp);

	}

}
