# Asynchronous and parallel PHP with PCNTL

[![Latest Version on Packagist](https://img.shields.io/packagist/v/spatie/async.svg?style=flat-square)](https://packagist.org/packages/spatie/async)
[![Build Status](https://img.shields.io/travis/spatie/async/master.svg?style=flat-square)](https://travis-ci.org/spatie/async)
[![Quality Score](https://img.shields.io/scrutinizer/g/spatie/async.svg?style=flat-square)](https://scrutinizer-ci.com/g/spatie/async)
[![StyleCI](https://styleci.io/repos/114228700/shield?branch=master)](https://styleci.io/repos/114228700)
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
    ->maximumExecutionTime(200); // The maximum amount of time a process may take to finish in seconds.
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

## Behind the curtains

When using this package, you're probably wondering what's happening underneath the surface.

PHP has an extension called [PCNTL](http://php.net/manual/en/book.pcntl.php) which can spawn forks of its current process. 
PCNTL directly uses your system's `fork` call to create a copy of the process, as a child process.

By creating child processes on the fly, we're able to execute PHP scripts in parallel.
This parallelism can improve performance significantly when dealing with multiple synchronous tasks, 
which don't really need to wait for each other.
By giving these tasks a separate process to run on, the underlying operating system can take care of running them in parallel.

There's a caveat when dynamically spawning processes: you need to make sure that there won't be too many processes at once,
or the application might crash.
The `Pool` class provided by this package takes care of handling as many processes as you want 
by scheduling and running them when it's possible.

That's the part that `async()` or `$pool->add()` do. Now let's look at what `await()` or `$pool->wait()` does.

When multiple processes are spawned, each can have a separate time to completion. 
One process might eg. have to wait for a HTTP call, while the other has to process large amounts of data.
Sometimes you also have points in your code which have to wait until the result of a process is returned.

This is why we have to wait at a certain point in time: for all processes on a pool to finish, 
so we can be sure it's safe to continue without accidentally killing the child processes which aren't done yet.

"Waiting" for all processes is done in a `while` loop, which will check the status of every process once in a while.
When a process is finished, its success event is triggered, which you can hook into with the `->then()` function.
Likewise, when a process fails or times out, the loop will update that process' status and move on. 

When all processes are finished, the while loop will see that there's nothing more to wait for, and stop.
This is the moment your parent process can continue to execute.

Because we're working with separate processes, we need a way of communication between the parent and child processes.
You might for example want to use the result generated by your child processes, in the parent process.

Our package uses UNIX sockets for this communication. 
Once a process is executed, we'll serialize its output and send it via a socket to the parent process, 
who can handle it further in the while loop we spoke about earlier.

When a process throws an exception or fails, we can also catch that output and send it via the socket to the parent.
That's how you can also listen for unhandled exceptions thrown in a child process, and handle them yourself.  

## Testing

``` bash
composer test
```

## Changelog

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
