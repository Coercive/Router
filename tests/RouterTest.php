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

	public function testServerFixtures(): void
	{
		$router = Loader::loadByArray([
			'TEST_FIXTURES' => [
				'__' => 'ControllerTest::Fixtures',
				'FR' => '/',
				'EN' => '/',
			]
		], '');

		$router->fixtures();

		# TEST RUNNING
		$router->run();
		if($exceptions = $router->getExceptions()) {
			foreach ($exceptions as $e) {
				throw $e;
			}
		}

		# TEST LOADING CURRENT ROUTE
		$this->assertSame('TEST_FIXTURES', $router->current()->getId());

		# TEST BASIC FIXTURES
		$this->assertSame('//123.45.67.89', $router->buildBaseUrl(true));
		$this->assertSame('ftp://123.45.67.89', $router->buildBaseUrl(false, 'ftp'));

		$this->assertSame('https://123.45.67.89', $router->buildBaseUrl());
		$this->assertSame('https://123.45.67.89', $router->getBaseUrl());

		$this->assertSame('123.45.67.89', $router->getHost());
		$this->assertSame('/server/root/path', $router->getServerRootPath());
		$this->assertSame('GET', $router->getMethod());
		$this->assertSame('https', $router->getProtocol());
		$this->assertSame('/', $router->getRawCurrentURL());
		$this->assertSame('/', $router->getCurrentURL());
		$this->assertSame('https://123.45.67.89/', $router->getCurrentURL(true));
		$this->assertSame('html', $router->getHttpAccept());
		$this->assertSame('https://test.website.com/', $router->getScriptUri());
		$this->assertSame('/', $router->getScriptUrl());
		$this->assertSame('test.website.com', $router->getServerName());
		$this->assertSame(true, $router->isAjaxRequest());

		# ADVANCED FIXTURES
		$router->fixtures([
			'HTTP_HOST' => '123.45.67.89:123',
		]);
		$this->assertSame('123.45.67.89', $router->getHost());

		$router->setAjaxRequest(false);
		$this->assertSame(false, $router->isAjaxRequest());

		$router->setBaseUrl('https://hello.world.test');
		$this->assertSame('https://hello.world.test/', $router->getCurrentURL(true));
		$router->setBaseUrl();
		$this->assertSame('https://123.45.67.89/', $router->getCurrentURL(true));

		$router->setHost('1.2.3.4');
		$this->assertSame('1.2.3.4', $router->getHost());
		$router->setHost('new.host.test');
		$this->assertSame('new.host.test', $router->getHost());

		$router->setProtocol('http');
		$this->assertSame('http', $router->getProtocol());
		$router->setProtocol('unexpected');
		$this->assertSame('http', $router->getProtocol());
		$router->setProtocol('ftp');
		$this->assertSame('ftp', $router->getProtocol());
		$router->setProtocol('https');
		$this->assertSame('https', $router->getProtocol());

		$router->current()->setRewriteParam('hello', 'world');
		$router->overloadGET();
		$this->assertSame('world', $_GET['hello'] ?? null);
	}

	public function testLoadRoutes(): void
	{
		$router = Loader::loadByYaml([__DIR__ . '/routes.yml']);

		$router->debug(function ($e) {
			error_log(print_r($e->getMessage(), true));
		});

		# TEST LOADING
		$router->load();
		if($exceptions = $router->getExceptions()) {
			foreach ($exceptions as $e) {
				throw $e;
			}
		}

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

	public function testLoadFromCache(): void
	{
		$router = Loader::loadByYaml([__DIR__ . '/routes.yml']);

		$router->debug(function ($e) {
			error_log(print_r($e->getMessage(), true));
		});

		# TEST LOADING
		$router->load();
		if($exceptions = $router->getExceptions()) {
			foreach ($exceptions as $e) {
				throw $e;
			}
		}

		# Reload from export
		$cachedData = $router->export();
		$router = Loader::loadByCache($cachedData);

		$router->debug(function ($e) {
			error_log(print_r($e->getMessage(), true));
		});

		# TEST LOADING
		$router->load();
		if($exceptions = $router->getExceptions()) {
			foreach ($exceptions as $e) {
				throw $e;
			}
		}
;
		# TEST_ROOT (for validate reload from cache)
		$route = $router->route('TEST_ROOT', 'FR');
		$this->assertSame('TEST_ROOT', $route->getId());
	}
}

