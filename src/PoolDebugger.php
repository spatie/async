<?php

namespace Spatie\Async;

use Spatie\Async\Output\SerializableException;

class PoolDebugger
{
    public static function statusForPool(Pool $pool): string
    {
        return self::summaryForPool($pool) . "\n"
            . self::statusForFailed($pool);
    }

    public static function summaryForPool(Pool $pool): string
    {
        $finished = $pool->getFinished();
        $failed = $pool->getFailed();
        $timeouts = $pool->getTimeouts();

        return 'finished: ' . count($finished)
            . ' - failed: ' . count($failed)
            . ' - timeouts: ' . count($timeouts);
    }

    public static function statusForFailed(Pool $pool): string
    {
        $failed = $pool->getFailed();

        $status = "\nFailed status:\n\n";

        foreach ($failed as $process) {
            $output = $process->getErrorOutput();

            if ($output instanceof SerializableException) {
                $output = get_class($output->asThrowable()) . ' ' . $output->asThrowable()->getMessage();
            }

            $status .= "{$process->getId()} failed with {$output}\n\n";
        }

        return $status;
    }
}
