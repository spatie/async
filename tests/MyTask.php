<?php

namespace Spatie\Async\Tests;

use Spatie\Async\Task;

class MyTask extends Task
{
    protected $i = 0;

    public function configure()
    {
        $this->i = 2;
    }

    public function execute()
    {
        return $this->i;
    }
}
