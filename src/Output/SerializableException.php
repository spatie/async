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
    protected $serialized;

    public function __construct(Throwable $exception)
    {
        try{
            $this->serialized = serialize($exception);
        }
        catch(\Exception $e){
            $this->class = get_class($exception);
            $this->message = $exception->getMessage();
            $this->trace = $exception->getTraceAsString();
        }
    }

    public function asThrowable(): Throwable
    {
        if(isset($this->serialized)){
            /** @var Throwable $throwable */
            $throwable = unserialize($this->serialized);
        }
        else{
            try {
                $throwable = new $this->class($this->message."\n\n".$this->trace);
            } catch (Throwable $exception) {
                $throwable = new ParallelException($this->message, $this->class, $this->trace);
            }
        }

        return $throwable;
    }
}
