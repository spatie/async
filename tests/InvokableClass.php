<?php

namespace Spatie\Async\Tests;

class InvokableClass
{
    public function __invoke()
    {
        return 2;
    }
}
