<?php

namespace Spatie\Async;

use PHPUnit\Framework\TestCase;

class PoolTest extends TestCase
{
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
    public function it_can_handle_a_maximum_of_concurrent_processes()
    {
        $pool = Pool::create()
            ->concurrency(1);

        $startTime = microtime(true);

        for ($i = 0; $i < 5; $i++) {
            $pool->add(function () {
                usleep(1000);
            });
        }

        $pool->wait();

        $endTime = microtime(true);

        $executionTime = $endTime - $startTime;

        $this->assertTrue($executionTime >= 0.5);
    }
}
