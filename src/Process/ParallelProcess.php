<?php

namespace Spatie\Async\Process;

use Spatie\Async\Output\ParallelError;
use Spatie\Async\Output\SerializableException;
use Spatie\Async\Pool;
use Spatie\Async\Runtime\ParentRuntime;
use Symfony\Component\Process\Process;
use Throwable;

class ParallelProcess implements Runnable
{
    protected $process;
    protected $binary = Pool::DEFAULT_PHP_BINARY;
    protected $task;
    protected $id;
    protected $pid;

    protected $output;
    protected $errorOutput;

    protected $startTime;

    private $outputLength;

    use ProcessCallbacks;

    public function __construct(callable $task, Process $process, int $id, ?int $outputLength)
    {
        $this->process      = $process;
        $this->task         = $task;
        $this->id           = $id;
        $this->outputLength = $outputLength;
    }

    public static function create(callable $task, Process $process, int $id, ?int $outputLength): self
    {
        return new self($task, $process, $id, $outputLength);
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

    public function withBinary(string $binary = Pool::DEFAULT_PHP_BINARY): self
    {
        $this->binary  = $binary;
        $this->process = ParentRuntime::createProcessExecutable($this->task, $this->outputLength, $this->binary);

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
        if (!$this->output) {
            $processOutput = $this->process->getOutput();

            $this->output = @unserialize(base64_decode($processOutput));

            if (!$this->output) {
                $this->errorOutput = $processOutput;
            }
        }

        return $this->output;
    }

    public function getErrorOutput()
    {
        if (!$this->errorOutput) {
            $processOutput = $this->process->getErrorOutput();

            $this->errorOutput = @unserialize(base64_decode($processOutput));

            if (!$this->errorOutput) {
                $this->errorOutput = $processOutput;
            }
        }

        return $this->errorOutput;
    }

    public function getProcess(): Process
    {
        return $this->process;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getPid(): ?int
    {
        return $this->pid;
    }

    public function getCurrentExecutionTime(): float
    {
        return microtime(true) - $this->startTime;
    }

    protected function resolveErrorOutput(): Throwable
    {
        $exception = $this->getErrorOutput();

        if ($exception instanceof SerializableException) {
            $exception = $exception->asThrowable();
        }

        if (!$exception instanceof Throwable) {
            $exception = ParallelError::fromException($exception);
        }

        return $exception;
    }
}
