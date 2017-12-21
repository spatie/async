<?php

namespace Spatie\Async\Tests;

use PHPUnit\Framework\TestCase;
use SuperClosure\Serializer;
use Symfony\Component\Process\Process;

class ChildRuntimeTest extends TestCase
{
    /** @test */
    public function it_can_run()
    {
        $bootstrap = __DIR__ . '/../src/Runtime/ChildRuntime.php';

        $autoloader = __DIR__ . '/../vendor/autoload.php';

        $serializer = new Serializer();

        $serializedClosure = base64_encode($serializer->serialize(function () {
            echo 'child';
        }));

        $process = new Process("php {$bootstrap} {$autoloader} {$serializedClosure}");

        $process->start();

        $process->wait();

        $this->assertContains('child', $process->getOutput());
    }
}
