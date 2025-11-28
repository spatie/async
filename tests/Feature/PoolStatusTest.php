<?php

use Spatie\Async\Pool;
use Spatie\Async\Tests\MyTask;

it('can show a textual status', function () {
    $pool = Pool::create();

    $pool->add(new MyTask());

    expect((string) $pool->status())->toContain('finished: 0');

    await($pool);

    expect((string) $pool->status())->toContain('finished: 1');
});

it('can show a textual failed status', function () {
    $pool = Pool::create();

    foreach (range(1, 5) as $i) {
        $pool->add(childTask(function () {
            throw new Exception('Test');
        }))->catch(function () {
            // Do nothing
        });
    }

    $pool->wait();

    expect((string) $pool->status())->toContain('finished: 0');
    expect((string) $pool->status())->toContain('failed: 5');
    expect((string) $pool->status())->toContain('failed with Exception: Test');
});

it('can show timeout status', function () {
    $pool = Pool::create()->timeout(0);

    foreach (range(1, 5) as $i) {
        $pool->add(childTask(function () {
            sleep(1000);
        }));
    }

    $pool->wait();

    expect((string) $pool->status())->toContain('timeout: 5');
});
