<?php

namespace Spatie\Async;

abstract class Task
{
    abstract public function configure();

    abstract public function execute();

    public function __invoke()
    {
        $this->configure();

        return $this->execute();
    }
}
