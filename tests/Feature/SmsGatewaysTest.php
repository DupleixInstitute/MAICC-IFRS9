<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\SmsGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class SmsGatewaysTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_sms_gateway_can_be_created()
    {
        $user = user::factory()->create();
        $user->assignRole('admin');

        $response = $this->actingAs($user)
                         ->withSession(['banned'=>false])
                         ->post('/sms_gateway/store',[
                                    'name' => 'econet',
        ]);    

        $response->assertRedirect('/sms_gateway');
    }

    public function test_sms_gateway_can_be_updated()
    {
        $user = user::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)
             ->withSession(['banned'=>false])
             ->post('/sms_gateway/store',[
                                    'name' => 'econet',
        ]);  
        
        $smsGateway = SmsGateway::latest()->first();

        $response = $this->actingAs($user)
                         ->withSession(['banned'=>false])
                         ->put('/sms_gateway/'.$smsGateway->id.'/update',[
                                    'name' => 'netone',
        ]); 

        $this->assertEquals('netone', SmsGateway::latest()->first()->name);

        $response->assertRedirect('/sms_gateway');
    }

    public function test_sms_gateway_can_be_deleted()
    {
        $user = user::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)
             ->withSession(['banned'=>false])
             ->post('/sms_gateway/store',[
                                    'name' => 'econet',
        ]);  
        
        $smsGateway = SmsGateway::latest()->first();
        $count = SmsGateway::count();
        
        $response = $this->actingAs($user)
                         ->withSession(['banned'=>false])
  
                         ->delete('/sms_gateway/'.$smsGateway->id.'/destroy');

        $this->assertCount($count-1, SmsGateway::all());
        $response->assertRedirect('/payment_type');

    }
}
