# Change Log

All notable changes to this project will be documented in this file.
This project adheres to [Semantic Versioning](http://semver.org/) since version 0.0.2.

## [Unreleased]
- still need to test on OS X. Volunteers are still very welcome. Reports about success or failure on certain systems are appreciated.

## [0.1.0] – 2016-05-13
### Added
- You now have the ability to query the server controller for its host and port. E.g. `$server->getHost();`. This is important when you configured your server to be bound to `0.0.0.0`, which is suggested but not accessible on all systems. `getHost()` will then return `127.0.0.1`, which works fine on all systems.

### Changed
- Enhanced documentation (README.md)
- Your document-root and routerscript will now be validated: If they don't exist, you'll get a `RuntimeException` with a detailed errormessage.
- There are essential functions, that are needed to 
 - start the server
 - to check if the server is up and listening 
 - to kill it
  Now I added a sanity check. If your php configuration disables these functions, you'll get a `RuntimeException` with a detailed errormessage.
- Only the php **cli** variant comes with a built-in server. Now I added a sanity check for the `php_sapi_name` as well. That means, you'll get a `RuntimeException` with a detailed errormessage, when you're using the wrong php variant.

### Fixed
- Test teardown: After killing the server, we wait until the server is not reachable anymore. I recommend this for your own tests too. The old way caused tests to break on some systems.

## [0.0.2] – 2016-04-26
### Fixed
- Removed usage of `realpath(__DIR__...)` for documentRoot, because that 
  led to unintuitive or even useless docroots. 
  Providing `./tests/web` would have pointed to `vendor/macrominds/website-testing/tests/web` 
  instead of `project-root/tests/web`. This is fixed now. Provide a relative path for a document-root relative to your project-root.

## [0.0.1] – 2016-04-22 [YANKED]
Don't use. Contains an irritating bug concerning documentRoot and relative paths, that has been fixed in 0.0.2
### Added
- Basic functionality. Tested on Windows 8.1, Linux Mint 17.2 with php7.0.5 cli and CI. Mac not tested yet. (Volunteers are very welcome).
