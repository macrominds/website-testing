# Change Log

All notable changes to this project will be documented in this file.
## [0.0.2] – 2016-04-26
- Removed usage of `realpath(__DIR__...)` for documentRoot, because that 
  led to unintuitive or even useless docroots. 
  Providing `./tests/web` would have pointed to `vendor/macrominds/website-testing/tests/web` 
  instead of `project-root/tests/web`. This is fixed now. Provide a relative path for a document-root relative to your project-root.

## [0.0.1] - 2016-04-22
### Added
- Basic functionality. Tested on Windows 8.1, Linux Mint 17.2 with php7.0.5 cli and CI. Mac not tested yet. (Volunteers are very welcome).
