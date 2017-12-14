<?php

namespace Spatie\Async;

// TODO: Optional description property for easier debugging?
abstract class Process
{
    protected $internalId;
    protected $pid;
    protected $socket;
    protected $startTime;

    protected $successCallback;
    protected $errorCallback;
    protected $timeoutCallback;

    abstract public function execute();

    public function internalId()
    {
        return $this->internalId;
    }

    public function pid()
    {
        return $this->pid;
    }

    public function socket()
    {
        return $this->socket;
    }

    public function startTime()
    {
        return $this->startTime;
    }

    public function then(callable $callback): self
    {
        $this->successCallback = $callback;

        return $this;
    }

    public function catch(callable $callback): self
    {
        $this->errorCallback = $callback;

        return $this;
    }

    public function timeout(callable $callback): self
    {
        $this->timeoutCallback = $callback;

        return $this;
    }

    public function triggerSuccess($output = null)
    {
        if (! $this->successCallback) {
            return;
        }

        return call_user_func_array($this->successCallback, [$output]);
    }

    public function triggerError($output = null)
    {
        if (! $this->errorCallback) {
            return;
        }

        return call_user_func_array($this->errorCallback, [$output]);
    }

    public function triggerTimeout($output = null)
    {
        if (! $this->timeoutCallback) {
            return;
        }

        return call_user_func_array($this->timeoutCallback, [$output]);
    }

    public function setInternalId($internalId): self
    {
        $this->internalId = $internalId;

        return $this;
    }

    public function setPid($pid): self
    {
        $this->pid = $pid;

        return $this;
    }

    public function setSocket($socket): self
    {
        $this->socket = $socket;

        return $this;
    }

    public function setStartTime($startTime): self
    {
        $this->startTime = $startTime;

        return $this;
    }
}
