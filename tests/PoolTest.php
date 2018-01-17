<?php

namespace Spatie\Async;

use Spatie\Async\Tests\MyTask;
use PHPUnit\Framework\TestCase;
use Spatie\Async\Tests\MyClass;
use Symfony\Component\Stopwatch\Stopwatch;
use Spatie\Async\Process\SynchronousProcess;

class PoolTest extends TestCase
{
    /** @var \Symfony\Component\Stopwatch\Stopwatch */
    protected $stopwatch;

    protected function setUp()
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

        $this->assertLessThan(200, $stopwatchResult->getDuration(), "Execution time was {$stopwatchResult->getDuration()}, expected less than 0.2.\n".(string) $pool->status());
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
            $this->assertEquals(0, $output);
        });

        await($pool);
    }
}
