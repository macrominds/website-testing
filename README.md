# Cross-Platform Helper for functional testing of websites.
## Start built-in php server from php unit test
macrominds/website-testing allows you to start the built-in php server directly inside unit tests or 
other php scripts. Especially useful for functional testing. Helps to reduce
external dependencies and to make your project self-contained.

### Usage example
A rough usage overview. See below for a detailed example.
```php
$this->testServer = new EmbeddedServerController(HOST,PORT,DOCROOT,'./tests/web/router.php');
$this->testServer->start();
//...
$this->testServer->stopAndWaitForConnectionLoss();
```
See this projects' phpunit.xml to learn how you can define HOST, PORT and DOCROOT for your own project.

### Installing macrominds/website-testing

#### Recommended: Composer
The recommended installation way is through [Composer](http://getcomposer.org/).

Run the Composer command to add the latest stable version of macrominds/website-testing to your development dependencies:
```
composer require --dev macrominds/website-testing
```
or add it to the appropriate section of your composer.json:
```json
    "require-dev": {
        "macrominds/website-testing": "^0.1.0",
    }
```

#### Without Composer
You may as well use macrominds/website-testing without Composer. Just fetch
src/EmbeddedServerController.php and then in your php file
```php
require_once('path-to-your/src/EmbeddedServerController.php');
```



There you go. Ready for the detailed example?


### Detailed usage example
Testing the behavior of your web application. 
This example uses [Guzzle](https://github.com/guzzle/guzzle) as the HTTP client
and phpunit. It requires the usage of Composer. 

Your `composer.json` should contain this section:
```json
    "require-dev": {
        "phpunit/phpunit": "^5.3",
        "macrominds/website-testing": "^0.1.0",
        "guzzlehttp/guzzle": "^6.2"
    }
```
Make sure to update composer:
```
composer update
```

```php
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use macrominds\website\testing\EmbeddedServerController;

class FunctionalPageTest extends \PHPUnit_Framework_TestCase{
    /**
     *
     * @var EmbeddedServerController 
     */
    private static $serverController;
    private static $client;

// setup server

    public static function setupBeforeClass()
    {
        //see phpunit.xml for configuration of PORT, DOCROOT, ROUTERSCRIPT
        self::$serverController = new EmbeddedServerController('0.0.0.0',PORT,DOCROOT,ROUTERSCRIPT);
        self::$serverController->start();
        $host = self::$serverController->getHost();
        $port = PORT;
        self::$client = new Client([
            'base_uri' => "http://$host:$port"
        ]);
    }
  
// tear down server
  
    public static function tearDownAfterClass()
    {
        if (self::$serverController !== null) {
            self::$serverController->stopAndWaitForConnectionLoss();
        }
        self::$serverController = null;
    }

// arbitrary tests

    /**
     * @test
     */
    public function shouldSendCorrectRedirectHeaders()
    {
        // we expect a temporary redirect
        $this->assertCorrectnessOfRedirectHeader(302);
        // we expect a permanent redirect
        $this->assertCorrectnessOfRedirectHeader(301);
    }
    
    private function assertCorrectnessOfRedirectHeader($code)
    {
        // our test scenario is setup to answer requests to /301.html 
        // or /302.html with the corresponding redirect.
        $response = self::$client->get("/$code.html", [
            RequestOptions::ALLOW_REDIRECTS=>false
        ]);
        $this->assertEquals($code, $response->getStatusCode());
    }
}
```

### Troubleshooting

If you've got any issues or feature-requests, just add it to the Issues section.

Running the tests from your project root in the first place could help tracking down any issues related to your system.
```
bin/phpunit -c vendor/macrominds/website-testing/phpunit.xml
```
Have fun.

---

[macrominds – Webdesign and development](http://www.macrominds.de)