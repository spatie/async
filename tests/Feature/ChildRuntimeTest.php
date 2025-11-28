<?php

use Laravel\SerializableClosure\SerializableClosure;
use Symfony\Component\Process\Process;

it('can run', function () {
    $bootstrap = __DIR__.'/../../src/Runtime/ChildRuntime.php';

    $autoloader = __DIR__.'/../../vendor/autoload.php';

    $closure = Closure::bind(function () {
        echo 'interfere with output';

        return 'child';
    }, null, null);

    $serializedClosure = base64_encode(serialize(new SerializableClosure($closure)));

    $process = new Process([
        'php',
        $bootstrap,
        $autoloader,
        $serializedClosure,
        1024 * 10,
    ]);

    $process->start();

    $process->wait();

    $output = unserialize(base64_decode($process->getOutput()));

    expect($output)->toBeArray();
    expect($output)->toHaveKey('output');
    expect($output['output'])->toContain('child');
});
