<?php

namespace Spatie\Async\Tests;

use Error;
use PHPUnit\Framework\TestCase;
use Spatie\Async\ParallelError;
use Spatie\Async\Pool;

class ErrorHandlingTest extends TestCase
{
    /** @test */
    public function it_can_handle_exceptions_via_catch_callback()
    {
        $pool = Pool::create();

        foreach (range(1, 5) as $i) {
            $pool->add(function () {
                throw new MyException('test');
            })->catch(function (MyException $e) {
                $this->assertRegExp('/test/', $e->getMessage());
            });
        }

        $pool->wait();

        $this->assertCount(5, $pool->getFailed(), (string) $pool->status());
    }

    /** @test */
    public function it_throws_the_exception_if_no_catch_callback()
    {
        $this->expectException(MyException::class);
        $this->expectExceptionMessageRegExp('/test/');

        $pool = Pool::create();

        $pool->add(function () {
            throw new MyException('test');
        });

        $pool->wait();
    }

    /** @test */
    public function it_throws_fatal_errors()
    {
        $this->expectException(Error::class);
        $this->expectExceptionMessageRegExp('/test/');

        $pool = Pool::create();

        $pool->add(function () {
            throw new Error('test');
        });

        $pool->wait();
    }

    /** @test */
    public function it_keeps_the_original_trace()
    {
        $pool = Pool::create();

        $pool->add(function () {
            $myClass = new MyClass();

            $myClass->throwException();
        })->catch(function (MyException $exception) {
            $this->assertContains('Spatie\Async\Tests\MyClass->throwException()', $exception->getMessage());
        });

        $pool->wait();
    }

    /** @test */
    public function it_handles_stderr_as_parallel_error()
    {
        $pool = Pool::create();

        $pool->add(function () {
            fwrite(STDERR, "test");
        })->catch(function (ParallelError $error) {
            $this->assertContains('test', $error->getMessage());
        });

        $pool->wait();
    }
}
