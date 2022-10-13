Coercive Utility Router
=======================

A simple and multilingual yaml router.

Get
---
```
composer require coercive/router
```

Example YAML
------------
```yaml
INDEX:
    __: Projet\Controller::Method
    FR: /
    EN: /

HOME:
    __: Projet\Controller::Method
    FR: accueil
    EN: home

###########################

# {slug} can be all exept /
# {nb} integer (three numbers) (custom regex)
# {test} is optional (because brackets []) and like @abcdef ...

BLOG:
    __: Projet\Controller::Method
    FR: /{slug}/article-{nb:[0-9]{3}}[/{test:@[a-z]+}]
    EN: /{slug}/post-{nb:[0-9]{3}}[/{test:@[a-z]+}]
    options:
        example: 'Hello World.'

###########################

# {id} integer (two numbers) (custom regex)
# {name} can be all exept /
# {script} can be all exept /

COMPLEX:
    __: Projet\Controller::Method
    FR: chiffre-{id:[0-9]{2}}/nom-{name}/mechant-{script}
    EN: number-{id:[0-9]{2}}/name-{name}/bad-{script}

###########################

# Multi optional inner same brackets !
# {date} integer (optional)
# {slug} can be all exept / (optional too)

MULTI:
    __: Projet\Controller::Method
    FR: fr/multi-optional[/{date:\d+}-{slug}]
    EN: en/multi-optional[/{date:\d+}-{slug}]
```
The "__" (double underscore) is what the router return when the route match.
You can set what information you want, basicaly the item to load.
In this example the router return a namespace / controller / method to launch.

Load
----
```php
use Coercive\Utility\Router\Loader;

# YAML

# Load one routes file
$router = Loader::loadByYaml('/path/routes.yml')
# OR multi-files
$router = Loader::loadByYaml(['/path/first_routes.yml', '/path/second_routes.yml', ...])

# JSON

# Load one routes file
$router = Loader::loadByJson('/path/routes.json')
# OR multi-files
$router = Loader::loadByJson(['/path/first_routes.json', '/path/second_routes.json', ...])

# ARRAY

# Load routes array
$router = Loader::loadByArray([
    INDEX => [
        '__' => 'Projet\Controller::Method',
        'FR' => '/',
        'EN' => '/'
    ],
    HOME => [
            '__' => 'Projet\Controller::Method',
            'FR' => 'accueil',
            'EN' => 'home'
    ],
    [ ... ]
])

# CACHE

# You can cache the prepared routes and re-inject them after
# For example, you have cache in var $cache

if( $cache->isCacheReady() ) {
    # YES
    # Load by cache
    $data = $cache->get()
    $router = Loader::loadByCache( $data )
}
else {
    # NO
    # Classical Load
    $router = Loader::loadByYaml( '/path/routes.yml' )
}

# /!\ Important
# On a previous page you have cached the prepared routes
$cache->set( $router->getPreparedRoutesForCache() )

# The second param in loader allow you to set a basepath before your routes
# You can now use the router in local mode or ip with subdirectories before
# Example : 000.000.00.00/folder/subfolder/my-custom-route/my-param
$router = Loader::loadByArray([...], '/folder/subfolder')

# If you don't use the loader :
$parser->setBasePath('/folder/subfolder')

# OPTIONAL CONFIG PREFIX for loadByYaml or loadByJson
# (if use loadByArray : you have to set it yourself)
$router = Loader::loadByYaml([
	'/path/routes.yml',
	'SHOP_' => '/other-directory/shop/path/routes.yml',
	'BLOG_' => '/other-directory/blog/path/routes.yml'
]);
# (the first route has no prefix, the others ID : SHOP_... and BLOG_...)

```

Start the router
----------------
When config is loaded, start the router if you wan't to handle current route.
```php
<?php
# Start
$router->run();
```

Basic Functions
---------------
```php
#
# Important: don't forget to start the router before use current route...
#
    // Get ID : (example : INDEX)
    $router->current()->getId()

    // Get LANG : (example : EN)
    $router->current()->getLang()

    // Get Controller : (example : Projet\Controller::Method)
    $router->current()->getController()

    // Get ALL Options : (array)
    $router->current()->getOptions()

    // Get ONE Option : (example on BLOG route)
    $router->current()->getOption('example')

    // Force LANG : (example : EN)
    // If your process requires it, you can change the internal language
    // For example : when no route founded for current uri (404)
    $router->current()->setLang('EN')
    // or you can set a default lang in the constructor for the empty route)

    // Get HOST : (example : www.my-website.test)
    $router->getHost()

    // Force HOST : (example : www.my-new-domaine-name.test)
    // If your process requires it, you can change the host name
    // Useful for url builder
    $router->setHost('www.my-new-domaine-name.test')

    // Force SCHEME : (example : https://)
    // If your process requires it, you can change the protocol scheme
    // Useful for url builder
    $router->setProtocol('//')

    // To apply your new host on base url, use setBaseUrl with no param
    $router->setBaseUrl();

    // To overwrite current base url with custom one
    $router->setBaseUrl('https://www.my-new-domaine-name.test');

#
# Util methods :
#
    // Get Access Mode : (example : GET, POST ...)
    $router->getMethod()

    // Get HTTP Mode : (http / https)
    $router->getProtocol()

    // Get Server Script Uri
    $router->getScriptUri()

    // Get Server Script Url
    $router->getScriptUrl()

    // Detect ajax request
    $router->isAjaxRequest()
	
    // Manually set ajax request
    $router->setAjaxRequest(true|false)

    // Get what data accepted : (example : json, xml ...)
    $router->getHttpAccept()

    // Get current url
    $router->getCurrentURL() // xss filtered
    $router->getRawCurrentURL() // raw

    // Get server doc_root
    $router->getServerRootPath()
```

Switch Language
---------------
```php
#
# Switch lang for the current url
#
    # BLOG example
    # /{slug}/article-{nb:[0-9]{3}}[/{test:@[a-z]+}]

    // Get basic current url path for FR language
    $router->switchLang('FR')

        /* blog-title/article-789 */

    // Same with the full path option
    $router->switchLang('FR', true)

        /* http://www.my-web-site.example/blog-title/article-789 */


#
# OVERLOAD CURRENT URLPATH DATA
#
    // Create a new slug and add the optional test var (blog route)
    $router->overloadParam([
        'EN' => [
            'slug' => 'my-new-slug',
            'test' => '@example'
        ]
    ]);

    // You can prepare all data in one place
    $router->overloadParam([
        'EN' => [...],
        'FR' => [...],
        'RU' => [...]
    ]);

    // OR separately in multi places
    $router->overloadParam([
        'EN' => [...]
    ]);
    # ... some code
    $router->overloadParam([
        'FR' => [...]
    ]);
    # ... some code
    $router->overloadParam([
        'RU' => [...]
    ]);
    # ... some code

    // Now, when get the swithed path :
    $router->switchLang('EN')

        /* my-new-slug/article-789/@example */


#
# You can reset overload params
#
    $router->resetOverload();

    // Now, when get the swithed path, it returns the original params
    $router->switchLang('EN')

        /* blog-title/article-789 */

```

Switch
------
```php
#
# Switch params for the current url
#
    # SEARCH example
    # /search-article?q=hello&page=1

    // Rewrite only the page in french
    $router->switch('FR', [], ['page' => 2]);

        /* /recherche-un-article?q=hello&page=2 */

 	# BLOG example
    # /article-42?option=pdf&page=2

	// Rewrite id in same lang
    $router->switch('', ['id' => 55]);

        /* /article-55?option=pdf&page=2 */
		
	// Delete specific query item
    $router->switch('', [], ['option' => null]);

        /* /article-42?page=2 */

# etc...

```

Build URL
---------
```php
#
# BASIC URL
#
$router->url('HOME') // current language
$router->url('HOME', null) // current language
$router->url('HOME', 'EN') // EN language

#
# For the next examples, i use the blog route
# /{slug}/article-{nb:[0-9]{3}}[/{test:@[a-z]+}]
#

# example-fabric-url/article-100
$router->url('BLOG', 'FR', ['slug'=>'example-fabric-url', 'nb'=>100])

# Full scheme autodetect (true | 'auto') or manually set
$router->url('BLOG', 'FR', ['slug'=>'example-fabric-url', 'nb'=>100], null, true)

# http://www.my-web-site.com/example-fabric-url/article-100
$router->url('BLOG', 'FR', ['slug'=>'example-fabric-url', 'nb'=>100], null, 'http')

# https://www.my-web-site.com/example-fabric-url/article-100
$router->url('BLOG', 'FR', ['slug'=>'example-fabric-url', 'nb'=>100], null, 'https')

# ftp://www.my-web-site.com/example-fabric-url/article-100
$router->url('BLOG', 'FR', ['slug'=>'example-fabric-url', 'nb'=>100], null, 'ftp')

# https://custom-domain.com/example-fabric-url/article-100
$router->url('BLOG', 'FR', ['slug'=>'example-fabric-url', 'nb'=>100], null, 'https://custom-domain.com')

# example-fabric-url/article-100?param1=test1&param2=test2
$router->url('BLOG', 'FR', ['slug'=>'example-fabric-url', 'nb'=>100], ['param1'=>'test1', 'param2'=>'test2'])

#
# With Route object
#
echo $router->route('BLOG', 'FR')
    ->setRewriteParams([
        'slug'=>'example-fabric-url',
         'nb'=>100
     ])
     ->setQueryParams([
        'param1'=>'test1',
        'param2'=>'test2'
    ])
    ->setBaseUrl('https://custom-domain.com')
    ->setFullScheme(true)
;
```

Find
----
```php
<?php
# You may need to find a route relative to a specific url which is not the current url.
$example_referer_url = (string) filter_input(INPUT_SERVER, 'HTTP_REFERER', FILTER_SANITIZE_STRING);
$route = $router->find($example_referer_url);
echo $route->getId();
```

Filter
------
```php
<?php
# For this example, we use the BLOG entry in the previous sample yaml.
#   options:
#       example: 'Hello World.'
$f = new Filter;
$f->options('example', 'Hello World.', 'string'); # third parameter is optional

$routes = $router->filter($f);

foreach ($routes as $route) {
    echo $route->getId() . ' - ' . $route->getOption('example') . '<br>';
}
```

Load Controller
---------------
```php
#
# Class
#
$ctrl = new Ctrl;

#
# Set default controller
#
$ctrl->setDefault('Error500\\CtrlName::MethodName');

#
# Set App
# (Object optional : Example\\CtrlName->MethodName(> Param App <))
#
$ctrl->setApp(new App);

#
# Load
#
$ctrl->load('Example\\MyController::HomePage');
```
