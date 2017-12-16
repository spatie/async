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

    public static function createChildProcess(callable $closure): ParallelProcess
    {
        if (!self::$isInitialised) {
            self::init();
        }

        $serializedClosure = self::$serializer->serialize($closure);

        $input = new InputStream();

        $input->write(self::$autoloader);
        $input->write("\r\n");
        $input->write($serializedClosure);

        $process = new Process('php ' . self::$childProcessScript);
        $process->setInput($input);

        $input->close();

        return ParallelProcess::create($process, $input);
    }
}
