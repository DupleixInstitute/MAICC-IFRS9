<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\PatientRelationship;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class PatientRelationshipsTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_patient_relationship_can_be_created()
    {
        $user = user::factory()->create();
        $user->assignRole('admin');

        $response = $this->actingAs($user)
                         ->withSession(['banned'=>false])
                         ->post('/patient_relationship/store',[
                                    'name' => 'brother',
        ]);    

        $response->assertRedirect('/patient_relationship');
    }
        
    public function test_patient_relationship_can_be_updated()
    {
        $user = user::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)
             ->withSession(['banned'=>false])
             ->post('/patient_relationship/store',[
                        'name' => 'brother',
        ]); 
        
        $patientRelationship = PatientRelationship::first();

        $response=  $this->actingAs($user)
                         ->withSession(['banned'=>false])
                         ->put('/patient_relationship/'.$patientRelationship->id.'/update',[
                                'name' => 'sister',
        ]);  

        $this->assertEquals('sister', PatientRelationship::first()->name);

        $response->assertRedirect('/patient_relationship');
    }

    public function test_patient_relationship_can_be_deleted()
    {
        $user = user::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)
             ->withSession(['banned'=>false])
             ->post('/patient_relationship/store',[
                        'name' => 'brother',
        ]); 
        
        $patientRelationship = PatientRelationship::first();

        $count = PatientRelationship::count();

        $response=  $this->actingAs($user)
                         ->withSession(['banned'=>false])
                         ->delete('/patient_relationship/'.$patientRelationship->id.'/destroy');

        $this->assertCount($count-1, PatientRelationship::all());
        $response->assertRedirect('/patient_relationship');

    }
        
}
