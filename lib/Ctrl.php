<?php
namespace Coercive\Utility\Router;

use ReflectionMethod;
use ReflectionException;
use Coercive\Utility\Router\Exception\CtrlException;

/**
 * Ctrl
 *
 * @package		Coercive\Utility\Router
 * @link		https://github.com/Coercive/Router
 *
 * @author  	Anthony Moral <contact@coercive.fr>
 * @copyright   (c) 2018 Anthony Moral
 * @license 	MIT
 */
class Ctrl {

	/** @var string */
	private $defaultController = '';

	/** @var string */
	private $allowedNamespace = '';

	/** @var mixed */
	private $App = null;

	/**
	 * SET DEFAULT CONTROLLER (ERROR 500)
	 *
	 * @param string $default
	 * @return Ctrl
	 */
    public function setDefault(string $default): Ctrl
	{
		$this->defaultController = $default;
		return $this;
	}

	/**
	 * SET ALLOWED NAMESPACE
	 *
	 * Verify if namespace start with this allowed path
	 *
	 * @param string $namespace
	 * @return Ctrl
	 */
	public function setAllowedNamespace(string $namespace): Ctrl
	{
		$this->allowedNamespace = $namespace;
		return $this;
	}

	/**
	 * SET APP TO INJECT
	 *
	 * @param mixed $App
	 * @return Ctrl
	 */
	public function setApp($App): Ctrl
	{
		$this->App = $App;
		return $this;
	}

	/**
	 * Ctrl loader
	 *
	 * @param string $class : ProjectCode\Controller::Method
	 * @return mixed
	 * @throws CtrlException
	 * @throws ReflectionException
	 */
	public function load(string $class)
	{
		# No controller
		if(!$class) {
			if(!$this->defaultController) {
				throw new CtrlException(CtrlException::DEFAULT_CONTROLLER_ERROR . $class);
			}
			return $this->load($this->defaultController);
		}

		# Verify Path
		if(!preg_match('`^(?P<controller>[\\\a-z0-9_]+)::(?P<method>[a-z0-9_]+)$`i', $class, $matches)) {
			throw new CtrlException(CtrlException::CONTROLLER_PATTERN_ERROR . $class);
		}

		# Bind
		$controller = $matches['controller'] ?? '';
		$method = $matches['method'] ?? '';

		# Verify allowed
		if($this->allowedNamespace && 0 !== strpos($controller, $this->allowedNamespace)) {
			throw new CtrlException(CtrlException::NAMESPACE_NOT_ALLOWED . $class);
		}

		# Not callable : 500
		if(!is_callable([$controller, $method])) {
			if($class === $this->defaultController || !$this->defaultController) {
				throw new CtrlException(CtrlException::DEFAULT_CONTROLLER_ERROR . $class);
			}
			return $this->load($this->defaultController);
		}

		# Call
		if($this->App) {
			return (new ReflectionMethod($controller, $method))->isStatic() ? $controller::{$method}($this->App) : (new $controller($this->App))->{$method}($this->App);
		}
		else {
			return (new ReflectionMethod($controller, $method))->isStatic() ? $controller::{$method}() : (new $controller())->{$method}();
		}
	}
}
