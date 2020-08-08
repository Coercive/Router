<?php
namespace Coercive\Utility\Router;

use Exception;
use ReflectionMethod;
use ReflectionException;

/**
 * Ctrl
 *
 * @package Coercive\Utility\Router
 * @link https://github.com/Coercive/Router
 *
 * @author Anthony Moral <contact@coercive.fr>
 * @copyright 2020
 * @license MIT
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
	 * @throws Exception
	 * @throws ReflectionException
	 */
	public function load(string $class)
	{
		# No controller
		if(!$class) {
			if(!$this->defaultController) {
				throw new Exception('CtrlException : Can\'t load default ctrl ' . $class);
			}
			return $this->load($this->defaultController);
		}

		# Verify Path
		if(!preg_match('`^(?P<controller>[\\\a-z0-9_]+)::(?P<method>[a-z0-9_]+)$`i', $class, $matches)) {
			throw new Exception('CtrlException : Pattern don\'t match ' . $class);
		}

		# Bind
		$controller = $matches['controller'] ?? '';
		$method = $matches['method'] ?? '';

		# Verify allowed
		foreach ($this->allowedNamespaces as $namespace) {
			if($namespace && 0 !== strpos($controller, $namespace)) {
				throw new Exception('CtrlException : Namespace is not allowed ' . $class);
			}
		}

		# Not callable : 500
		if(!is_callable([$controller, $method])) {
			if($class === $this->defaultController || !$this->defaultController) {
				throw new Exception('CtrlException : Can\'t load default ctrl ' . $class);
			}
			return $this->load($this->defaultController);
		}

		# Detect if required App parameter
		$reflection = new ReflectionMethod($controller, $method);
		$methodExpectedApp = false;
		if($this->app && $reflection->getNumberOfParameters()) {
			foreach ($reflection->getParameters() as $parameter) {
				if ($parameter->getName() === 'app') {
					$methodExpectedApp = true;
				}
				break;
			}
		}

		# Call static
		if($reflection->isStatic()) {
			return $methodExpectedApp ? $controller::{$method}($this->app) : $controller::{$method}();
		}

		# Call instantiate
		else {
			# Detect if constructor required App parameter
			$constructorExpectedApp = false;
			if($this->app) {
				$constructor = $reflection->getDeclaringClass()->getConstructor();
				if ($constructor && $constructor->getNumberOfParameters()) {
					foreach ($constructor->getParameters() as $parameter) {
						if ($parameter->getName() === 'app') {
							$constructorExpectedApp = true;
						}
						break;
					}
				}
			}

			# Load with or without app
			$ctrl = $constructorExpectedApp ? new $controller($this->app) : new $controller();
			return $methodExpectedApp ? $ctrl->{$method}($this->app) : $ctrl->{$method}();
		}
	}
}