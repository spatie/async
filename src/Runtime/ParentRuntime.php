<?php

namespace Spatie\Async\Runtime;

use Closure;
use Spatie\Async\Pool;
use Spatie\Async\Process\Runnable;
use function Opis\Closure\serialize;
use Opis\Closure\SerializableClosure;
use function Opis\Closure\unserialize;
use Symfony\Component\Process\Process;
use Spatie\Async\Process\ParallelProcess;
use Spatie\Async\Process\SynchronousProcess;

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
		if (!defined('_DS'))
			define('_DS', DIRECTORY_SEPARATOR);
		
        if (! $autoloader) {
            $existingAutoloaderFiles = array_filter([
                __DIR__._DS.'..'._DS.'..'._DS.'..'._DS.'..'._DS.'autoload.php',
                __DIR__._DS.'..'._DS.'..'._DS.'..'._DS.'autoload.php',
                __DIR__._DS.'..'._DS.'..'._DS.'vendor'._DS.'autoload.php',
                __DIR__._DS.'..'._DS.'vendor'._DS.'autoload.php',
                __DIR__._DS.'vendor'._DS.'autoload.php',
                __DIR__._DS.'..'._DS.'..'._DS.'..'._DS.'vendor'._DS.'autoload.php',
            ], function (string $path) {
                return file_exists($path);
            });

            $autoloader = reset($existingAutoloaderFiles);
        }

        self::$autoloader = $autoloader;
        self::$childProcessScript = __DIR__._DS.'ChildRuntime.php';

        self::$isInitialised = true;
    }

    /**
     * @param \Spatie\Async\Task|callable $task
     *
     * @return \Spatie\Async\Process\Runnable
     */
    public static function createProcess($task): Runnable
    {
        if (! self::$isInitialised) {
            self::init();
        }

        //if (! Pool::isSupported()) {
        //    return SynchronousProcess::create($task, self::getId());
        //}

        $process = new Process(implode(' ', [
            'php',
            self::$childProcessScript,
            self::$autoloader,
            self::encodeTask($task),
        ]));

        return ParallelProcess::create($process, self::getId());
    }

    /**
     * @param \Spatie\Async\Task|callable $task
     *
     * @return string
     */
    public static function encodeTask($task): string
    {
        if ($task instanceof Closure) {
            $task = new SerializableClosure($task);
        }

        return base64_encode(serialize($task));
    }

    public static function decodeTask(string $task)
    {
        return unserialize(base64_decode($task));
    }

    protected static function getId(): string
    {
        if (self::$myPid === null) {
            self::$myPid = getmypid();
        }

        self::$currentId += 1;

        return (string) self::$currentId.(string) self::$myPid;
    }
}
