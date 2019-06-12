<?php

namespace Spatie\Async\Tests;

use Error;
use Exception;
use ParseError;
use Spatie\Async\Pool;
use PHPUnit\Framework\TestCase;
use Spatie\Async\Output\ParallelError;

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
    public function it_can_handle_typed_exceptions_via_catch_callback()
    {
        $pool = Pool::create();

        $myExceptionCount = 0;

        $otherExceptionCount = 0;

        $exceptionCount = 0;

        foreach (range(1, 5) as $i) {
            $pool
                ->add(function () {
                    throw new MyException('test');
                })
                ->catch(function (MyException $e) use (&$myExceptionCount) {
                    $this->assertRegExp('/test/', $e->getMessage());

                    $myExceptionCount += 1;
                })
                ->catch(function (OtherException $e) use (&$otherExceptionCount) {
                    $otherExceptionCount += 1;
                })
                ->catch(function (Exception $e) use (&$exceptionCount) {
                    $exceptionCount += 1;
                });
        }

        $pool->wait();

        $this->assertEquals(5, $myExceptionCount);
        $this->assertEquals(0, $otherExceptionCount);
        $this->assertEquals(0, $exceptionCount);
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
            fwrite(STDERR, 'test');
        })->catch(function (ParallelError $error) {
            $this->assertContains('test', $error->getMessage());
        });

        $pool->wait();
    }

    /** @test */
    public function deep_syntax_errors_are_thrown()
    {
        $pool = Pool::create();

        $pool->add(function () {
            new ClassWithSyntaxError();
        })->catch(function ($error) {
            $this->assertInstanceOf(ParseError::class, $error);
        });

        $pool->wait();
    }

    /** @test */
    public function it_can_handle_synchronous_exception()
    {
        Pool::$forceSynchronous = true;

        $pool = Pool::create();

        $pool->add(function () {
            throw new MyException('test');
        })->catch(function (MyException $e) {
            $this->assertRegExp('/test/', $e->getMessage());
        });

        $pool->wait();

        Pool::$forceSynchronous = false;
    }
}
