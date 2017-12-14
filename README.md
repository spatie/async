# Asynchronous and parallel PHP with PCNTL

[![Latest Version on Packagist](https://img.shields.io/packagist/v/spatie/async.svg?style=flat-square)](https://packagist.org/packages/spatie/async)
[![Build Status](https://img.shields.io/travis/spatie/async/master.svg?style=flat-square)](https://travis-ci.org/spatie/async)
[![Quality Score](https://img.shields.io/scrutinizer/g/spatie/async.svg?style=flat-square)](https://scrutinizer-ci.com/g/spatie/async)
[![Total Downloads](https://img.shields.io/packagist/dt/spatie/async.svg?style=flat-square)](https://packagist.org/packages/spatie/async)

This library provides a small and easy wrapper around PHP's PCNTL extension. 
It allows for running difference processes in parallel, with an easy-to-use API.

## Installation

You can install the package via composer:

```bash
composer require spatie/async
```

## Usage

```php
use Spatie\Async\Pool;

$pool = Pool::create();

foreach ($things as $thing) {
    $pool->add(function () use ($thing) {
        // Do a thing
    })->then(function ($output) {
        // Handle success
    })->catch(function (Exception $e) {
        // Handle exception
    });
}

$pool->wait();
```

### Pool configuration

You're free to create as many pools as you want, each pool has its own queue of processes it will handle.

A pool is configurable by the developer:

```php
use Spatie\Async\Pool;

$pool = Pool::create()
    ->concurrency(20) // The maximum amount of processes which can run simultaneously.
    ->maximumExecutionTime(200) // The maximum amount of time a process may take to finish in seconds.
;
```

### Processes

You can just add closures to the pool, but in some cases you want a class to represent a process.

```php
use Spatie\Async\Process;

class MyProcess extends Process
{
    public function __construct()
    {
        // You can add your own dependencies.
    }

    public function execute() 
    {
        // You can do whatever you like in here.
    }
}
```

### Event listeners

When adding a process or a callable to a pool, you'll get an instance of `Process` returned.
You can add the following event hooks on a process.

```php
$pool
    ->add(function () {
        // ...
    })
    ->then(function ($output) {
        // On success, `$output` is returned by the process or callable you passed to the queue.
    })
    ->error(function ($e) {
        // When an exception is thrown from within a process, it's caught and passed here.
    })
    ->timeout(function () {
        // A process took too long to finish.
    })
;
```

### Functional API

Instead of using methods on the `$pool` object, you may also use the `async` and `await` helper functions.

```php
use Spatie\Async\Process;

$pool = Pool::create();

for ($i = 0; $i < 5; $i++) {
    $pool[] = async(function () {
        usleep(random_int(10, 1000));

        return 2;
    })->then(function (int $output) {
        $this->counter += $output;
    });
}

await($pool);
```

### Testing

``` bash
composer test
```

### Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

### Security

If you discover any security related issues, please email freek@spatie.be instead of using the issue tracker.

## Postcardware

You're free to use this package, but if it makes it to your production environment we highly appreciate you sending us a postcard from your hometown, mentioning which of our package(s) you are using.

Our address is: Spatie, Samberstraat 69D, 2060 Antwerp, Belgium.

We publish all received postcards [on our company website](https://spatie.be/en/opensource/postcards).

## Credits

- [Brent Roose](https://github.com/brendt)
- [All Contributors](../../contributors)

## Support us

Spatie is a webdesign agency based in Antwerp, Belgium. You'll find an overview of all our open source projects [on our website](https://spatie.be/opensource).

Does your business depend on our contributions? Reach out and support us on [Patreon](https://www.patreon.com/spatie). 
All pledges will be dedicated to allocating workforce on maintenance and new awesome stuff.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
