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
 * @copyright   2019 Anthony Moral
 * @license 	MIT
 */
class Ctrl
{
	/** @var string */
	private $defaultController = '';

	/** @var array */
	private $allowedNamespaces = [];

	/** @var mixed */
	private $app = null;

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
		if($namespace) {
			$this->allowedNamespaces[sha1($namespace)] = $namespace;
		}
		return $this;
	}

	/**
	 * SET ALLOWED NAMESPACES
	 *
	 * Verify if multiples namespaces start with this allowed path
	 *
	 * @param string[] $namespaces
	 * @return Ctrl
	 */
	public function setAllowedNamespaces(array $namespaces): Ctrl
	{
		foreach ($namespaces as $namespace) {
			if(!$namespace || !is_string($namespace)) { continue; }
			$this->allowedNamespaces[sha1($namespace)] = $namespace;
		}
		return $this;
	}

	/**
	 * SET APP TO INJECT
	 *
	 * @param mixed $app
	 * @return Ctrl
	 */
	public function setApp($app): Ctrl
	{
		$this->app = $app;
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
		foreach ($this->allowedNamespaces as $namespace) {
			if($namespace && 0 !== strpos($controller, $namespace)) {
				throw new CtrlException(CtrlException::NAMESPACE_NOT_ALLOWED . $class);
			}
		}

		# Not callable : 500
		if(!is_callable([$controller, $method])) {
			if($class === $this->defaultController || !$this->defaultController) {
				throw new CtrlException(CtrlException::DEFAULT_CONTROLLER_ERROR . $class);
			}
			return $this->load($this->defaultController);
		}

		# Call
		if($this->app) {
			return (new ReflectionMethod($controller, $method))->isStatic() ? $controller::{$method}($this->app) : (new $controller($this->app))->{$method}($this->app);
		}
		else {
			return (new ReflectionMethod($controller, $method))->isStatic() ? $controller::{$method}() : (new $controller())->{$method}();
		}
	}
}
