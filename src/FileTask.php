<?php

namespace Spatie\Async;

class FileTask
{
    public string $file;

    public function __construct(string $file)
    {
        $this->file = $file;
    }
}
