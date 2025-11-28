<?php

use Spatie\Async\Output\ParallelError;
use Spatie\Async\Output\ParallelException;
use Spatie\Async\Pool;
use Spatie\Async\Tests\ClassWithSyntaxError;
use Spatie\Async\Tests\MyClass;
use Spatie\Async\Tests\MyException;
use Spatie\Async\Tests\MyExceptionWithAComplexArgument;
use Spatie\Async\Tests\MyExceptionWithAComplexFirstArgument;
use Spatie\Async\Tests\OtherException;

it('can handle exceptions via catch callback', function () {
    $pool = Pool::create();

    foreach (range(1, 5) as $i) {
        $pool->add(childTask(function () {
            throw new MyException('test');
        }))->catch(function (MyException $e) {
            expect($e->getMessage())->toMatch('/test/');
        });
    }

    $pool->wait();

    expect($pool->getFailed())->toHaveCount(5, (string) $pool->status());
});

it('can handle complex exceptions via catch callback', function () {
    $pool = Pool::create();

    $originalExceptionCount = 0;
    $fallbackExceptionCount = 0;

    $pool
        ->add(childTask(function () {
            throw new MyExceptionWithAComplexArgument('test', (object) ['error' => 'wrong query']);
        }))
        ->catch(function (MyExceptionWithAComplexArgument $e) use (&$originalExceptionCount) {
            $originalExceptionCount += 1;
        })
        ->catch(function (ParallelException $e) use (&$fallbackExceptionCount) {
            $fallbackExceptionCount += 1;
            expect($e->getMessage())->toBe('test');
            expect($e->getOriginalClass())->toBe(MyExceptionWithAComplexArgument::class);
        });

    $pool
        ->add(childTask(function () {
            throw new MyExceptionWithAComplexFirstArgument((object) ['error' => 'wrong query'], 'test');
        }))
        ->catch(function (MyExceptionWithAComplexFirstArgument $e) use (&$originalExceptionCount) {
            $originalExceptionCount += 1;
        })
        ->catch(function (ParallelException $e) use (&$fallbackExceptionCount) {
            $fallbackExceptionCount += 1;
            expect($e->getMessage())->toBe('test');
            expect($e->getOriginalClass())->toBe(MyExceptionWithAComplexFirstArgument::class);
        });

    $pool->wait();

    expect($pool->getFailed())->toHaveCount(2, (string) $pool->status());
    expect($originalExceptionCount)->toBe(0);
    expect($fallbackExceptionCount)->toBe(2);
});

it('can handle typed exceptions via catch callback', function () {
    $pool = Pool::create();

    $myExceptionCount = 0;
    $otherExceptionCount = 0;
    $exceptionCount = 0;

    foreach (range(1, 5) as $i) {
        $pool
            ->add(childTask(function () {
                throw new MyException('test');
            }))
            ->catch(function (MyException $e) use (&$myExceptionCount) {
                expect($e->getMessage())->toMatch('/test/');
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

    expect($myExceptionCount)->toBe(5);
    expect($otherExceptionCount)->toBe(0);
    expect($exceptionCount)->toBe(0);
    expect($pool->getFailed())->toHaveCount(5, (string) $pool->status());
});

it('throws the exception if no catch callback', function () {
    $pool = Pool::create();

    $pool->add(childTask(function () {
        throw new MyException('test');
    }));

    $pool->wait();
})->throws(MyException::class);

it('throws fatal errors', function () {
    $pool = Pool::create();

    $pool->add(childTask(function () {
        throw new Error('test');
    }));

    $pool->wait();
})->throws(Error::class);

it('keeps the original trace', function () {
    $pool = Pool::create();

    $exceptionMessage = null;

    $pool->add(childTask(function () {
        $myClass = new MyClass();

        $myClass->throwException();
    }))->catch(function (MyException $exception) use (&$exceptionMessage) {
        $exceptionMessage = $exception->getMessage();
    });

    $pool->wait();

    expect($exceptionMessage)->toContain('Spatie\Async\Tests\MyClass->throwException()');
});

it('handles stderr as parallel error', function () {
    $pool = Pool::create();

    $errorMessage = null;

    $pool->add(childTask(function () {
        fwrite(STDERR, 'test');
    }))->catch(function (ParallelError $error) use (&$errorMessage) {
        $errorMessage = $error->getMessage();
    });

    $pool->wait();

    expect($errorMessage)->toContain('test');
});

it('handles stdout as parallel error', function () {
    $pool = Pool::create();

    $errorMessage = null;

    $pool->add(childTask(function () {
        fwrite(STDOUT, 'test');
    }))->then(function ($output) {
        throw new Exception('Child process output did not error on faulty output');
    })->catch(function (ParallelError $error) use (&$errorMessage) {
        $errorMessage = $error->getMessage();
    });

    $pool->wait();

    expect($errorMessage)->toContain('test');
});

it('throws deep syntax errors', function () {
    $pool = Pool::create();

    $caughtError = null;

    $pool->add(childTask(function () {
        new ClassWithSyntaxError();
    }))->catch(function ($error) use (&$caughtError) {
        $caughtError = $error;
    });

    $pool->wait();

    expect($caughtError)->toBeInstanceOf(ParseError::class);
});

it('can handle synchronous exception', function () {
    Pool::$forceSynchronous = true;

    $pool = Pool::create();

    $pool->add(childTask(function () {
        throw new MyException('test');
    }))->catch(function (MyException $e) {
        expect($e->getMessage())->toMatch('/test/');
    });

    $pool->wait();

    Pool::$forceSynchronous = false;
});
