<?php

namespace Spatie\Async\Output;

use Exception;

class ParallelError extends Exception
{
    public static function fromException($exception): self
    {
        return new self($exception);
    }
}
