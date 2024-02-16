<?php
namespace Coercive\Utility\Router;

use Closure;
use Exception;
use ReflectionMethod;

/**
 * Ctrl
 *
 * @package Coercive\Utility\Router
 * @link https://github.com/Coercive/Router
 *
 * @author Anthony Moral <contact@coercive.fr>
 * @copyright 2024
 * @license MIT
 */
class Ctrl
{
	/** @var string */
	private string $defaultControllerNotCallable = '';

	/** @var string */
	private string $defaultControllerNotFound = '';

	/** @var Closure|null */
	private ? Closure $hookNotCallable = null;

	/** @var Closure|null */
	private ? Closure $hookNotFound = null;

	/** @var array */
	private array $allowedNamespaces = [];

	/** @var mixed */
	private $app = null;

	/**
	 * LOAD DEFAULT CONTROLLER AND HOOK
	 *
	 * @param string $controller
	 * @param int $code
	 * @param string $message
	 * @return void
	 */
	private function hook(string $controller, int $code, string $message)
	{
		$hook = $code === 404 ? $this->hookNotFound : $this->hookNotCallable;
		$ctrl = $code === 404 ? $this->defaultControllerNotFound : $this->defaultControllerNotCallable;

		if($hook) {
			($hook)(new Exception($message, $code));
		}
		if($ctrl && $controller !== $ctrl) {
			$this->load($ctrl);
		}
	}

	/**
	 * SET DEFAULT CONTROLLER CRASH (ERROR 500)
	 *
	 * @param string $controller
	 * @param Closure|null $hook [optional]
	 * @return Ctrl
	 */
	public function setFallbackNotCallable(string $controller, ? Closure $hook = null): self
	{
		$this->defaultControllerNotCallable = $controller;
		$this->hookNotCallable = $hook;
		return $this;
	}

	/**
	 * SET DEFAULT CONTROLLER NOT FOUND (ERROR 404)
	 *
	 * @param string $controller
	 * @param Closure|null $hook [optional]
	 * @return Ctrl
	 */
	public function setFallbackNotFound(string $controller, ? Closure $hook = null): self
	{
		$this->defaultControllerNotFound = $controller;
		$this->hookNotFound = $hook;
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
	public function setAllowedNamespace(string $namespace): self
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
	public function setAllowedNamespaces(array $namespaces): self
	{
		foreach ($namespaces as $namespace) {
			$this->setAllowedNamespace($namespace);
		}
		return $this;
	}

	/**
	 * SET APP TO INJECT
	 *
	 * @param mixed $app
	 * @return Ctrl
	 */
	public function setApp($app): self
	{
		$this->app = $app;
		return $this;
	}

	/**
	 * Ctrl loader
	 *
	 * @param string $class : ProjectCode\Controller::Method
	 * @return void
	 */
	public function load(string $class)
	{
		# No controller
		if(!$class) {
			$msg = 'CtrlException : ' . ($this->defaultControllerNotFound ? 'Controller not found' : 'Default controller not found');
			$this->hook($class, 404, $msg);
			return;
		}

		# Verify Path
		if(!preg_match('`^(?P<controller>[\\\a-z\d_]+)::(?P<method>[a-z\d_]+)$`i', $class, $matches)) {
			$this->hook($class, 500, 'CtrlException : Pattern don\'t match ' . $class);
			return;
		}

		# Bind
		$controller = $matches['controller'] ?? '';
		$method = $matches['method'] ?? '';

		# Verify allowed
		foreach ($this->allowedNamespaces as $namespace) {
			if($namespace && 0 !== strpos($controller, $namespace)) {
				$this->hook($class, 500, 'CtrlException : Namespace is not allowed ' . $class);
				return;
			}
		}

		# Not callable : 500
		if(!is_callable([$controller, $method])) {
			$this->hook($class, 500, 'CtrlException : Controller is not callable ' . $class);
			return;
		}

		# Detect if required App parameter
		try {
			$reflection = new ReflectionMethod($controller, $method);
		}
		catch (Exception $e) {
			$this->hook($class, 500, 'CtrlException : ReflectionMethod crash ' . $class . ', with message : ' . $e->getMessage());
			return;
		}
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