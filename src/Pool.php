<?php

namespace Spatie\Async;

use ArrayAccess;
use InvalidArgumentException;
use Spatie\Async\Process\Runnable;
use Spatie\Async\Runtime\ParentRuntime;
use Spatie\Async\Process\ParallelProcess;
use Spatie\Async\Process\SynchronousProcess;

class Pool implements ArrayAccess
{
    public static $forceSynchronous = false;

    protected $concurrency = 20;
    protected $tasksPerProcess = 1;
    protected $timeout = 300;
    protected $sleepTime = 50000;

    /** @var \Spatie\Async\Process\Runnable[] */
    protected $queue = [];

    /** @var \Spatie\Async\Process\Runnable[] */
    protected $inProgress = [];

    /** @var \Spatie\Async\Process\Runnable[] */
    protected $finished = [];

    /** @var \Spatie\Async\Process\Runnable[] */
    protected $failed = [];

    /** @var \Spatie\Async\Process\Runnable[] */
    protected $timeouts = [];

    protected $results = [];

    protected $status;

    public function __construct()
    {
        $this->registerListener();

        $this->status = new PoolStatus($this);
    }

    /**
     * @return static
     */
    public static function create()
    {
        return new static();
    }

    public static function isSupported(): bool
    {
        return
            function_exists('pcntl_async_signals')
            && function_exists('posix_kill')
            && ! self::$forceSynchronous;
    }

    public function concurrency(int $concurrency): self
    {
        $this->concurrency = $concurrency;

        return $this;
    }

    public function timeout(int $timeout): self
    {
        $this->timeout = $timeout;

        return $this;
    }

    public function autoload(string $autoloader): self
    {
        ParentRuntime::init($autoloader);

        return $this;
    }

    public function sleepTime(int $sleepTime): self
    {
        $this->sleepTime = $sleepTime;

        return $this;
    }

    public function notify()
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

    /**
     * @param \Spatie\Async\Process\Runnable|callable $process
     *
     * @return \Spatie\Async\Process\Runnable
     */
    public function add($process): Runnable
    {
        if (! is_callable($process) && ! $process instanceof Runnable) {
            throw new InvalidArgumentException('The process passed to Pool::add should be callable.');
        }

        if (! $process instanceof Runnable) {
            $process = ParentRuntime::createProcess($process);
        }

        $this->putInQueue($process);

        return $process;
    }

    public function wait(): array
    {
        while ($this->inProgress) {
            foreach ($this->inProgress as $process) {
                if ($process->getCurrentExecutionTime() > $this->timeout) {
                    $this->markAsTimedOut($process);
                }

                if ($process instanceof SynchronousProcess) {
                    $this->markAsFinished($process);
                }
            }

            if (! $this->inProgress) {
                break;
            }

            usleep($this->sleepTime);
        }

        return $this->results;
    }

    public function putInQueue(Runnable $process)
    {
        $this->queue[$process->getId()] = $process;

        $this->notify();
    }

    public function putInProgress(Runnable $process)
    {
        if ($process instanceof ParallelProcess) {
            $process->getProcess()->setTimeout($this->timeout);
        }

        $process->start();

        unset($this->queue[$process->getId()]);

        $this->inProgress[$process->getPid()] = $process;
    }

    public function markAsFinished(Runnable $process)
    {
        unset($this->inProgress[$process->getPid()]);

        $this->notify();

        $this->results[] = $process->triggerSuccess();

        $this->finished[$process->getPid()] = $process;
    }

    public function markAsTimedOut(Runnable $process)
    {
        unset($this->inProgress[$process->getPid()]);

        $this->notify();

        $process->triggerTimeout();

        $this->timeouts[$process->getPid()] = $process;
    }

    public function markAsFailed(Runnable $process)
    {
        unset($this->inProgress[$process->getPid()]);

        $this->notify();

        $process->triggerError();

        $this->failed[$process->getPid()] = $process;
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
     * @return \Spatie\Async\Process\Runnable[]
     */
    public function getQueue(): array
    {
        return $this->queue;
    }

    /**
     * @return \Spatie\Async\Process\Runnable[]
     */
    public function getFinished(): array
    {
        return $this->finished;
    }

    /**
     * @return \Spatie\Async\Process\Runnable[]
     */
    public function getFailed(): array
    {
        return $this->failed;
    }

    /**
     * @return \Spatie\Async\Process\Runnable[]
     */
    public function getTimeouts(): array
    {
        return $this->timeouts;
    }

    public function status(): PoolStatus
    {
        return $this->status;
    }

    protected function registerListener()
    {
        pcntl_async_signals(true);

        pcntl_signal(SIGCHLD, function ($signo, $status) {
            while (true) {
                $pid = pcntl_waitpid(-1, $processState, WNOHANG | WUNTRACED);

                if ($pid <= 0) {
                    break;
                }

                $process = $this->inProgress[$pid] ?? null;

                if (! $process) {
                    continue;
                }

                if ($status['status'] === 0) {
                    $this->markAsFinished($process);

                    continue;
                }

                $this->markAsFailed($process);
            }
        });
    }
}
