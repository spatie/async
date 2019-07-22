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
        $bootstrap = __DIR__.'/../src/Runtime/ChildRuntime.php';

        $autoloader = __DIR__.'/../vendor/autoload.php';

        $serializedClosure = base64_encode(serialize(new SerializableClosure(function () {
            echo 'child';
        })));

        $process = new Process([
            'php',
            $bootstrap,
            $autoloader,
            $serializedClosure,
        ]);

        $process->start();

        $process->wait();

        $this->assertContains('child', $process->getOutput());
    }
}
