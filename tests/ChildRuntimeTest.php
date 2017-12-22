<?php

namespace Spatie\Async\Tests;

use Opis\Closure\SerializableClosure;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use function Opis\Closure\serialize;

class ChildRuntimeTest extends TestCase
{
    /** @test */
    public function it_can_run()
    {
        $bootstrap = __DIR__ . '/../src/Runtime/ChildRuntime.php';

        $autoloader = __DIR__ . '/../vendor/autoload.php';

        $serializedClosure = base64_encode(serialize(new SerializableClosure(function () {
            echo 'child';
        })));

        $process = new Process("php {$bootstrap} {$autoloader} {$serializedClosure}");

        $process->start();

        $process->wait();

        $this->assertContains('child', $process->getOutput());
    }
}
