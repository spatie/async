<?php

namespace Spatie\Async;

use Exception;

class ParallelError extends Exception
{
    public static function fromException($exception): ParallelError
    {
        return new self($exception);
    }
}
