<?php

use Spatie\Async\Output\ParallelError;
use Spatie\Async\Pool;
use Spatie\Async\Tests\MyTask;

it('can increase max content length', function () {
    $pool = Pool::create();

    $longerContentLength = 1024 * 100;

    $pool->add(new MyTask(), $longerContentLength);

    expect((string) $pool->status())->toContain('finished: 0');

    await($pool);

    expect((string) $pool->status())->toContain('finished: 1');
});

it('can decrease max content length', function () {
    $pool = Pool::create();

    $shorterContentLength = 1024;

    $pool->add(new MyTask(), $shorterContentLength);

    expect((string) $pool->status())->toContain('finished: 0');

    await($pool);

    expect((string) $pool->status())->toContain('finished: 1');
});

it('can throw error with increased max content length', function () {
    $pool = Pool::create();

    $longerContentLength = 1024 * 100;

    $errorMessage = null;

    $pool->add(childTask(function () {
        return random_bytes(1024 * 1000);
    }), $longerContentLength)
        ->catch(function (ParallelError $e) use (&$errorMessage) {
            $errorMessage = $e->getMessage();
        });

    await($pool);

    expect($errorMessage)->toMatch('/The output returned by this child process is too large/');
});

it('can throw error with decreased max content length', function () {
    $pool = Pool::create();

    $longerContentLength = 1024;

    $errorMessage = null;

    $pool->add(childTask(function () {
        return random_bytes(1024 * 100);
    }), $longerContentLength)
        ->catch(function (ParallelError $e) use (&$errorMessage) {
            $errorMessage = $e->getMessage();
        });

    await($pool);

    expect($errorMessage)->toMatch('/The output returned by this child process is too large/');
});
