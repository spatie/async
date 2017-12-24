<?php

namespace Spatie\Async\Output;

use Throwable;

class SerializableException
{
    /** @string */
    protected $class;

    /** @string */
    protected $message;

    /** @string */
    protected $trace;

    public function __construct(Throwable $exception)
    {
        $this->class = get_class($exception);
        $this->message = $exception->getMessage();
        $this->trace = $exception->getTraceAsString();
    }

    public function asThrowable(): Throwable
    {
        /** @var Throwable $throwable */
        $throwable = new $this->class($this->message);

        return $throwable;
    }
}
