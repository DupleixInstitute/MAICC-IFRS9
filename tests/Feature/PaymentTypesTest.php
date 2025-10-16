<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\PaymentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class PaymentTypesTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_payment_type_can_be_created()
    {
        $user = user::factory()->create();
        $user->assignRole('admin');

        $response = $this->actingAs($user)
                         ->withSession(['banned'=>false])
                         ->post('/payment_type/store',[
                                    'name' => 'transfer',
        ]);    

        $response->assertRedirect('/payment_type');
    }

    public function test_payment_type_can_be_updated()
    {
        $user = user::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)
             ->withSession(['banned'=>false])
             ->post('/payment_type/store',[
                     'name' => 'transfer',
        ]); 
        
        $paymentType = PaymentType::latest()->first();

        
        $response = $this->actingAs($user)
                         ->withSession(['banned'=>false])
                         ->put('/payment_type/'.$paymentType->id.'/update',[
                                    'name' => 'cash',
        ]);    

        $this->assertEquals('cash', PaymentType::latest()->first()->name);

        $response->assertRedirect('/payment_type');
    }

    public function test_payment_type_can_be_deleted()
    {
        $user = user::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)
             ->withSession(['banned'=>false])
             ->post('/payment_type/store',[
                     'name' => 'transfer',
        ]); 
        
        $paymentType = PaymentType::latest()->first();
        $count = PaymentType::count();

        
        $response = $this->actingAs($user)
                         ->withSession(['banned'=>false])
                         ->delete('/payment_type/'.$paymentType->id.'/destroy');

        $this->assertCount($count-1, PaymentType::all());
        $response->assertRedirect('/payment_type');
    }      
        
}
