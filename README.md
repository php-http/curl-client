# Curl client for PHP HTTP

[![Latest Version](https://img.shields.io/github/release/php-http/curl-client.svg?style=flat-square)](https://github.com/php-http/curl-client/releases)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![Build Status](https://img.shields.io/travis/php-http/curl-client.svg?style=flat-square)](https://travis-ci.org/php-http/curl-client)
[![Code Coverage](https://img.shields.io/scrutinizer/coverage/g/php-http/curl-client.svg?style=flat-square)](https://scrutinizer-ci.com/g/php-http/curl-client)
[![Quality Score](https://img.shields.io/scrutinizer/g/php-http/curl-client.svg?style=flat-square)](https://scrutinizer-ci.com/g/php-http/curl-client)
[![Total Downloads](https://img.shields.io/packagist/dt/php-http/curl-client.svg?style=flat-square)](https://packagist.org/packages/php-http/curl-client)

The cURL client use the cURL PHP extension which must be activated in your `php.ini`.


## Install

Via Composer

``` bash
$ composer require php-http/curl-client
```

## Usage

### Using [php-http/discovery](https://packagist.org/packages/php-http/discovery):

```php
use Http\Client\Curl\Client;
use Http\Discovery\MessageFactoryDiscovery;
use Http\Discovery\StreamFactoryDiscovery;

$messageFactory = MessageFactoryDiscovery::find();
$streamFactory = StreamFactoryDiscovery::find();
$client = new Client($messageFactory, $streamFactory);

$request = $messageFactory->createRequest('GET', 'http://example.com/');
$response = $client->sendRequest($request);
```

### Using [mekras/httplug-diactoros-bridge](https://packagist.org/packages/mekras/httplug-diactoros-bridge):

```php
use Http\Client\Curl\Client;
use Mekras\HttplugDiactorosBridge\DiactorosMessageFactory;
use Mekras\HttplugDiactorosBridge\DiactorosStreamFactory;

$messageFactory = new DiactorosMessageFactory();
$client = new Client($messageFactory, new DiactorosStreamFactory());

$request = $messageFactory->createRequest('GET', 'http://example.com/');
$response = $client->sendRequest($request);
```

### Configuring client

You can use [cURL options](http://php.net/curl_setopt) to configure Client:

```php
use Http\Client\Curl\Client;
use Http\Discovery\MessageFactoryDiscovery;
use Http\Discovery\StreamFactoryDiscovery;

$options = [
    CURLOPT_CONNECTTIMEOUT => 10, // The number of seconds to wait while trying to connect. 
    CURLOPT_SSL_VERIFYPEER => false // Stop cURL from verifying the peer's certificate
];
$client = new Client(MessageFactoryDiscovery::find(), StreamFactoryDiscovery::find(), $options);
```

These options can not ne used:

* CURLOPT_CUSTOMREQUEST
* CURLOPT_FOLLOWLOCATION
* CURLOPT_HEADER
* CURLOPT_HTTP_VERSION
* CURLOPT_HTTPHEADER
* CURLOPT_NOBODY
* CURLOPT_POSTFIELDS
* CURLOPT_RETURNTRANSFER
* CURLOPT_URL

These options can be overwritten by Client:

* CURLOPT_USERPWD

## Documentation

Please see the [official documentation](http://php-http.readthedocs.org/en/latest/).

## Testing

``` bash
$ composer test
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CONDUCT](CONDUCT.md) for details.


## Security

If you discover any security related issues, please contact us at
[security@php-http.org](mailto:security@php-http.org).


## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
