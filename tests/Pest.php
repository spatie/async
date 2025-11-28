<?php

use Spatie\Async\Pool;

pest()->beforeEach(function () {
    if (! Pool::isSupported()) {
        $this->markTestSkipped('Extensions `posix` and `pcntl` not supported.');
    }
})->in('Feature');

/**
 * Creates a closure that is not bound to the test class,
 * so it can be serialized and run in a child process.
 */
function childTask(Closure $closure): Closure
{
    return Closure::bind($closure, null, null);
}
