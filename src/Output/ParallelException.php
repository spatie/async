<?php

namespace Spatie\Async\Output;

class ParallelException extends \Exception
{
    protected string $originalClass;

    protected string $originalTrace;

    public function __construct(string $message, string $originalClass, string $originalTrace)
    {
        parent::__construct($message);
        $this->originalClass = $originalClass;
        $this->originalTrace = $originalTrace;
    }

    public function getOriginalClass(): string
    {
        return $this->originalClass;
    }

    public function getOriginalTrace(): string
    {
        return $this->originalTrace;
    }
}
