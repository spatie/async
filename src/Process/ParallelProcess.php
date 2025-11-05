<?php

namespace Spatie\Async\Process;

use Spatie\Async\Output\ParallelError;
use Spatie\Async\Output\SerializableException;
use Symfony\Component\Process\Process;
use Throwable;

class ParallelProcess implements Runnable
{
    use ProcessCallbacks;
    protected $process;
    protected $id;
    protected $pid;

    protected $output = [];
    protected $capturedOtherOutput = [];
    protected $errorOutput = [];

    protected $startTime;

    public function __construct(Process $process, int $id)
    {
        $this->process = $process;
        $this->id = $id;
    }

    public static function create(Process $process, int $id): self
    {
        return new self($process, $id);
    }

    public function start(): self
    {
        $this->startTime = microtime(true);

        $this->process->start();

        $this->pid = $this->process->getPid();

        return $this;
    }

    public function stop($timeout = 0): self
    {
        $this->process->stop($timeout, SIGKILL);

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
            $this->readProcessOutput();
        }

        return $this->output;
    }

    public function getCapturedOtherOutput(): array
    {
        if (! $this->capturedOtherOutput) {
            $this->readProcessOutput();
        }

        return $this->capturedOtherOutput;
    }

    private function readProcessOutput(): void
    {
        $processOutput = $this->process->getOutput();
        $allOutput = array_map(fn($line) => json_decode($line, true) ?? $line, explode("\n", trim($processOutput)));

        $taskOutput = array_pop($allOutput);
        $this->capturedOtherOutput = $allOutput;

        if (!is_string($taskOutput)) {
            $this->errorOutput = 'Unexpected output: ' . var_export($taskOutput, true);
        } else {
            $childResult = @unserialize(base64_decode($taskOutput));

            if ($childResult === false || ! array_key_exists('output', $childResult)) {
                $this->errorOutput = $taskOutput;
            }
            $this->output = $childResult['output'];
        }
    }

    public function getErrorOutput()
    {
        if (! $this->errorOutput) {
            $processOutput = $this->process->getErrorOutput();

            $childResult = @unserialize(base64_decode($processOutput));

            if ($childResult === false || ! array_key_exists('output', $childResult)) {
                $this->errorOutput = $processOutput;
            } else {
                $this->errorOutput = $childResult['output'];
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

        if (! $exception instanceof Throwable) {
            $exception = ParallelError::fromException($exception);
        }

        return $exception;
    }
}
