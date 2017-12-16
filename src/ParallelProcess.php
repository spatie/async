<?php

namespace Spatie\Async;

use Spatie\Async\Output\SerializableException;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

class ParallelProcess
{
    protected $process;
    protected $inputStream;
    protected $id;

    protected $successCallbacks = [];
    protected $errorCallbacks = [];
    protected $timeoutCallbacks = [];

    public function __construct(Process $process, InputStream $inputStream)
    {
        $this->process = $process;
        $this->inputStream = $inputStream;
        $this->id = uniqid(getmypid());
    }

    public static function create(Process $process, InputStream $inputStream): self
    {
        return new self($process, $inputStream);
    }

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

    public function start(): self
    {
        $this->process->start();

        $this->inputStream->close();

        return $this;
    }

    public function isRunning(): bool
    {
        return $this->process->isRunning();
    }

    public function isSuccessful(): bool
    {
        return $this->process->isSuccessful();
    }

    public function isTerminated(): bool
    {
        return $this->process->isTerminated();
    }

    public function output()
    {
        return unserialize($this->process->getOutput());
    }

    public function errorOutput()
    {
        return unserialize($this->process->getErrorOutput());
    }

    public function process(): Process
    {
        return $this->process;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function triggerSuccess()
    {
        $output = $this->output();

        foreach ($this->successCallbacks as $callback) {
            call_user_func_array($callback, [$output]);
        }
    }

    public function triggerError()
    {
        $output = $this->errorOutput();

        if ($output instanceof SerializableException) {
            $output = $output->asThrowable();
        }

        foreach ($this->errorCallbacks as $callback) {
            call_user_func_array($callback, [$output]);
        }
    }

    public function triggerTimeout()
    {
        foreach ($this->timeoutCallbacks as $callback) {
            call_user_func_array($callback, []);
        }
    }
}
