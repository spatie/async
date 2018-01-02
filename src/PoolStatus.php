<?php

namespace Spatie\Async;

use Spatie\Async\Output\SerializableException;

class PoolStatus
{
    protected $pool;

    public function __construct(Pool $pool)
    {
        $this->pool = $pool;
    }

    public function __toString(): string
    {
        return $this->lines(
            $this->summaryToString(),
            $this->failedToString()
        );
    }

    protected function lines(string ...$lines): string
    {
        return implode("\n", $lines);
    }

    protected function summaryToString(): string
    {
        $finished = $this->pool->getFinished();
        $failed = $this->pool->getFailed();
        $timeouts = $this->pool->getTimeouts();

        return 'finished: '.count($finished)
            .' - failed: '.count($failed)
            .' - timeouts: '.count($timeouts);
    }

    protected function failedToString(): string
    {
        $failed = $this->pool->getFailed();

        $status = '';

        foreach ($failed as $process) {
            $output = $process->getErrorOutput();

            if ($output instanceof SerializableException) {
                $output = get_class($output->asThrowable()).': '.$output->asThrowable()->getMessage();
            }

            $status = $this->lines($status, "{$process->getPid()} failed with {$output}");
        }

        return $status;
    }
}
