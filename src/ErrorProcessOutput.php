<?php

namespace Spatie\Async;

use Throwable;
use InvalidArgumentException;

// TODO: Move to separate namespace
class ErrorProcessOutput extends ProcessOutput
{
    /**
     * @param $payload
     *
     * @return static
     */
    public static function create($payload)
    {
        if (! $payload instanceof Throwable) {
            throw new InvalidArgumentException('ErrorProcessOutput only accepts Throwables.');
        }

        $payload = [
            'class' => get_class($payload),
            'message' => $payload->getMessage(),
        ];

        return new static($payload);
    }

    public function payload(): Throwable
    {
        $class = $this->payload['class'] ?? null;
        $message = $this->payload['message'] ?? null;

        $payload = new $class($message);

        return $payload;
    }
}
