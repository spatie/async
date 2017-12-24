<?php

use Spatie\Async\Pool;
use Spatie\Async\Runtime\ParentRuntime;
use Spatie\Async\ParallelProcess;

if (! function_exists('async')) {
    function async(callable $callable): ParallelProcess
    {
        return ParentRuntime::createChildProcess($callable);
    }
}

if (! function_exists('await')) {
    function await(Pool $pool): array
    {
        return $pool->wait();
    }
}
