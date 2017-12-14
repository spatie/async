<?php

namespace Spatie\Async;

// TODO: Move to separate namespace
class ProcessOutput
{
    protected $payload;
    protected $success = false;

    public function __construct($payload)
    {
        $this->payload = $payload;
    }

    /**
     * @param $payload
     *
     * @return static
     */
    public static function create($payload)
    {
        return new static($payload);
    }

    public function setSuccess(bool $success = true): self
    {
        $this->success = $success;

        return $this;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function payload()
    {
        return $this->payload;
    }

    public function serialize()
    {
        return serialize($this);
    }
}
