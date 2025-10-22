<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Prevent queue serialization issues with SQLite in tests
        \Illuminate\Support\Facades\Queue::fake();
    }
}
