<?php

namespace Spatie\Async\Process;

use Throwable;
use Spatie\Async\Task;

class SynchronousProcess implements Runnable
{
    protected $id;

    protected $task;

    protected $output;
    protected $errorOutput;
    protected $executionTime;

    use ProcessCallbacks;

    public function __construct(callable $task, int $id)
    {
        $this->id = $id;
        $this->task = $task;
    }

    public static function create(callable $task, int $id): self
    {
        return new self($task, $id);
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getPid(): ?int
    {
        return $this->getId();
    }

    public function start()
    {
        try {
            $startTime = microtime(true);

            $this->output = $this->task instanceof Task
                ? $this->task->execute()
                : call_user_func($this->task);

            $this->executionTime = microtime(true) - $startTime;
        } catch (Throwable $throwable) {
            $this->errorOutput = $throwable;
        }
    }

    public function stop()
    {
    }

    public function getOutput()
    {
        return $this->output;
    }

    public function getErrorOutput()
    {
        return $this->errorOutput;
    }

    public function getCurrentExecutionTime(): float
    {
        return $this->executionTime;
    }
}
