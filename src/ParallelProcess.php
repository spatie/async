<?php

namespace Spatie\Async;

use Throwable;
use Symfony\Component\Process\Process;
use Spatie\Async\Output\SerializableException;

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

    protected $startTime;

    public function __construct(Process $process, string $id)
    {
        $this->process = $process;
        $this->id = $id;
    }

    public static function create(Process $process, string $id): self
    {
        return new self($process, $id);
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
        $this->startTime = microtime(true);

        $this->process->start();

        $this->pid = $this->process->getPid();

        return $this;
    }

    public function stop(): self
    {
        $this->process->stop(10, SIGKILL);

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

    public function getOutput()
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

    public function getErrorOutput()
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

    public function getProcess(): Process
    {
        return $this->process;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getPid(): ?string
    {
        return $this->pid;
    }

    public function getCurrentExecutionTime(): float
    {
        return microtime(true) - $this->startTime;
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

    protected function resolveErrorOutput(): Throwable
    {
        $exception = $this->getErrorOutput();

        if ($exception instanceof SerializableException) {
            $exception = $exception->asThrowable();
        }

        if (! $exception instanceof Throwable) {
            $exception = ParallelError::fromException($exception);
        }

        return $exception;
    }
}
