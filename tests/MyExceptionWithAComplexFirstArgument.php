<?php

namespace Spatie\Async\Tests;

use Exception;
use stdClass;

class MyExceptionWithAComplexFirstArgument extends Exception
{
    public $payload;

    public function __construct(stdClass $payload, string $message)
    {
        parent::__construct($message);
        $this->payload = $payload;
    }
}
