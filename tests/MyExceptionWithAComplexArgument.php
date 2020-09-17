<?php

namespace Spatie\Async\Tests;

use Exception;
use stdClass;

class MyExceptionWithAComplexArgument extends Exception
{
    public $payload;

    public function __construct(string $message, stdClass $payload)
    {
        parent::__construct($message);
        $this->payload = $payload;
    }
}
