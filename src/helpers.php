<?php

use Spatie\Async\Pool;
use Spatie\Async\Process\Runnable;
use Spatie\Async\Runtime\ParentRuntime;

if (! function_exists('async')) {
    /**
     * @param \Spatie\Async\Task|callable $task
     *
     * @param string $binary
     * @return \Spatie\Async\Process\ParallelProcess
     */
    function async($task, string $binary = 'php'): Runnable
    {
        return ParentRuntime::createProcess($task, null, $binary);
    }
}

if (! function_exists('await')) {
    function await(Pool $pool): array
    {
        return $pool->wait();
    }
}
