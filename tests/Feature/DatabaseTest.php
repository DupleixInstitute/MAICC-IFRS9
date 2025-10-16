<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class DatabaseTest extends TestCase
{
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_database()
    {
       $this->assertDatabaseHas('users',[
           'first_name'=> 'Admin'
       ]);

       $this->assertDatabaseMissing('users',[
        'first_name'=> 'Lawine'
    ]);
    }
}
