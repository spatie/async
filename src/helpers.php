<?php

use Spatie\Async\Pool;
use Spatie\Async\Runtime\ParentRuntime;

if (! function_exists('async')) {
    function async(callable $callable): \Spatie\Async\ParallelProcess
    {
        return ParentRuntime::createChildProcess($callable);
    }
}

if (! function_exists('await')) {
    function await(Pool $pool): void
    {
        $pool->wait();
    }
}
