<?php

namespace Spatie\Async;

use Exception;
use Spatie\Async\Tests\MyTask;
use PHPUnit\Framework\TestCase;

class PoolStatusTest extends TestCase
{
    /** @test */
    public function it_can_show_a_textual_status()
    {
        $pool = Pool::create();

        $pool->add(new MyTask());

        $this->assertContains('finished: 0', (string) $pool->status());

        await($pool);

        $this->assertContains('finished: 1', (string) $pool->status());
    }

    /** @test */
    public function it_can_show_a_textual_failed_status()
    {
        $pool = Pool::create();

        foreach (range(1, 5) as $i) {
            $pool->add(function () {
                throw new Exception('Test');
            })->catch(function () {
                // Do nothing
            });
        }

        $pool->wait();

        $this->assertContains('finished: 0', (string) $pool->status());
        $this->assertContains('failed: 5', (string) $pool->status());
        $this->assertContains('failed with Exception: Test', (string) $pool->status());
    }

    /** @test */
    public function it_can_show_timeout_status()
    {
        $pool = Pool::create()->timeout(0);

        foreach (range(1, 5) as $i) {
            $pool->add(function () {
                sleep(1000);
            })->catch(function () {
                // Do nothing
            });
        }

        $pool->wait();

        $this->assertContains('timeout: 5', (string) $pool->status());
    }
}
