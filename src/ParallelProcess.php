<?php

namespace Spatie\Async;

use GuzzleHttp\Promise\Promise;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

class ParallelProcess
{
    protected $process;
    protected $inputStream;
    protected $promise;
    protected $internalId;

    public function __construct(Process $process, InputStream $inputStream)
    {
        $this->process = $process;
        $this->inputStream = $inputStream;
        $this->promise = new Promise();
        $this->internalId = uniqid(getmypid());
    }

    public static function create(Process $process, InputStream $inputStream): self
    {
        return new self($process, $inputStream);
    }

    public function start(): self
    {
        $this->process->start();

        $this->inputStream->close();

        return $this;
    }

    public function isRunning(): bool
    {
        return $this->process->isRunning();
    }

    public function isSuccessful(): bool
    {
        return $this->process->isSuccessful();
    }

    public function isTerminated(): bool
    {
        return $this->process->isTerminated();
    }

    public function output()
    {
        return unserialize($this->process->getOutput());
    }

    public function errorOutput()
    {
        return $this->process->getErrorOutput();
    }

    public function process(): Process
    {
        return $this->process;
    }

    public function promise(): Promise
    {
        return $this->promise;
    }

    public function internalId(): string
    {
        return $this->internalId;
    }

    public function pid(): ?string
    {
        return $this->process->getPid();
    }
}
