<?php

namespace Spatie\Async\Runtime;

use Spatie\Async\ParallelProcess;
use function Opis\Closure\serialize;
use Opis\Closure\SerializableClosure;
use Symfony\Component\Process\Process;

class ParentRuntime
{
    /** @var bool */
    protected static $isInitialised = false;

    /** @var string */
    protected static $autoloader;

    /** @var string */
    protected static $childProcessScript;

    public static function init(string $autoloader = null)
    {
        if (! $autoloader) {
            $existingAutoloaderFiles = array_filter([
                __DIR__.'/../../../autoload.php',
                __DIR__.'/../../vendor/autoload.php',
                __DIR__.'/../../../vendor/autoload.php',
            ], function (string $path) {
                return file_exists($path);
            });

            $autoloader = reset($existingAutoloaderFiles);
        }

        self::$autoloader = $autoloader;
        self::$childProcessScript = __DIR__.'/ChildRuntime.php';

        self::$isInitialised = true;
    }

    public static function createChildProcess(callable $callable): ParallelProcess
    {
        if (! self::$isInitialised) {
            self::init();
        }

        $closure = new SerializableClosure($callable);

        $process = new Process(implode(' ', [
            'exec php',
            self::$childProcessScript,
            self::$autoloader,
            base64_encode(serialize($closure)),
        ]));

        return ParallelProcess::create($process);
    }
}
