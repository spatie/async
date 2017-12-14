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

    public abstract function execute();

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

    public function then(callable $callback): Process
    {
        $this->successCallback = $callback;

        return $this;
    }

    public function catch(callable $callback): Process
    {
        $this->errorCallback = $callback;

        return $this;
    }

    public function timeout(callable $callback): Process
    {
        $this->timeoutCallback = $callback;

        return $this;
    }

    public function triggerSuccess($output = null)
    {
        if (! $this->successCallback) {
            return null;
        }

        return call_user_func_array($this->successCallback, [$output]);
    }

    public function triggerError($output = null)
    {
        if (! $this->errorCallback) {
            return null;
        }

        return call_user_func_array($this->errorCallback, [$output]);
    }

    public function triggerTimeout($output = null)
    {
        if (! $this->timeoutCallback) {
            return null;
        }

        return call_user_func_array($this->timeoutCallback, [$output]);
    }

    public function setInternalId($internalId): Process
    {
        $this->internalId = $internalId;

        return $this;
    }

    public function setPid($pid): Process
    {
        $this->pid = $pid;

        return $this;
    }

    public function setSocket($socket): Process
    {
        $this->socket = $socket;

        return $this;
    }

    public function setStartTime($startTime): Process
    {
        $this->startTime = $startTime;

        return $this;
    }
}
