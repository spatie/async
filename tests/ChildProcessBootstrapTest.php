<?php

namespace Spatie\Async\Tests;

use PHPUnit\Framework\TestCase;
use SuperClosure\Serializer;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

class ChildProcessBootstrapTest extends TestCase
{
    /** @test */
    public function it_can_run()
    {
        $bootstrap = __DIR__ . '/../src/Runtime/ChildProcess.php';

        $autoloader = __DIR__ . '/../vendor/autoload.php';

        $serializer = new Serializer();

        $serializedClosure = $serializer->serialize(function () {
            echo 'child';
        });

        $input = new InputStream();
        $input->write($autoloader);
        $input->write("\r\n");
        $input->write($serializedClosure);

        $process = new Process("php {$bootstrap}");
        $process->setInput($input);

        $process->start();

        $input->close();

        $process->wait();

        $this->assertEquals('child', $process->getOutput());
    }
}
