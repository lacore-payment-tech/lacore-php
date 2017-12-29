# Lacore PHP Client

[![CircleCI](https://circleci.com/gh/lacore-payment-tech/lacore-php.svg?style=svg)](https://circleci.com/gh/lacore-payment-tech/lacore-php)

## Requirements

PHP 5.4 and later.

## Issues

Please use appropriately tagged github [issues](https://github.com/lacore-payment-tech/lacore-php/issues) to request features or report bugs.

## Composer

You can install the bindings via [Composer](http://getcomposer.org/). Run the following command:

```bash
composer require lacore-payment-tech/lacore-php
```

To use the bindings, use Composer's [autoload](https://getcomposer.org/doc/00-intro.md#autoloading):

```php
require_once('vendor/autoload.php');
```

## Getting Started

```php
require('/path/to/Lacore/Settings.php');
require('/path/to/Lacore/Bootstrap.php');

use \Lacore\Settings;
use \Lacore\Bootstrap;

Settings::configure([
    "root_url" => "https://lacore-sandbox.finixpayments.com",
    "username" => 'USed8KcvU1NcqCjL2gecsdE7',
    "password" => '6bb7691d-eb11-4fba-a65e-c8f352dca244 '
]);

Bootstrap::init();
```

See the [tests](https://github.com/lacore-payment-tech/lacore-php/tree/master/tests) for more details.

### Running tests

`./vendor/bin/phpunit`

See `circle.yml` for more details.
