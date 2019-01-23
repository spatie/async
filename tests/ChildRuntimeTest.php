<?php

namespace Spatie\Async\Tests;

use PHPUnit\Framework\TestCase;
use function Opis\Closure\serialize;
use Opis\Closure\SerializableClosure;
use Symfony\Component\Process\Process;

class ChildRuntimeTest extends TestCase
{
    /** @test */
    public function it_can_run()
    {
		if (!defined('_DS'))
			define('_DS', DIRECTORY_SEPARATOR);
        $bootstrap = __DIR__._DS.'..'._DS.'src'._DS.'Runtime'._DS.'ChildRuntime.php';

        $autoloader = __DIR__._DS.'..'._DS.'vendor'._DS.'autoload.php';

        $serializedClosure = base64_encode(serialize(new SerializableClosure(function () {
            echo 'child';
        })));

        $process = new Process("php {$bootstrap} {$autoloader} {$serializedClosure}");

        $process->start();

        $process->wait();

        $this->assertContains('child', $process->getOutput());
    }
}
