<?php

use Spatie\Async\Pool;
use Spatie\Async\Runtime\ParentRuntime;

if (! function_exists('async')) {
    function async(callable $callable): \GuzzleHttp\Promise\Promise
    {
        return ParentRuntime::createChildProcess($callable)->promise();
    }
}

if (! function_exists('await')) {
    function await(Pool $pool): void
    {
        $pool->wait();
    }
}
