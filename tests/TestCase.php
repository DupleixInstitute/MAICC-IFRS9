<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        // Several legacy controllers call ini_set('max_execution_time', 300) for
        // their own long requests; in one PHPUnit process that clock then runs
        // for every test that follows, and the suite died at five minutes with
        // "Maximum execution time exceeded". The suite runs unbounded.
        set_time_limit(0);
    }
}
