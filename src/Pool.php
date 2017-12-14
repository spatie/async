<?php

namespace Spatie\Async;

use Exception;
use Throwable;

class Pool
{
    protected $concurrency = 20;
    protected $maximumExecutionTime = 300;

    /** @var \Spatie\Async\Process[] */
    protected $queue = [];
    /** @var \Spatie\Async\Process[] */
    protected $inProgress = [];
    /** @var \Spatie\Async\Process[] */
    protected $finished = [];
    /** @var \Spatie\Async\Process[] */
    protected $failed = [];

    /**
     * @return static
     */
    public static function create()
    {
        return new static();
    }

    public function concurrency(int $concurrency): Pool
    {
        $this->concurrency = $concurrency;

        return $this;
    }

    public function maximumExecutionTime(int $maximumExecutionTime): Pool
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

        if (!$process) {
            return;
        }

        $process = $this->run($process);

        $this->inProgress($process);
    }

    public function add($process): Process
    {
        if (!$process instanceof Process) {
            $process = new CallableProcess($process);
        }

        $process->setInternalId(uniqid(getmypid()));

        $this->queue($process);

        return $process;
    }

    public function run(Process $process): Process
    {
        socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $sockets);

        [$parentSocket, $childSocket] = $sockets;

        if (($pid = pcntl_fork()) == 0) {
            try {
                $output = ProcessOutput::create($process->execute())->setSuccess();
            } catch (Throwable $e) {
                $output = ErrorProcessOutput::create($e);
            }

            socket_close($childSocket);

            socket_write($parentSocket, $output->serialize());

            socket_close($parentSocket);

            exit;
        }

        socket_close($parentSocket);

        return $process
            ->setPid($pid)
            ->setSocket($childSocket)
            ->setStartTime(time());
    }

    public function wait(): void
    {
        while (count($this->inProgress)) {
            foreach ($this->inProgress as $process) {
                $processStatus = pcntl_waitpid($process->pid(), $status, WNOHANG | WUNTRACED);

                if ($processStatus == $process->pid()) {
                    /** @var \Spatie\Async\ProcessOutput $output */
                    $output = unserialize(socket_read($process->socket(), 4096));

                    socket_close($process->socket());

                    if ($output->isSuccess()) {
                        $process->triggerSuccess($output->payload());
                    } else {
                        $process->triggerError($output->payload());
                    }

                    $this->finished($process);
                } else if ($processStatus == 0) {
                    if ($process->startTime() + $this->maximumExecutionTime < time() || pcntl_wifstopped($status)) {
                        if (!posix_kill($process->pid(), SIGKILL)) {
                            throw new Exception("Failed to kill {$process->pid()}: " . posix_strerror(posix_get_last_error()));
                        }

                        $process->triggerTimeout();

                        $this->failed($process);
                    }
                } else {
                    throw new Exception("Could not reliably manage process {$process->pid()}");
                }
            }

            if (!count($this->inProgress)) {
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
}
