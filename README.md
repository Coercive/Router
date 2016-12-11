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
```
The "__" (double underscore) is what the router return when the route match.
You can set what information you want, basicaly the item to load.
In this example the router return a namespace / controller / method to launch.

Load
----
```php
use Coercive\Utility\Router\Router;

# Load one routes file
$oRouteur = new Router('/path/routes.yml');

# OR multi-files
$oRouteur = new Router(['/path/first_routes.yml', '/path/second_routes.yml', ...]);
```

Basic Functions
---------------
```php
#
# Important
#
    // Get ID : (example : INDEX)
    $oRouteur->getId() 
	
    // Get LANG : (example : EN)
    $oRouteur->getLang()
	
    // Get Controller : (example : Projet\Controller::Method)
    $oRouteur->getController()

#
# Util methods :
#
    // Get Access Mode : (example : GET, POST ...)
    $oRouteur->getAccessMode()

    // Get HTTP Mode : (http / https)
    $oRouteur->getHttpMode()

    // Get the current matched path : (example : /{slug}/article-{nb:[0-9]{3}}[/{test:@[a-z]+}])
    $oRouteur->getNoRewritedMatchedPath()

    // Detect ajax request
    $oRouteur->isAjaxRequest()
	
    // Manually set ajax request
    $oRouteur->setAjaxRequest(true|false)

    // Get what data accepted : (example : json, xml ...)
    $oRouteur->getHttpAccept()

    // Detect offical bot (basic list : bot|google|googlebot|spider|yahoo)
    $oRouteur->isOfficialBot()

    // Get current url
    $oRouteur->getCurrentURL()

    // Get server doc_root
    $oRouteur->getServerRootPath()

    // Get the translated route params (array)
    $oRouteur->getTranslateRouteParams()
```
Some new functions will be added soon, like a better custom bot detection etc...

Switch Language
---------------
```php
#
# Switch lang for the current url
#
    # BLOG example
    # /{slug}/article-{nb:[0-9]{3}}[/{test:@[a-z]+}]

    // Get basic current url path for FR language
    $oRouteur->switchLang('FR')

        /* blog-title/article-789 */

    // Same with the full path option
    $oRouteur->switchLang('FR', true)

        /* http://www.my-web-site.example/blog-title/article-789 */


#
# OVERLOAD CURRENT URLPATH DATA
#
    // Create a new slug and add the optional test var (blog route)
    $oRouteur->overloadParam([
        'EN' => [
            'slug' => 'my-new-slug',
            'test' => '@example'
        ]
    ]);

    // You can prepare all data in one place
    $oRouteur->overloadParam([
        'EN' => [...],
        'FR' => [...],
        'RU' => [...]
    ]);

    // OR separately in multi places
    $oRouteur->overloadParam([
        'EN' => [...]
    ]);
    # ... some code
    $oRouteur->overloadParam([
        'FR' => [...]
    ]);
    # ... some code
    $oRouteur->overloadParam([
        'RU' => [...]
    ]);
    # ... some code

    // Now, when get the swithed path :
    $oRouteur->switchLang('EN')

        /* my-new-slug/article-789/@example */


#
# You can reset overload params
#
    $oRouteur->resetOverload();

    // Now, when get the swithed path, it returns the original params
    $oRouteur->switchLang('EN')

        /* blog-title/article-789 */

```

Build URL
---------
```php
#
# BASIC ROUTE
#
$oRouteur->url('HOME') // current language
$oRouteur->url('HOME', null) // current language
$oRouteur->url('HOME', 'EN') // EN language

#
# For the next examples, i use the blog route
# /{slug}/article-{nb:[0-9]{3}}[/{test:@[a-z]+}]
#

# example-fabric-url/article-100
$oRouteur->url('BLOG', 'FR', ['slug'=>'example-fabric-url', 'nb'=>100])

# http://www.my-web-site.com/example-fabric-url/article-100
$oRouteur->url('BLOG', 'FR', ['slug'=>'example-fabric-url', 'nb'=>100], null, 'http')

# https://www.my-web-site.com/example-fabric-url/article-100
$oRouteur->url('BLOG', 'FR', ['slug'=>'example-fabric-url', 'nb'=>100], null, 'https')

# example-fabric-url/article-100?param1=test1&param2=test2
$oRouteur->url('BLOG', 'FR', ['slug'=>'example-fabric-url', 'nb'=>100], ['param1'=>'test1', 'param2'=>'test2'])
```
