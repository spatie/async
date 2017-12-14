<?php

namespace Spatie\Async\Tests;

use Spatie\Async\Task;

class MyTask extends Task
{
    private $callable;

    public function __construct(callable $callable)
    {
        $this->callable = $callable;
    }

    public function execute()
    {
        call_user_func($this->callable);
    }
}
