<?php

namespace Spatie\Async\Process;

use Spatie\Async\Pool;

interface Runnable
{
    public function getId(): int;

    public function getPid(): ?int;

    public function start();

    public function withBinary(string $binary = Pool::DEFAULT_PHP_BINARY);

    /**
     * @param callable $callback
     *
     * @return static
     */
    public function then(callable $callback);

    /**
     * @param callable $callback
     *
     * @return static
     */
    public function catch(callable $callback);

    /**
     * @param callable $callback
     *
     * @return static
     */
    public function timeout(callable $callback);

    public function stop();

    public function getOutput();

    public function getErrorOutput();

    public function triggerSuccess();

    public function triggerError();

    public function triggerTimeout();

    public function getCurrentExecutionTime(): float;
}
