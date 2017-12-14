<?php

namespace Spatie\Async;

use Exception;
use Spatie\Async\Output\ErrorProcessOutput;
use Spatie\Async\Output\ProcessOutput;
use Throwable;

class Runtime
{
    protected $maximumExecutionTime = 300;

    public function maximumExecutionTime(int $maximumExecutionTime): self
    {
        $this->maximumExecutionTime = $maximumExecutionTime;

        return $this;
    }

    public function start(Process $process): Process
    {
        socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $sockets);

        [$parentSocket, $childSocket] = $sockets;

        if (($pid = pcntl_fork()) == 0) {
            $this->executeChildProcess($parentSocket, $childSocket, $process);

            exit;
        }

        socket_close($parentSocket);

        return $process
            ->setPid($pid)
            ->setSocket($childSocket)
            ->setStartTime(time());
    }

    public function handleFinishedProcess(Process $process): void
    {
        $output = $this->readProcessOutputFromSocket($process);

        if ($output->isSuccess()) {
            $process->triggerSuccess($output->payload());
        } else {
            $process->triggerError($output->payload());
        }
    }

    public function handleRunningProcess(Process $process, $status): bool
    {
        if ($process->startTime() + $this->maximumExecutionTime < time() || pcntl_wifstopped($status)) {
            if (! posix_kill($process->pid(), SIGKILL)) {
                throw new Exception("Failed to kill {$process->pid()}: ".posix_strerror(posix_get_last_error()));
            }

            $process->triggerTimeout();

            return false;
        }

        return true;
    }

    protected function executeChildProcess($parentSocket, $childSocket, Process $process): void
    {
        try {
            $output = ProcessOutput::create($process->execute())->setSuccess();
        } catch (Throwable $e) {
            $output = ErrorProcessOutput::create($e);
        }

        socket_close($childSocket);

        socket_write($parentSocket, $output->serialize());

        socket_close($parentSocket);
    }

    protected function readProcessOutputFromSocket(Process $process): ProcessOutput
    {
        $rawOutput = '';

        while ($buffer = socket_read($process->socket(), 1024)) {
            $rawOutput .= $buffer;
        }

        $output = unserialize($rawOutput);

        socket_close($process->socket());

        return $output;
    }
}
