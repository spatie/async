<?php

namespace Spatie\Async\Output;

use Throwable;

class SerializableException
{
    /** @var string */
    protected $class;

    /** @var string */
    protected $message;

    /** @var string */
    protected $trace;

    /** @var string */
    protected $details;

    /** @var int */
    private $code;

    /** @var null|Throwable */
    private $previous;

    public function __construct(Throwable $exception)
    {
        $this->class = get_class($exception);
        $this->message = $exception->getMessage();
        $this->code = $exception->getCode();
        $this->previous = $exception->getPrevious();
        $this->trace = $exception->getTraceAsString();
        if (method_exists($exception,'getDetails')) {
            $this->details = $exception->getDetails();
        }
    }

    public function asThrowable(): Throwable
    {
        try {
            /** @var Throwable $throwable */
            if (!empty($this->details)) {
                $throwable = new $this->class($this->message, $this->code, $this->previous, $this->details);
            } else {
                $throwable = new $this->class($this->message, $this->code, $this->previous);
            }

        } catch (Throwable $exception) {
            $throwable = new ParallelException($this->message, $this->class, $this->trace);
        }

        return $throwable;
    }
}
