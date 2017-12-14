<?php

namespace Spatie\Async;

use Exception;

class Pool implements \ArrayAccess
{
    protected $runtime;
    protected $concurrency = 20;

    /** @var \Spatie\Async\Process[] */
    protected $queue = [];
    /** @var \Spatie\Async\Process[] */
    protected $inProgress = [];
    /** @var \Spatie\Async\Process[] */
    protected $finished = [];
    /** @var \Spatie\Async\Process[] */
    protected $failed = [];

    public function __construct()
    {
        $this->runtime = new Runtime();
    }

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

    public function maximumExecutionTime(int $maximumExecutionTime): self
    {
        $this->runtime->maximumExecutionTime($maximumExecutionTime);

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

        $process = $this->run($process);

        $this->inProgress($process);
    }

    public function add($process): Process
    {
        if (! $process instanceof Process) {
            $process = new CallableProcess($process);
        }

        $process->setInternalId(uniqid(getmypid()));

        $this->queue($process);

        return $process;
    }

    public function run(Process $process): Process
    {
        return $this->runtime->start($process);
    }

    public function wait(): void
    {
        while (count($this->inProgress)) {
            foreach ($this->inProgress as $process) {
                $processStatus = pcntl_waitpid($process->pid(), $status, WNOHANG | WUNTRACED);

                if ($processStatus == $process->pid()) {
                    $this->runtime->handleFinishedProcess($process);

                    $this->finished($process);
                } elseif ($processStatus == 0) {
                    $isRunning = $this->runtime->handleRunningProcess($process, $status);

                    if (!$isRunning) {
                        $this->failed($process);
                    }
                } else {
                    throw new Exception("Could not reliably manage process {$process->pid()}");
                }
            }

            if (! count($this->inProgress)) {
                break;
            }

            usleep(100000);
        }
    }

    public function queue(Process $process): void
    {
        $this->queue[$process->internalId()] = $process;

        $this->notify();
    }

    public function inProgress(Process $process): void
    {
        unset($this->queue[$process->internalId()]);

        $this->inProgress[$process->pid()] = $process;
    }

    public function finished(Process $process): void
    {
        unset($this->inProgress[$process->pid()]);

        $this->finished[$process->pid()] = $process;

        $this->notify();
    }

    public function failed(Process $process): void
    {
        unset($this->inProgress[$process->pid()]);

        $this->failed[$process->pid()] = $process;

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
}
