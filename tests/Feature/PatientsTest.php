<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class PatientsTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_patient_can_be_created()
    {

            $user = user::factory()->create();
            $user->assignRole('admin');

            $response = $this->actingAs($user)
                             ->withSession(['banned'=>false])
                             ->post('/patient/store',[
                                'first_name' => 'Nenyasha',
                                'last_name' => 'Adams',
                                'gender' => 'female',
                                'status' => 'active',
                                'dob' => '1999-09-15',
                                'sponsor' => 'cash',

            ]);

            $response->assertRedirect('/patient');

    }

    public function test_patient_can_be_updated()
    {

            $user = user::factory()->create();
            $user->assignRole('admin');

            $this->actingAs($user)
                 ->withSession(['banned'=>false])
                 ->post('/patient/store',[
                                'first_name' => 'Nenyasha',
                                'last_name' => 'Adams',
                                'gender' => 'female',
                                'status' => 'active',
                                'dob' => '1999-09-15',
                                'sponsor' => 'cash',

            ]);

            $patient = Client::first();

              $response = $this->actingAs($user)
                             ->withSession(['banned'=>false])
                             ->put('/patient/'.$patient->id.'/update',[
                                'first_name' => 'Anenyasha',
                                'last_name' => 'Adams',
                                'gender' => 'female',
                                'status' => 'active',
                                'dob' => '1999-09-15',
                                'sponsor' => 'cash',

            ]);

            $this->assertEquals('Anenyasha', Client::first()->first_name);

            $response->assertRedirect('/patient');

    }

    public function test_patient_can_be_delted()
    {

            $user = user::factory()->create();
            $user->assignRole('admin');

            $this->actingAs($user)
                 ->withSession(['banned'=>false])
                 ->post('/patient/store',[
                                'first_name' => 'Nenyasha',
                                'last_name' => 'Adams',
                                'gender' => 'female',
                                'status' => 'active',
                                'dob' => '1999-09-15',
                                'sponsor' => 'cash',

            ]);

            $patient = Client::first();

            $count = Client::count();

              $response = $this->actingAs($user)
                             ->withSession(['banned'=>false])
                             ->delete('/patient/'.$patient->id.'/destroy');

            $this->assertCount($count-1, Client::all());
            $response->assertRedirect('/patient');

    }

}
