<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseTest extends TestCase
{
    use RefreshDatabase;

    /** The seeded database holds the installation's administrator and no one else by that name. */
    public function test_database()
    {
        $this->assertDatabaseHas('users', ['email' => 'admin@localhost.com']);
        $this->assertDatabaseMissing('users', ['email' => 'lawine@localhost.com']);
    }
}
