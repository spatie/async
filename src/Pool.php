<?php

namespace Spatie\Async;

use ArrayAccess;
use GuzzleHttp\Promise\Promise;
use Spatie\Async\Runtime\ParentRuntime;

class Pool implements ArrayAccess
{
    protected $concurrency = 20;
    protected $tasksPerProcess = 1;
    protected $maximumExecutionTime = 300;

    /** @var \Spatie\Async\ParallelProcess[] */
    protected $queue = [];
    /** @var \Spatie\Async\ParallelProcess[] */
    protected $inProgress = [];
    /** @var \Spatie\Async\ParallelProcess[] */
    protected $finished = [];
    /** @var \Spatie\Async\ParallelProcess[] */
    protected $failed = [];

    /**
     * @return static
     */
    public static function create()
    {
        return new static();
    }

    public function concurrency(int $concurrency): self
    {
        $this->concurrency = $concurrency;

        return $this;
    }

    public function tasksPerProcess(int $tasksPerProcess): self
    {
        $this->tasksPerProcess = $tasksPerProcess;

        return $this;
    }

    public function maximumExecutionTime(int $maximumExecutionTime): self
    {
        $this->maximumExecutionTime = $maximumExecutionTime;

        return $this;
    }

    public function notify(): void
    {
        if (count($this->inProgress) >= $this->concurrency) {
            return;
        }

        $process = array_shift($this->queue);

        if (! $process) {
            return;
        }

        $this->putInProgress($process);
    }

    public function add(callable $callable): Promise
    {
        $process = ParentRuntime::createChildProcess($callable);

        $this->putInQueue($process);

        return $process->promise();
    }

    public function wait(): void
    {
        while (count($this->inProgress)) {
            foreach ($this->inProgress as $process) {
                if ($process->isRunning()) {
                    continue;
                }

                if (!$process->isSuccessful()) {
                    $this->markAsFailed($process);

                    continue;
                }

                $this->markAsFinished($process);
            }

            if (count($this->inProgress)) {
                usleep(10000);
            }
        }
    }

    public function putInQueue(ParallelProcess $process): void
    {
        $this->queue[$process->internalId()] = $process;

        $this->notify();
    }

    public function putInProgress(ParallelProcess $process): void
    {
        $process->start();

        $process->process()->wait();

        unset($this->queue[$process->internalId()]);

        $this->inProgress[$process->internalId()] = $process;
    }

    public function markAsFinished(ParallelProcess $process): void
    {
        $process->promise()->resolve($process->output());

        unset($this->inProgress[$process->internalId()]);

        $this->finished[$process->internalId()] = $process;

        $this->notify();
    }

    public function markAsFailed(ParallelProcess $process): void
    {
        $process->promise()->reject($process->errorOutput());

        unset($this->inProgress[$process->internalId()]);

        $this->failed[$process->internalId()] = $process;

        $this->notify();
    }

    public function offsetExists($offset)
    {
        // TODO

        return false;
    }

    public function offsetGet($offset)
    {
        // TODO
    }

    public function offsetSet($offset, $value)
    {
        $this->add($value);
    }

    public function offsetUnset($offset)
    {
        // TODO
    }

    /**
     * @return \Spatie\Async\ParallelProcess[]
     */
    public function getFinished(): array
    {
        return $this->finished;
    }

    /**
     * @return \Spatie\Async\ParallelProcess[]
     */
    public function getFailed(): array
    {
        return $this->failed;
    }
}
