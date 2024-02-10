<?php

namespace Spatie\Async\Output;

use Throwable;

class SerializableException
{
    protected string $class;

    protected string $message;

    protected string $trace;

    public function __construct(Throwable $exception)
    {
        $this->class = get_class($exception);
        $this->message = $exception->getMessage();
        $this->trace = $exception->getTraceAsString();
    }

    public function asThrowable(): Throwable
    {
        try {
            /** @var Throwable $throwable */
            $throwable = new $this->class($this->message."\n\n".$this->trace);
        } catch (Throwable $exception) {
            $throwable = new ParallelException($this->message, $this->class, $this->trace);
        }

        return $throwable;
    }
}
