<?php

namespace Spatie\Async;

use Exception;
use PHPUnit\Framework\TestCase;

class PoolTest extends TestCase
{
    protected $counter = 0;

    protected function setUp()
    {
        parent::setUp();

        $this->counter = 0;
    }

    /** @test */
    public function it_can_run_processes_in_parallel()
    {
        $pool = Pool::create();

        $startTime = microtime(true);

        for ($i = 0; $i < 5; $i++) {
            $pool->add(function () {
                usleep(1000);
            });
        }

        $pool->wait();

        $endTime = microtime(true);

        $executionTime = $endTime - $startTime;

        $this->assertTrue($executionTime >= 0.1);
        $this->assertTrue($executionTime < 0.2);
    }

    /** @test */
    public function it_can_handle_success()
    {
        $pool = Pool::create();

        for ($i = 0; $i < 5; $i++) {
            $pool->add(function () {
                return 2;
            })->then(function (int $output) {
                $this->counter += $output;
            });
        }

        $pool->wait();

        $this->assertEquals(10, $this->counter);
    }

    /** @test */
    public function it_can_handle_timeout()
    {
        $pool = Pool::create()
            ->maximumExecutionTime(0);

        for ($i = 0; $i < 5; $i++) {
            $pool->add(function () {
                sleep(1);
            })->timeout(function () {
                $this->counter += 1;
            });
        }

        $pool->wait();

        $this->assertEquals(5, $this->counter);
    }

    /** @test */
    public function it_can_handle_exceptions()
    {
        $pool = Pool::create();

        for ($i = 0; $i < 5; $i++) {
            $pool->add(function () {
                throw new Exception('test');
            })->catch(function (Exception $e) {
                $this->assertEquals('test', $e->getMessage());

                $this->counter += 1;
            });
        }

        $pool->wait();

        $this->assertEquals(5, $this->counter);
    }

    /** @test */
    public function it_can_handle_a_maximum_of_concurrent_processes()
    {
        $pool = Pool::create()
            ->concurrency(1);

        $startTime = microtime(true);

        for ($i = 0; $i < 5; $i++) {
            $pool->add(function () {
                usleep(1000);
            })->then(function () {
                $this->counter += 1;
            });
        }

        $pool->wait();

        $endTime = microtime(true);

        $executionTime = $endTime - $startTime;

        $this->assertTrue($executionTime >= 0.5);
        $this->assertEquals(5, $this->counter);
    }

    /** @test */
    public function it_works_with_helper_functions()
    {
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

        $this->assertEquals(10, $this->counter);
    }
}
