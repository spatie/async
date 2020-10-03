<?php

namespace Spatie\Async\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    public function expectExceptionMessageRegularExpression(string $regularExpression): void
    {
        if (method_exists($this, 'expectExceptionMessageMatches')) {
            // PHPUnit 8.0+
            $this->expectExceptionMessageMatches($regularExpression);
        } else {
            // legacy PHPUnit 7.5
            $this->expectExceptionMessageRegExp($regularExpression);
        }
    }

    public function assertMatchesRegExp($pattern, $string)
    {
        if (method_exists($this, 'assertMatchesRegularExpression')) {
            // PHPUnit 10+
            $this->assertMatchesRegularExpression($pattern, $string);
        } else {
            // PHPUnit < 9.2
            $this->assertRegExp($pattern, $string);
        }
    }
}
