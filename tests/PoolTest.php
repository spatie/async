<?php

namespace Spatie\Async\Tests;

use InvalidArgumentException;
use Spatie\Async\Pool;
use Spatie\Async\Process\SynchronousProcess;
use Symfony\Component\Stopwatch\Stopwatch;

class PoolTest extends TestCase
{
    /** @var \Symfony\Component\Stopwatch\Stopwatch */
    protected $stopwatch;

    protected function setUp(): void
    {
        parent::setUp();

        $supported = Pool::isSupported();

        if (! $supported) {
            $this->markTestSkipped('Extensions `posix` and `pcntl` not supported.');
        }

        $this->stopwatch = new Stopwatch();
    }

    /** @test */
    public function it_can_run_processes_in_parallel()
    {
        $pool = Pool::create();

        $this->stopwatch->start('test');

        foreach (range(1, 5) as $i) {
            $pool->add(function () {
                usleep(1000);
            });
        }

        $pool->wait();

        $stopwatchResult = $this->stopwatch->stop('test');

        $this->assertLessThan(400, $stopwatchResult->getDuration(), "Execution time was {$stopwatchResult->getDuration()}, expected less than 400.\n".(string) $pool->status());
    }

    /** @test */
    public function it_can_handle_success()
    {
        $pool = Pool::create();

        $counter = 0;

        foreach (range(1, 5) as $i) {
            $pool->add(function () {
                return 2;
            })->then(function (int $output) use (&$counter) {
                $counter += $output;
            });
        }

        $pool->wait();

        $this->assertEquals(10, $counter, (string) $pool->status());
    }

    /** @test */
    public function it_can_configure_another_binary()
    {
        $binary = __DIR__.'/another-php-binary';

        if (! file_exists($binary)) {
            symlink(PHP_BINARY, $binary);
        }

        $pool = Pool::create()->withBinary($binary);

        $counter = 0;

        foreach (range(1, 5) as $i) {
            $pool->add(function () {
                return 2;
            })->then(function (int $output) use (&$counter) {
                $counter += $output;
            });
        }

        $pool->wait();

        $this->assertEquals(10, $counter, (string) $pool->status());

        if (file_exists($binary)) {
            unlink($binary);
        }
    }

    /** @test */
    public function it_can_handle_timeout()
    {
        $pool = Pool::create()
            ->timeout(1);

        $counter = 0;

        foreach (range(1, 5) as $i) {
            $pool->add(function () {
                sleep(2);
            })->timeout(function () use (&$counter) {
                $counter += 1;
            });
        }

        $pool->wait();

        $this->assertEquals(5, $counter, (string) $pool->status());
    }

    /** @test */
    public function it_can_handle_millisecond_timeouts()
    {
        $pool = Pool::create()
            ->timeout(0.2);

        $counter = 0;

        foreach (range(1, 5) as $i) {
            $pool->add(function () {
                usleep(500000);
            })->timeout(function () use (&$counter) {
                $counter += 1;
            });
        }

        $pool->wait();

        $this->assertEquals(5, $counter, (string) $pool->status());
    }

    /** @test */
    public function it_can_handle_a_maximum_of_concurrent_processes()
    {
        $pool = Pool::create()
            ->concurrency(2);

        $startTime = microtime(true);

        foreach (range(1, 3) as $i) {
            $pool->add(function () {
                sleep(1);
            });
        }

        $pool->wait();

        $endTime = microtime(true);

        $executionTime = $endTime - $startTime;

        $this->assertGreaterThanOrEqual(2, $executionTime, "Execution time was {$executionTime}, expected more than 2.\n".(string) $pool->status());
        $this->assertCount(3, $pool->getFinished(), (string) $pool->status());
    }

    /** @test */
    public function it_works_with_helper_functions()
    {
        $pool = Pool::create();

        $counter = 0;

        foreach (range(1, 5) as $i) {
            $pool[] = async(function () {
                usleep(random_int(10, 1000));

                return 2;
            })->then(function (int $output) use (&$counter) {
                $counter += $output;
            });
        }

        await($pool);

        $this->assertEquals(10, $counter, (string) $pool->status());
    }

    /** @test */
    public function it_can_use_a_class_from_the_parent_process()
    {
        $pool = Pool::create();

        /** @var MyClass $result */
        $result = null;

        $pool[] = async(function () {
            $class = new MyClass();

            $class->property = true;

            return $class;
        })->then(function (MyClass $class) use (&$result) {
            $result = $class;
        });

        await($pool);

        $this->assertInstanceOf(MyClass::class, $result);
        $this->assertTrue($result->property);
    }

    /** @test */
    public function it_returns_all_the_output_as_an_array()
    {
        $pool = Pool::create();

        /** @var MyClass $result */
        $result = null;

        foreach (range(1, 5) as $i) {
            $pool[] = async(function () {
                return 2;
            });
        }

        $result = await($pool);

        $this->assertCount(5, $result);
        $this->assertEquals(10, array_sum($result));
    }

    /** @test */
    public function it_can_work_with_tasks()
    {
        $pool = Pool::create();

        $pool[] = async(new MyTask());

        $results = await($pool);

        $this->assertEquals(2, $results[0]);
    }

    /** @test */
    public function it_can_accept_tasks_with_pool_add()
    {
        $pool = Pool::create();

        $pool->add(new MyTask());

        $results = await($pool);

        $this->assertEquals(2, $results[0]);
    }

    /** @test */
    public function it_can_check_for_asynchronous_support()
    {
        $this->assertTrue(Pool::isSupported());
    }

    /** @test */
    public function it_can_run_invokable_classes()
    {
        $pool = Pool::create();

        $pool->add(new InvokableClass());

        $results = await($pool);

        $this->assertEquals(2, $results[0]);
    }

    /** @test */
    public function it_reports_error_for_non_invokable_classes()
    {
        $this->expectException(InvalidArgumentException::class);

        $pool = Pool::create();

        $pool->add(new NonInvokableClass());
    }

    public function it_can_run_synchronous_processes()
    {
        $pool = Pool::create();

        $this->stopwatch->start('test');

        foreach (range(1, 3) as $i) {
            $pool->add(new SynchronousProcess(function () {
                sleep(1);

                return 2;
            }, $i))->then(function ($output) {
                $this->assertEquals(2, $output);
            });
        }

        $pool->wait();

        $stopwatchResult = $this->stopwatch->stop('test');

        $this->assertGreaterThan(3000, $stopwatchResult->getDuration(), "Execution time was {$stopwatchResult->getDuration()}, expected less than 3000.\n".(string) $pool->status());
    }

    /** @test */
    public function it_will_automatically_schedule_synchronous_tasks_if_pcntl_not_supported()
    {
        Pool::$forceSynchronous = true;

        $pool = Pool::create();

        $pool[] = async(new MyTask())->then(function ($output) {
            $this->assertEquals(2, $output);
        });

        await($pool);

        Pool::$forceSynchronous = false;
    }

    /** @test */
    public function it_takes_an_intermediate_callback()
    {
        $pool = Pool::create();

        $pool[] = async(function () {
            return 1;
        });

        $isIntermediateCallbackCalled = false;

        $pool->wait(function (Pool $pool) use (&$isIntermediateCallbackCalled) {
            $isIntermediateCallbackCalled = true;
        });

        $this->assertTrue($isIntermediateCallbackCalled);
    }

    /** @test */
    public function it_takes_a_cancellable_intermediate_callback()
    {
        $pool = Pool::create();

        $isVisited = false;
        $pool[] = async(function () {
            sleep(2);
        })->then(function () use (&$isVisited) {
            $isVisited = true;
        });

        $pool->wait(function () {
            // Returning true should quit waiting before the task is completed
            return true;
        });

        $this->assertFalse($isVisited);
    }

    /** @test */
    public function it_can_be_stopped_early()
    {
        $concurrency = 20;
        $stoppingPoint = $concurrency / 5;

        $pool = Pool::create()->concurrency($concurrency);

        $maxProcesses = 10000;
        $completedProcessesCount = 0;

        for ($i = 0; $i < $maxProcesses; $i++) {
            $pool->add(function () use ($i) {
                return $i;
            })->then(function ($output) use ($pool, &$completedProcessesCount, $stoppingPoint) {
                $completedProcessesCount++;

                if ($output === $stoppingPoint) {
                    $pool->stop();
                }
            });
        }

        $pool->wait();

        /**
         * Because we are stopping the pool early (during the first set of processes created), we expect
         * the number of completed processes to be less than 2 times the defined concurrency.
         */
        $this->assertGreaterThanOrEqual($stoppingPoint, $completedProcessesCount);
        $this->assertLessThanOrEqual($concurrency * 2, $completedProcessesCount);
    }

    /** @test */
    public function it_writes_large_serialized_tasks_to_file()
    {
        $pool = Pool::create()->maxTaskPayload(10);

        $counter = 0;

        foreach (range(1, 5) as $i) {
            $pool->add(function () {
                return 2;
            })->then(function (int $output) use (&$counter) {
                $counter += $output;
            });
        }

        $pool->wait();

        $this->assertEquals(10, $counter, (string) $pool->status());
    }

    /** @test */
    public function it_does_memory_footprint_controllable_by_clearing_results_issue_235()
    {
        $pool = Pool::create();
        $this->assertEquals('queue: 0 - finished: 0 - failed: 0 - timeout: 0', trim($pool->status()->__toString()));
        gc_collect_cycles();
        $memUsageBefore = memory_get_usage();
        $cntTasks = 30; // sane value to test, increasing above >500 takes a substantial time to test
        foreach (range(1, $cntTasks) as $i) {
            $pool->add(function () { return 1; });
        }
        $pool->wait();
        $this->assertEquals('queue: 0 - finished: ' . $cntTasks . ' - failed: 0 - timeout: 0', trim($pool->status()->__toString()));
        gc_collect_cycles();
        $memUsageAfter1000Tasks = memory_get_usage();
        $etaTaskMemFootprint = ($memUsageAfter1000Tasks - $memUsageBefore) / $cntTasks;
        $pool->clearResults();
        $pool->clearFinished();
        $this->assertEquals('queue: 0 - finished: 0 - failed: 0 - timeout: 0', trim($pool->status()->__toString()));
        gc_collect_cycles();
        $memUsageAfter1000TasksWiped = memory_get_usage();
        $etaTaskMemFootprintWiped = ($memUsageAfter1000TasksWiped - $memUsageBefore) / $cntTasks;
        // dd($etaTaskMemFootprint . ' bytes --> ' . $etaTaskMemFootprintWiped . ' bytes per task');

        /**
         * tested with   1 tasks: "4928 bytes --> 824 bytes per task"
         * tested with  10 tasks: "3769.6 bytes --> 114.4 bytes per task"
         * tested with 100 tasks: "3678.08 bytes --> 17.84 bytes per task"
         * tested with 300 tasks: "3939.4133333333 bytes --> 277.94666666667 bytes per task"
         * tested with 600 tasks: "3970.9866666667 bytes --> 316.46666666667 bytes per task"
         * tested with 600 tasks: "3970.9866666667 bytes --> 316.46666666667 bytes per task"
         * tested with 10000 tasks: "3995.3432 bytes --> 351.176 bytes per task"
         * the memory footprint of the results of one task is ~3-4KB
         * eg: when one task is run every second, this yields a memory requirement of 3KB * 3600 * 24 = 260MB per day runtime
         * When the result is wiped instead the memory footprint is (at least) an order of magnitude less, maybe even less / no mem leak at all.
         */
        $this->assertTrue($etaTaskMemFootprint > 3000 && $etaTaskMemFootprint < 5000,
            'memory footprint without wipe not as axpected, etaTaskMemFootprint: ' . $etaTaskMemFootprint);
        $this->assertTrue($etaTaskMemFootprintWiped > 0 && $etaTaskMemFootprintWiped < 500,
            'memory footprint with wipe not as axpected, etaTaskMemFootprintWiped: ' . $etaTaskMemFootprintWiped);
    }
}
