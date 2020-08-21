<?php

namespace Spatie\Async\Output;

use ArgumentCountError;
use Throwable;

class SerializableException
{
    /** @var string */
    protected $class;

    /** @var string */
    protected $message;

    /** @var string */
    protected $trace;

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
        } catch (ArgumentCountError $exception) {
            $throwable = new \Exception($this->message."\n\n".$this->trace);
        }

        return $throwable;
    }
}
