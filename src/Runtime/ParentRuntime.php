<?php

namespace Spatie\Async\Runtime;

use Closure;
use Laravel\SerializableClosure\SerializableClosure;
use Spatie\Async\FileTask;
use Spatie\Async\Pool;
use Spatie\Async\Process\ParallelProcess;
use Spatie\Async\Process\Runnable;
use Spatie\Async\Process\SynchronousProcess;
use Symfony\Component\Process\Process;

class ParentRuntime
{
    /** @var bool */
    protected static $isInitialised = false;

    /** @var string */
    protected static $autoloader;

    /** @var string */
    protected static $childProcessScript;

    protected static $currentId = 0;

    protected static $myPid = null;

    public static function init(string $autoloader = null)
    {
        if (! $autoloader) {
            $existingAutoloaderFiles = array_filter([
                __DIR__ . '/../../../../autoload.php',
                __DIR__ . '/../../../autoload.php',
                __DIR__ . '/../../vendor/autoload.php',
                __DIR__ . '/../../../vendor/autoload.php',
            ], function (string $path) {
                return file_exists($path);
            });

            $autoloader = reset($existingAutoloaderFiles);
        }

        self::$autoloader = $autoloader;
        self::$childProcessScript = __DIR__ . '/ChildRuntime.php';

        self::$isInitialised = true;
    }

    /**
     * @param \Spatie\Async\Task|callable $task
     * @param int|null $outputLength
     *
     * @return \Spatie\Async\Process\Runnable
     */
    public static function createProcess($task, ?int $outputLength = null, ?string $binary = 'php', ?int $max_input_size = 100000): Runnable
    {
        if (! self::$isInitialised) {
            self::init();
        }

        if (! Pool::isSupported()) {
            return SynchronousProcess::create($task, self::getId());
        }

        $process = new Process([
            $binary,
            self::$childProcessScript,
            self::$autoloader,
            self::encodeTask($task, $max_input_size),
            $outputLength,
        ]);

        return ParallelProcess::create($process, self::getId());
    }

    /**
     * @param \Spatie\Async\Task|callable $task
     *
     * @return string
     */
    public static function encodeTask($task, ?int $maxTaskPayloadInBytes = 100000): string
    {
        if ($task instanceof Closure) {
            $task = new SerializableClosure($task);
        }

        $serializedTask = base64_encode(serialize($task));

        if (strlen($serializedTask) > $maxTaskPayloadInBytes) {
            // Write the serialized task to a temporary file and package it as a `FileTask`:
            $filename = tempnam(sys_get_temp_dir(), 'spatie_async_task_');
            file_put_contents($filename, $serializedTask);
            $file_task = new FileTask($filename);
            $serializedTask = base64_encode(serialize($file_task));
        }

        return $serializedTask;
    }

    public static function decodeTask(string $task)
    {
        $decodedTask = unserialize(base64_decode($task));

        if (get_class($decodedTask) === 'Spatie\Async\FileTask') {
            $filename = $decodedTask->file;
            $decodedTask = unserialize(base64_decode(file_get_contents($filename)));
            unlink($filename);
        }

        return $decodedTask;
    }

    protected static function getId(): string
    {
        if (self::$myPid === null) {
            self::$myPid = getmypid();
        }

        self::$currentId += 1;

        return (string)self::$currentId . (string)self::$myPid;
    }
}
