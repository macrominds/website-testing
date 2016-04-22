# Cross-Platform Helper for functional testing of websites.
 
## EmbeddedServerController
Enables you to start the built-in php webserver from a 
php script. Especially useful for functional testing.

However: This will only work from php-cli, such as php 5.6 cli or 
php 7.0 cli. **So make sure, you're running the cli version.**
If you're not using the cli version, php will behave just as if
you had called it without -S. There will be no error-message from php.

### Usage
```php
$this->testServer = new EmbeddedServerController(HOST,PORT,DOCROOT);
$this->testServer->start();
//...
$this->testServer->stop();
```
See this projects' phpunit.xml to learn how you can define HOST, PORT, DOCROOT
for your own project.

---

[macrominds – Webdesign and development](http://www.macrominds.de)