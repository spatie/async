<?php

namespace Spatie\Async;

use Error;
use PHPUnit\Runner\Exception;
use Spatie\Async\Output\SerializableException;
use Symfony\Component\Process\Process;
use Throwable;

class ParallelProcess
{
    protected $process;
    protected $id;
    protected $pid;

    protected $successCallbacks = [];
    protected $errorCallbacks = [];
    protected $timeoutCallbacks = [];

    protected $output;
    protected $errorOutput;

    public function __construct(Process $process)
    {
        $this->process = $process;
        $this->id = uniqid(getmypid());
    }

    public static function create(Process $process): self
    {
        return new self($process);
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

        $this->pid = $this->process->getPid();

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
        if (! $this->output) {
            $processOutput = $this->process->getOutput();

            $this->output = @unserialize(base64_decode($processOutput));

            if (! $this->output) {
                $this->errorOutput = $processOutput;
            }
        }

        return $this->output;
    }

    public function errorOutput()
    {
        if (! $this->errorOutput) {
            $processOutput = $this->process->getErrorOutput();

            $this->errorOutput = @unserialize(base64_decode($processOutput));

            if (! $this->errorOutput) {
                $this->errorOutput = $processOutput;
            }
        }

        return $this->errorOutput;
    }

    public function process(): Process
    {
        return $this->process;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function pid(): ?string
    {
        return $this->pid;
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
        $exception = $this->errorOutput();

        if ($exception instanceof SerializableException) {
            $exception = $exception->asThrowable();
        }

        foreach ($this->errorCallbacks as $callback) {
            call_user_func_array($callback, [$exception]);
        }

        if (! $this->errorCallbacks) {
            if ($exception instanceof Throwable) {
                throw $exception;
            }

            throw new Error($exception);
        }
    }

    public function triggerTimeout()
    {
        foreach ($this->timeoutCallbacks as $callback) {
            call_user_func_array($callback, []);
        }
    }
}
