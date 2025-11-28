<?php

use Spatie\Async\Pool;
use Spatie\Async\Tests\InvokableClass;
use Spatie\Async\Tests\MyClass;
use Spatie\Async\Tests\MyTask;
use Spatie\Async\Tests\NonInvokableClass;
use Symfony\Component\Stopwatch\Stopwatch;

it('can run processes in parallel', function () {
    $pool = Pool::create();

    $stopwatch = new Stopwatch();
    $stopwatch->start('test');

    foreach (range(1, 5) as $i) {
        $pool->add(childTask(function () {
            usleep(1000);
        }));
    }

    $pool->wait();

    $stopwatchResult = $stopwatch->stop('test');

    expect($stopwatchResult->getDuration())->toBeLessThan(400, "Execution time was {$stopwatchResult->getDuration()}, expected less than 400.\n".(string) $pool->status());
});

it('can handle success', function () {
    $pool = Pool::create();

    $counter = 0;

    foreach (range(1, 5) as $i) {
        $pool->add(childTask(function () {
            return 2;
        }))->then(function (int $output) use (&$counter) {
            $counter += $output;
        });
    }

    $pool->wait();

    expect($counter)->toBe(10, (string) $pool->status());
});

it('can configure another binary', function () {
    $binary = __DIR__.'/../another-php-binary';

    if (! file_exists($binary)) {
        symlink(PHP_BINARY, $binary);
    }

    $pool = Pool::create()->withBinary($binary);

    $counter = 0;

    foreach (range(1, 5) as $i) {
        $pool->add(childTask(function () {
            return 2;
        }))->then(function (int $output) use (&$counter) {
            $counter += $output;
        });
    }

    $pool->wait();

    expect($counter)->toBe(10, (string) $pool->status());

    if (file_exists($binary)) {
        unlink($binary);
    }
});

it('can handle timeout', function () {
    $pool = Pool::create()
        ->timeout(1);

    $counter = 0;

    foreach (range(1, 5) as $i) {
        $pool->add(childTask(function () {
            sleep(2);
        }))->timeout(function () use (&$counter) {
            $counter += 1;
        });
    }

    $pool->wait();

    expect($counter)->toBe(5, (string) $pool->status());
});

it('can handle millisecond timeouts', function () {
    $pool = Pool::create()
        ->timeout(0.2);

    $counter = 0;

    foreach (range(1, 5) as $i) {
        $pool->add(childTask(function () {
            usleep(500000);
        }))->timeout(function () use (&$counter) {
            $counter += 1;
        });
    }

    $pool->wait();

    expect($counter)->toBe(5, (string) $pool->status());
});

it('can handle a maximum of concurrent processes', function () {
    $pool = Pool::create()
        ->concurrency(2);

    $startTime = microtime(true);

    foreach (range(1, 3) as $i) {
        $pool->add(childTask(function () {
            sleep(1);
        }));
    }

    $pool->wait();

    $endTime = microtime(true);

    $executionTime = $endTime - $startTime;

    expect($executionTime)->toBeGreaterThanOrEqual(2, "Execution time was {$executionTime}, expected more than 2.\n".(string) $pool->status());
    expect($pool->getFinished())->toHaveCount(3, (string) $pool->status());
});

it('works with helper functions', function () {
    $pool = Pool::create();

    $counter = 0;

    foreach (range(1, 5) as $i) {
        $pool[] = async(childTask(function () {
            usleep(random_int(10, 1000));

            return 2;
        }))->then(function (int $output) use (&$counter) {
            $counter += $output;
        });
    }

    await($pool);

    expect($counter)->toBe(10, (string) $pool->status());
});

it('can use a class from the parent process', function () {
    $pool = Pool::create();

    /** @var MyClass $result */
    $result = null;

    $pool[] = async(childTask(function () {
        $class = new MyClass();

        $class->property = true;

        return $class;
    }))->then(function (MyClass $class) use (&$result) {
        $result = $class;
    });

    await($pool);

    expect($result)->toBeInstanceOf(MyClass::class);
    expect($result->property)->toBeTrue();
});

it('returns all the output as an array', function () {
    $pool = Pool::create();

    foreach (range(1, 5) as $i) {
        $pool[] = async(childTask(function () {
            return 2;
        }));
    }

    $result = await($pool);

    expect($result)->toHaveCount(5);
    expect(array_sum($result))->toBe(10);
});

it('can work with tasks', function () {
    $pool = Pool::create();

    $pool[] = async(new MyTask());

    $results = await($pool);

    expect($results[0])->toBe(2);
});

it('can accept tasks with pool add', function () {
    $pool = Pool::create();

    $pool->add(new MyTask());

    $results = await($pool);

    expect($results[0])->toBe(2);
});

it('can check for asynchronous support', function () {
    expect(Pool::isSupported())->toBeTrue();
});

it('can run invokable classes', function () {
    $pool = Pool::create();

    $pool->add(new InvokableClass());

    $results = await($pool);

    expect($results[0])->toBe(2);
});

it('reports error for non invokable classes', function () {
    $pool = Pool::create();

    $pool->add(new NonInvokableClass());
})->throws(InvalidArgumentException::class);

it('will automatically schedule synchronous tasks if pcntl not supported', function () {
    Pool::$forceSynchronous = true;

    $pool = Pool::create();

    $output = null;

    $pool[] = async(new MyTask())->then(function ($result) use (&$output) {
        $output = $result;
    });

    await($pool);

    expect($output)->toBe(2);

    Pool::$forceSynchronous = false;
});

it('takes an intermediate callback', function () {
    $pool = Pool::create();

    $pool[] = async(childTask(function () {
        return 1;
    }));

    $isIntermediateCallbackCalled = false;

    $pool->wait(function (Pool $pool) use (&$isIntermediateCallbackCalled) {
        $isIntermediateCallbackCalled = true;
    });

    expect($isIntermediateCallbackCalled)->toBeTrue();
});

it('takes a cancellable intermediate callback', function () {
    $pool = Pool::create();

    $isVisited = false;
    $pool[] = async(childTask(function () {
        sleep(2);
    }))->then(function () use (&$isVisited) {
        $isVisited = true;
    });

    $pool->wait(function () {
        // Returning true should quit waiting before the task is completed
        return true;
    });

    expect($isVisited)->toBeFalse();
});

it('can be stopped early', function () {
    $concurrency = 20;
    $stoppingPoint = $concurrency / 5;

    $pool = Pool::create()->concurrency($concurrency);

    $maxProcesses = 10000;
    $completedProcessesCount = 0;

    for ($i = 0; $i < $maxProcesses; $i++) {
        $index = $i;
        $pool->add(childTask(function () use ($index) {
            return $index;
        }))->then(function ($output) use ($pool, &$completedProcessesCount, $stoppingPoint) {
            $completedProcessesCount++;

            if ($output === $stoppingPoint) {
                $pool->stop();
            }
        });
    }

    $pool->wait();

    expect($completedProcessesCount)->toBeGreaterThanOrEqual($stoppingPoint);
    expect($completedProcessesCount)->toBeLessThanOrEqual($concurrency * 2);
});

it('writes large serialized tasks to file', function () {
    $pool = Pool::create()->maxTaskPayload(10);

    $counter = 0;

    foreach (range(1, 5) as $i) {
        $pool->add(childTask(function () {
            return 2;
        }))->then(function (int $output) use (&$counter) {
            $counter += $output;
        });
    }

    $pool->wait();

    expect($counter)->toBe(10, (string) $pool->status());
});

it('does memory footprint controllable by clearing results', function () {
    $pool = Pool::create();
    expect(trim($pool->status()->__toString()))->toBe('queue: 0 - finished: 0 - failed: 0 - timeout: 0');

    gc_collect_cycles();
    $memUsageBefore = memory_get_usage();
    $cntTasks = 30;

    foreach (range(1, $cntTasks) as $i) {
        $pool->add(childTask(function () { return 1; }));
    }

    $pool->wait();
    expect(trim($pool->status()->__toString()))->toBe('queue: 0 - finished: ' . $cntTasks . ' - failed: 0 - timeout: 0');

    gc_collect_cycles();
    $memUsageAfter1000Tasks = memory_get_usage();
    $etaTaskMemFootprint = ($memUsageAfter1000Tasks - $memUsageBefore) / $cntTasks;

    $pool->clearResults();
    $pool->clearFinished();
    expect(trim($pool->status()->__toString()))->toBe('queue: 0 - finished: 0 - failed: 0 - timeout: 0');

    gc_collect_cycles();
    $memUsageAfter1000TasksWiped = memory_get_usage();
    $etaTaskMemFootprintWiped = ($memUsageAfter1000TasksWiped - $memUsageBefore) / $cntTasks;

    expect($etaTaskMemFootprint)->toBeGreaterThan(3000);
    expect($etaTaskMemFootprint)->toBeLessThan(5000);
    expect($etaTaskMemFootprintWiped)->toBeGreaterThan(0);
    expect($etaTaskMemFootprintWiped)->toBeLessThan(500);
});
