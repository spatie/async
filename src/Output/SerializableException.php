<?php

namespace Spatie\Async\Output;

use Throwable;

class SerializableException
{
    protected $class;
    protected $message;
    protected $trace;

    public function __construct(Throwable $e)
    {
        $this->class = get_class($e);
        $this->message = $e->getMessage();
        $this->trace = $e->getTraceAsString();
    }

    public function asThrowable(): Throwable
    {
        /** @var Throwable $throwable */
        $throwable = new $this->class($this->message);

        return $throwable;
    }
}
