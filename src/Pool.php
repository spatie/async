<?php

namespace Spatie\Async;

use Exception;

class Pool implements \ArrayAccess
{
    protected $runtime;
    protected $concurrency = 20;
    protected $tasksPerProcess = 1;

    /** @var \Spatie\Async\Task[] */
    protected $tasks = [];

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

    public function tasksPerProcess(int $tasksPerProcess): self
    {
        $this->tasksPerProcess = $tasksPerProcess;

        return $this;
    }

    public function maximumExecutionTime(int $maximumExecutionTime): self
    {
        $this->runtime->maximumExecutionTime($maximumExecutionTime);

        return $this;
    }

    public function notify(): void
    {
        if (count($this->tasks) >= $this->tasksPerProcess) {
            $this->scheduleTasks($this->tasksPerProcess);
        }

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

    public function add($process): ?Process
    {
        if ($process instanceof Task) {
            $this->queueTask($process);

            return null;
        }

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
        $this->scheduleTasks();

        while (count($this->inProgress)) {
            foreach ($this->inProgress as $process) {
                $processStatus = pcntl_waitpid($process->pid(), $status, WNOHANG | WUNTRACED);

                if ($processStatus == $process->pid()) {
                    $isSuccess = $this->runtime->handleFinishedProcess($process);

                    if ($isSuccess) {
                        $this->finished($process);
                    } else {
                        $this->failed($process);
                    }
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

    public function queueTask(Task $task): void
    {
        $this->tasks[] = $task;

        $this->notify();
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

    protected function scheduleTasks(?int $amount = null): void
    {
        $amount = $amount ?? count($this->tasks);

        $tasksToRun = array_splice($this->tasks, 0, $amount);

        if (! count($tasksToRun)) {
            return;
        }

        $this->add(new CallableProcess(function () use ($tasksToRun) {
            /** @var \Spatie\Async\Task $task */
            foreach ($tasksToRun as $task) {
                $task->execute();
            }
        }));
    }

    /**
     * @return \Spatie\Async\Process[]
     */
    public function getFinished(): array
    {
        return $this->finished;
    }

    /**
     * @return \Spatie\Async\Process[]
     */
    public function getFailed(): array
    {
        return $this->failed;
    }
}
