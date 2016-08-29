# Change Log

## 1.5.1 - 2016-08-29

### Fixed

- #26: Combining CurlClient with StopwatchPlugin causes Promise onRejected handler to never be
  invoked.


## 1.5 - 2016-08-03

### Changed

- Request body can be send with any method except GET, HEAD and TRACE.
- #25: Make discovery a hard dependency. 


## 1.4.2 - 2016-06-14

### Added

- #23: "php-http/async-client-implementation" added to "provide" section.


## 1.4.1 - 2016-05-30

### Fixed

- #22: Cannot create the client using `HttpClientDiscovery`.


## 1.4 - 2016-03-30

### Changed

- #20: Minimize memory usage when reading large response body.


## 1.3 - 2016-03-14

### Fixed

- #18: Invalid "Expect" header.

### Removed

- #13: Remove HeaderParser. 


## 1.2 - 2016-03-09

### Added

- #16: Make sure discovery can find the curl client

### Fixed

- #15: "Out of memory" sending large files.


## 1.1.0 - 2016-01-29

### Changed

- Switch to php-http/message 1.0.


## 1.0.0 - 2016-01-28

First stable release.


## 0.7.0 - 2016-01-26

### Changed

- Migrate from `php-http/discovery` and `php-http/utils` to `php-http/message`.

## 0.6.0 - 2016-01-12

### Changed

- Root namespace changed from `Http\Curl` to `Http\Client\Curl`.
- Main client class name renamed from `CurlHttpClient` to `Client`. 
- Minimum required [php-http/discovery](https://packagist.org/packages/php-http/discovery)
  version changed to 0.5.


## 0.5.0 - 2015-12-18

### Changed

- Compatibility with php-http/httplug 1.0 beta
- Switch to php-http/discovery 0.4


## 0.4.0 - 2015-12-16

### Changed

- Switch to php-http/message-factory 1.0


## 0.3.1 - 2015-12-14

### Changed

- Requirements fixed.


## 0.3.0 - 2015-11-24

### Changed

- Use cURL constants as options keys.


## 0.2.0 - 2015-11-17

### Added

- HttpAsyncClient support.


## 0.1.0 - 2015-11-11

### Added

- Initial release
