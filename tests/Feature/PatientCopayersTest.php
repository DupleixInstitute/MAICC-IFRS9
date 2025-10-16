<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Client;
use App\Models\LoanProductScoringAttribute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class PatientCopayersTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_patient_copayers_can_be_created()
    {
        $user = user::factory()->create();
        $user->assignRole('admin');

        $patient = Client::factory()->create();
        $patient = Client::first();

        $response = $this->actingAs($user)
                         ->withSession(['banned'=>false])
                         ->post('/patient/'.$patient->id.'/copayer/store',[

                                'co_payer_id' => '1',
                                'patient_relationship_id' =>'1',
                                'member_name' => 'lawine',
                                'membership_number' => '2',
        ]);

        $response->assertRedirect('/patient/'.$patient->id.'/copayer');

    }

    public function test_patient_copayer_can_be_updated()
    {
        $user = user::factory()->create();
        $user->assignRole('admin');

        $patient = Client::factory()->create();
        $patient = Client::first();

        $this->actingAs($user)
             ->withSession(['banned'=>false])
             ->post('/patient/'.$patient->id.'/copayer/store',[

            'co_payer_id' => '1',
            'patient_relationship_id' =>'1',
            'member_name' => 'lawine',
            'membership_number' => '2',
        ]);

        $patientCopayer = LoanProductScoringAttribute::first();

        $response = $this->actingAs($user)
                         ->withSession(['banned'=>false])
                         ->put('/patient/copayer/'.$patientCopayer->id.'/update',[

                               'co_payer_id' => '1',
                               'patient_relationship_id' =>'1',
                               'member_name' => 'Memory',
                               'membership_number' => '2',
        ]);

        $this->assertEquals('Memory',LoanProductScoringAttribute::first()->member_name);
        $response->assertRedirect('/patient/'.$patient->id.'/copayer');



    }

    public function test_patient_copayer_can_be_deleted()
    {
        $user = user::factory()->create();
        $user->assignRole('admin');

        $patient = Client::factory()->create();
        $patient = Client::first();

        $this->actingAs($user)
             ->withSession(['banned'=>false])
             ->post('/patient/'.$patient->id.'/copayer/store',[

            'co_payer_id' => '1',
            'patient_relationship_id' =>'1',
            'member_name' => 'lawine',
            'membership_number' => '2',
        ]);

        $patientCopayer = LoanProductScoringAttribute::first();
        $count = LoanProductScoringAttribute::count();

        $response = $this->actingAs($user)
                         ->withSession(['banned'=>false])
                         ->delete('/patient/copayer/'.$patientCopayer->id.'/destroy');

        $this->assertCount($count-1, LoanProductScoringAttribute::all());
        $response->assertRedirect('/patient/'.$patient->id.'/copayer');

    }
}
