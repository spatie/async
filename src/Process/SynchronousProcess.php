<?php

namespace Spatie\Async\Process;

use Spatie\Async\Task;
use Throwable;

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

            if ($this->task instanceof Task) {
                $this->output = $this->task->execute();
            } else {
                $this->output = call_user_func($this->task);
            }

            $this->executionTime = $startTime - microtime(true);
        } catch (Throwable $throwable) {
            $this->errorOutput = $throwable;
        }
    }

    public function stop()
    {
        return null;
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
