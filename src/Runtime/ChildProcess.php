<?php

try {
    $autoloader = $argv[1];
    $serializedClosure = base64_decode($argv[2]);

    if (!$autoloader) {
        throw new InvalidArgumentException('No autoloader provided in child process.');
    }

    if (!file_exists($autoloader)) {
        throw new InvalidArgumentException("Could not find autoloader in child process: {$autoloader}");
    }

    if (!$serializedClosure) {
        throw new InvalidArgumentException("No valid closure was passed to the child process.");
    }

    require_once $autoloader;

    $serializer = new SuperClosure\Serializer();

    $closure = $serializer->unserialize($serializedClosure);

    $output = call_user_func($closure);

    fputs(STDOUT, serialize($output));

    exit(0);
} catch (Throwable $e) {
    require_once __DIR__ . '/../Output/SerializableException.php';

    $output = new \Spatie\Async\Output\SerializableException($e);

    fputs(STDERR, serialize($output));

    exit(1);
}
