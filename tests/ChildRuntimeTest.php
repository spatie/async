<?php

namespace Spatie\Async\Tests;

use Laravel\SerializableClosure\SerializableClosure;
use Symfony\Component\Process\Process;

class ChildRuntimeTest extends TestCase
{
    /** @test */
    public function it_can_run()
    {
        $bootstrap = __DIR__.'/../src/Runtime/ChildRuntime.php';

        $autoloader = __DIR__.'/../vendor/autoload.php';

        $serializedClosure = base64_encode(serialize(new SerializableClosure(function () {
            echo 'interfere with output';
            return 'child';
        })));

        $process = new Process([
            'php',
            $bootstrap,
            $autoloader,
            $serializedClosure,
        ]);

        $process->start();

        $process->wait();
        $output = unserialize(base64_decode($process->getOutput()));

        $this->assertIsArray($output);
        $this->assertArrayHasKey('output', $output);
        $this->assertStringContainsString('child', $output['output']);
    }
}
