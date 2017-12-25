<?php

use Spatie\Async\Pool;
use Spatie\Async\ParallelProcess;
use Spatie\Async\Runtime\ParentRuntime;

if (! function_exists('async')) {
    /**
     * @param \Spatie\Async\Task|callable $task
     *
     * @return \Spatie\Async\ParallelProcess
     */
    function async($task): ParallelProcess
    {
        return ParentRuntime::createChildProcess($task);
    }
}

if (! function_exists('await')) {
    function await(Pool $pool): array
    {
        return $pool->wait();
    }
}
