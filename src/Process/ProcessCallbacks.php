<?php

namespace Spatie\Async\Process;

trait ProcessCallbacks
{
    protected $successCallbacks = [];
    protected $errorCallbacks = [];
    protected $timeoutCallbacks = [];

    public function then(callable $callback): self
    {
        $this->successCallbacks[] = $callback;

        return $this;
    }

    public function catch(callable $callback): self
    {
        $this->errorCallbacks[] = $callback;

        return $this;
    }

    public function timeout(callable $callback): self
    {
        $this->timeoutCallbacks[] = $callback;

        return $this;
    }

    public function triggerSuccess()
    {
        if ($this->getErrorOutput()) {
            $this->triggerError();

            return;
        }

        $output = $this->getOutput();

        foreach ($this->successCallbacks as $callback) {
            call_user_func_array($callback, [$output]);
        }

        return $output;
    }

    public function triggerError()
    {
        $exception = $this->resolveErrorOutput();

        foreach ($this->errorCallbacks as $callback) {
            call_user_func_array($callback, [$exception]);
        }

        if (! $this->errorCallbacks) {
            throw $exception;
        }
    }

    public function triggerTimeout()
    {
        foreach ($this->timeoutCallbacks as $callback) {
            call_user_func_array($callback, []);
        }
    }
}
