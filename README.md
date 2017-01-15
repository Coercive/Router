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
$oRouter = Loader::loadByYaml('/path/routes.yml')
# OR multi-files
$oRouter = Loader::loadByYaml(['/path/first_routes.yml', '/path/second_routes.yml', ...])

# JSON

# Load one routes file
$oRouter = Loader::loadByJson('/path/routes.json')
# OR multi-files
$oRouter = Loader::loadByJson(['/path/first_routes.json', '/path/second_routes.json', ...])

# ARRAY

# Load routes array
$oRouter = Loader::loadByArray([
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
# For example, you have cache in var $oCache

if( $oCache->isCacheReady() ) {
    # YES
    # Load by cache
    $aArray = $oCache->get()
    $oRouter = Loader::loadByCache( $aArray )
}
else {
    # NO
    # Classical Load
    $oRouter = Loader::loadByYaml( '/path/routes.yml' )
}

# /!\ Important
# On a previous page you have cached the prepared routes
$oCache->set( $oRouter->getPreparedRoutesForCache() )

```

Basic Functions
---------------
```php
#
# Important
#
    // Get ID : (example : INDEX)
    $oRouter->getId()
    
    // Get HOST : (example : www.my-website.test)
    $oRouter->getHost()

    // Force HOST : (example : www.my-new-domaine-name.test)
    // If your process requires it, you can change the host name
    // Useful for url builder
    $oRouter->forceHost('www.my-new-domaine-name.test')

    // Get LANG : (example : EN)
    $oRouter->getLang()

    // Force LANG : (example : EN)
    // If your process requires it, you can change the internal language
    $oRouter->forceLang('EN')

    // Get Controller : (example : Projet\Controller::Method)
    $oRouter->getController()

#
# Util methods :
#
    // Get Access Mode : (example : GET, POST ...)
    $oRouter->getAccessMode()

    // Get HTTP Mode : (http / https)
    $oRouter->getHttpMode()

    // Get the current matched path : (example : /{slug}/article-{nb:[0-9]{3}}[/{test:@[a-z]+}])
    $oRouter->getNoRewritedMatchedPath()

    // Detect ajax request
    $oRouter->isAjaxRequest()
	
    // Manually set ajax request
    $oRouter->setAjaxRequest(true|false)

    // Get what data accepted : (example : json, xml ...)
    $oRouter->getHttpAccept()

    // Get current url
    $oRouter->getCurrentURL()

    // Get server doc_root
    $oRouter->getServerRootPath()

    // Get the translated route params (array)
    $oRouter->getTranslateRouteParams()
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
    $oRouter->switchLang('FR')

        /* blog-title/article-789 */

    // Same with the full path option
    $oRouter->switchLang('FR', true)

        /* http://www.my-web-site.example/blog-title/article-789 */


#
# OVERLOAD CURRENT URLPATH DATA
#
    // Create a new slug and add the optional test var (blog route)
    $oRouter->overloadParam([
        'EN' => [
            'slug' => 'my-new-slug',
            'test' => '@example'
        ]
    ]);

    // You can prepare all data in one place
    $oRouter->overloadParam([
        'EN' => [...],
        'FR' => [...],
        'RU' => [...]
    ]);

    // OR separately in multi places
    $oRouter->overloadParam([
        'EN' => [...]
    ]);
    # ... some code
    $oRouter->overloadParam([
        'FR' => [...]
    ]);
    # ... some code
    $oRouter->overloadParam([
        'RU' => [...]
    ]);
    # ... some code

    // Now, when get the swithed path :
    $oRouter->switchLang('EN')

        /* my-new-slug/article-789/@example */


#
# You can reset overload params
#
    $oRouter->resetOverload();

    // Now, when get the swithed path, it returns the original params
    $oRouter->switchLang('EN')

        /* blog-title/article-789 */

```

Build URL
---------
```php
#
# BASIC ROUTE
#
$oRouter->url('HOME') // current language
$oRouter->url('HOME', null) // current language
$oRouter->url('HOME', 'EN') // EN language

#
# For the next examples, i use the blog route
# /{slug}/article-{nb:[0-9]{3}}[/{test:@[a-z]+}]
#

# example-fabric-url/article-100
$oRouter->url('BLOG', 'FR', ['slug'=>'example-fabric-url', 'nb'=>100])

# Full scheme autodetect (true) or manually set
$oRouter->url('BLOG', 'FR', ['slug'=>'example-fabric-url', 'nb'=>100], null, true)

# http://www.my-web-site.com/example-fabric-url/article-100
$oRouter->url('BLOG', 'FR', ['slug'=>'example-fabric-url', 'nb'=>100], null, 'http')

# https://www.my-web-site.com/example-fabric-url/article-100
$oRouter->url('BLOG', 'FR', ['slug'=>'example-fabric-url', 'nb'=>100], null, 'https')

# example-fabric-url/article-100?param1=test1&param2=test2
$oRouter->url('BLOG', 'FR', ['slug'=>'example-fabric-url', 'nb'=>100], ['param1'=>'test1', 'param2'=>'test2'])
```
