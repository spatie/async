<?php

namespace Spatie\Async;

class CallableProcess extends Process
{
    private $callable;

    public function __construct(callable $callable)
    {
        $this->callable = $callable;
    }

    public function execute()
    {
        return call_user_func_array($this->callable, []);
    }
}
