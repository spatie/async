<?php

namespace Spatie\Async\Runtime;

use Spatie\Async\ParallelProcess;
use SuperClosure\Serializer;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

class ParentRuntime
{
    protected static $isInitialised = false;
    protected static $autoloader;
    /** @var Serializer */
    protected static $serializer;
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
        self::$childProcessScript = __DIR__ . '/ChildProcess.php';
        self::$serializer = new Serializer();

        self::$isInitialised = true;
    }

    public static function createChildProcess(callable $callable): ParallelProcess
    {
        if (!self::$isInitialised) {
            self::init();
        }

        $process = new Process(implode(' ', [
            'exec php',
            self::$childProcessScript,
            self::$autoloader,
            base64_encode(self::$serializer->serialize($callable))
        ]));

        return ParallelProcess::create($process);
    }
}
