<?php declare(strict_types=1);

use Coercive\Utility\Router\Loader;
use Coercive\Utility\Router\Parser;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
	public function testEmptyYamlPaths(): void
	{
		$this->expectException(Exception::class);

		Loader::loadByYaml([]);
	}

	public function testInvalidPaths(): void
	{
		$this->expectException(Exception::class);

		Loader::loadByYaml(['/path/does/not/exist.yaml']);
	}

	public function testLoadRoutes(): void
	{
		$router = Loader::loadByYaml([__DIR__ . '/routes.yml']);

		$router->debug(function ($e) {
			error_log(print_r($e->getMessage(), true));
		});

		$router->load();

		# TEST_ROOT
		$route = $router->route('TEST_ROOT', 'FR');
		$this->assertSame('TEST_ROOT', $route->getId());
		$this->assertSame('FR', $route->getLang());
		$this->assertSame('ControllerTest::Root', $route->getController());
		$this->assertSame(null, $route->getOption('unexpected'));
		$this->assertSame([], $route->getOptions());
		$this->assertSame('/fr/accueil', $route->getUrl());

		# TEST_CUSTOM_OPTIONS
		$route = $router->route('TEST_CUSTOM_OPTIONS', 'FR');
		$this->assertSame(null, $route->getOption('unexpected'));
		$this->assertSame(false, $route->getOption('test_option_1'));
		$this->assertSame(true, $route->getOption('test_option_2'));
		$this->assertSame(1234, $route->getOption('test_option_3'));
		$this->assertSame('bonjour', $route->getOption('test_option_4'));

		# TEST_REQUIRED_ARGS
		$route = $router->find('/fr/test-arguments/bonjour/1234567890');
		$route->setQueryParams(Parser::queryParams('hello=world'));
		$this->assertSame('TEST_REQUIRED_ARGS', $route->getId());
		$this->assertSame('FR', $route->getLang());
		$this->assertSame('ControllerTest::Arguments', $route->getController());
		$this->assertSame('/fr/test-arguments/bonjour/1234567890?hello=world', $route->getUrl());
		$this->assertSame('bonjour', $route->getParam('required1'));
		$this->assertSame('1234567890', $route->getParam('required2'));
		$this->assertSame('world', $route->getParam('hello'));
		$this->assertSame(['hello' => 'world'], $route->getQueryParams());
		$this->assertSame(['required1' => 'bonjour', 'required2' => '1234567890'], $route->getRewriteParams());

		$route->unsetQueryParam('hello');
		$this->assertSame('/fr/test-arguments/bonjour/1234567890', $route->getUrl());

		# TEST_OPTIONAL_ARGS
		$route = $router->find('/fr/test-arguments-optionel');
		$this->assertSame('TEST_OPTIONAL_ARGS', $route->getId());
		$this->assertSame('FR', $route->getLang());
		$this->assertSame('ControllerTest::Arguments', $route->getController());
		$this->assertSame('/fr/test-arguments-optionel', $route->getUrl());

		$route->setRewriteParam('optional', '1234567890');
		$this->assertSame('1234567890', $route->getParam('optional'));
		$this->assertSame(['optional' => '1234567890'], $route->getRewriteParams());
		$this->assertSame('/fr/test-arguments-optionel/optional-1234567890', $route->getUrl());

		$route->unsetRewriteParam('optional');
		$this->assertSame('/fr/test-arguments-optionel', $route->getUrl());

		$route->setLang('EN');
		$this->assertSame('/en/test-optional-arguments', $route->getUrl());

		# TEST_REQUIRED_AND_OPTIONAL_ARGS
		$route = $router->find('/fr/test-arguments-optionels-et-obligatoires/hello/12345/world/67890');
		$this->assertSame('TEST_REQUIRED_AND_OPTIONAL_ARGS', $route->getId());
		$this->assertSame('FR', $route->getLang());
		$this->assertSame('ControllerTest::Arguments', $route->getController());
		$this->assertSame('/fr/test-arguments-optionels-et-obligatoires/hello/12345/world/67890', $route->getUrl());
		$route->setRewriteParam('required1', 'hello');
		$route->setRewriteParam('required2', '12345');
		$route->setRewriteParam('optional1', 'world');
		$route->setRewriteParam('optional2', '67890');

		$route->unsetRewriteParam('optional1');
		$route->unsetRewriteParam('optional2');
		$this->assertSame('/fr/test-arguments-optionels-et-obligatoires/hello/12345', $route->getUrl());
	}
}

