<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class AppointmentsTest extends TestCase
{

    use RefreshDatabase;
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_appointment_can_be_created()
    {

        $user = user::factory()->create();
        $user->assignRole('receptionist');

        $response = $this->actingAs($user)
                         ->withSession(['banned'=>false])
                         ->post('/appointment/store', [

                                 'start_date'=>'2022-01-15',
                                 'end_date' => '2022-02-12',
                                 'start_time'=> '10:00:00',
                                 'end_time'=>'12:00:00',
                                 'reason'=>'covid',
                                 'patient_first_name' =>'patient',
                                 'patient_last_name'=>'patient',
                                 'type' => 'appointment',
                                 'all_day' => '0',
        ]);

        $response->assertRedirect('/appointment');


    }

    public function test_appointment_can_be_updated()
    {

        $user = user::factory()->create();
        $user->assignRole('receptionist');

        $this->actingAs($user)
                         ->withSession(['banned'=>false])
                         ->post('/appointment/store', [

                            'start_date'=>'2022-01-15',
                            'end_date' => '2022-02-12',
                            'start_time'=> '10:00:00',
                            'end_time'=>'12:00:00',
                            'reason'=>'covid',
                            'patient_first_name' =>'patient',
                            'patient_last_name'=>'patient',
                            'type' => 'appointment',
                            'all_day' => '0',

        ]);

        $appointment=Event::first();

        $response = $this->actingAs($user)
                         ->withSession(['banned'=>false])
                         ->put('/appointment/'. $appointment->id.'/update',[

                            'start_date'=>'2022-01-15',
                            'end_date' => '2022-02-12',
                            'start_time'=> '10:00:00',
                            'end_time'=>'12:00:00',
                            'reason'=>'malaria',
                            'patient_first_name' =>'patient',
                            'patient_last_name'=>'patient',
                            'type' => 'appointment',
                            'all_day' => '0',
                            'status'=>'pending',

        ]);


        $this->assertEquals('malaria',Event::first()->reason);
        $response->assertRedirect('/appointment');

    }


    public function test_appointment_can_be_deleted()
    {

         $user = user::factory()->create();
         $user->assignRole('doctor');

         $this->actingAs($user)
                          ->withSession(['banned'=>false])
                          ->post('/appointment/store', [

                             'start_date'=>'2022-01-15',
                             'end_date' => '2022-02-12',
                             'start_time'=> '10:00:00',
                             'end_time'=>'12:00:00',
                             'reason'=>'covid',
                             'patient_first_name' =>'patient',
                             'patient_last_name'=>'patient',
                             'type' => 'appointment',
                             'all_day' => '0',

         ]);

         $appointment=Event::first();
         $count = Event::count();



        $response = $this->actingAs($user)
                         ->withSession(['banned'=>false])
                         ->delete('/appointment/'. $appointment->id.'/destroy');

        $this->assertCount($count-1, Event::all());

        $response->assertRedirect('/appointment');


    }
}
