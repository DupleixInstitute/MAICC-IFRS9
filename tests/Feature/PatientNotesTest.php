<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Client;
use App\Models\LoanProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class PatientNotesTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_patient_note_can_be_created()
    {
        $user = user::factory()->create();
        $user->assignRole('admin');

        $patient = Client::factory()->create();
        $patient = Client::first();

        $response = $this->actingAs($user)
                         ->withSession(['banned'=>false])
                         ->post('/patient/'.$patient->id.'/note/store',[
                                'description' => 'status',
        ]);

        $response->assertRedirect('/patient/'.$patient->id.'/note');


    }

    public function test_patient_note_can_be_updated()
    {
        $user = user::factory()->create();
        $user->assignRole('admin');

        $patient = Client::factory()->create();
        $patient = Client::first();

        $this->actingAs($user)
             ->withSession(['banned'=>false])
             ->post('/patient/'.$patient->id.'/note/store',[
                        'description' => 'status',
        ]);

        $patientNote = LoanProduct::first();
        $response = $this->actingAs($user)
                         ->withSession(['banned'=>false])
                         ->put('/patient/note/'.$patientNote->id.'/update',[
                                   'description' => 'diagnosis',
        ]);

        $this->assertEquals('diagnosis', LoanProduct::first()->description);
        $response->assertRedirect('/patient/'.$patient->id.'/note');

    }

    public function test_patient_note_can_be_deleted()
    {
        $user = user::factory()->create();
        $user->assignRole('admin');

        $patient = Client::factory()->create();
        $patient = Client::first();

        $this->actingAs($user)
             ->withSession(['banned'=>false])
             ->post('/patient/'.$patient->id.'/note/store',[
                        'description' => 'status',
        ]);

        $patientNote = LoanProduct::first();
        $count = LoanProduct::count();

        $response = $this->actingAs($user)
                         ->withSession(['banned'=>false])
                         ->delete('/patient/note/'.$patientNote->id.'/destroy');

        $this->assertCount($count-1, LoanProduct::all());
        $response->assertRedirect('/patient/'.$patient->id.'/note');

    }
}
