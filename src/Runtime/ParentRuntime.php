<?php

namespace Spatie\Async\Runtime;

use Opis\Closure\SerializableClosure;
use Spatie\Async\ParallelProcess;
use Symfony\Component\Process\Process;
use function Opis\Closure\serialize;

class ParentRuntime
{
    protected static $isInitialised = false;
    protected static $autoloader;
    protected static $childProcessScript;

    public static function init()
    {
        $existingAutoloaderFiles = array_filter([
            __DIR__ . '/../../vendor/autoload.php',
            __DIR__ . '/../../../vendor/autoload.php',
        ], function (string $path) {
            return file_exists($path);
        });

        self::$autoloader = reset($existingAutoloaderFiles);
        self::$childProcessScript = __DIR__ . '/ChildRuntime.php';

        self::$isInitialised = true;
    }

    public static function createChildProcess(callable $callable): ParallelProcess
    {
        if (!self::$isInitialised) {
            self::init();
        }

        $closure = new SerializableClosure($callable);

        $process = new Process(implode(' ', [
            'exec php',
            self::$childProcessScript,
            self::$autoloader,
            base64_encode(serialize($closure))
        ]));

        return ParallelProcess::create($process);
    }
}
