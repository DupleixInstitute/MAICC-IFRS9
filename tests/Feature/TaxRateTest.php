<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\TaxRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class TaxRateTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_tax_rate_can_be_created()
    {

        $user = user::factory()->create();
        $user->assignRole('admin');

        $response = $this->actingAs($user)
                         ->withSession(['banned'=>false])
                         ->post('/tax_rate/store',[
                                    'name' => 'transfer',
                                    'type'=>'bank',
                                    'amount'=>'100',
        ]);    

        $response->assertRedirect('/tax_rate');
    }

    public function test_tax_rate_can_be_updated()
    {

        $user = user::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)
             ->withSession(['banned'=>false])
             ->post('/tax_rate/store',[
                    'name' => 'transfer',
                    'type'=>'bank',
                    'amount'=>'100',


        ]); 
        $taxRate = TaxRate::first();

        $response = $this->actingAs($user)
                         ->withSession(['banned'=>false])
                         ->put('/tax_rate/'.$taxRate->id.'/update',[
                                    'name' => 'cash',
                                    'type'=>'bank',
                                    'amount'=>'100',

        ]);  
        

        $this->assertEquals('cash', TaxRate::first()->name);
        $response->assertRedirect('/tax_rate');
    }

    public function test_tax_rate_can_be_deleted()
    {

        $user = user::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)
             ->withSession(['banned'=>false])
             ->post('/tax_rate/store',[
                    'name' => 'transfer',
                    'type'=>'bank',
                    'amount'=>'100',

        ]); 
        $taxRate = TaxRate::latest()->first();
        $count = TaxRate::count();

        $response = $this->actingAs($user)
                         ->withSession(['banned'=>false])
                         ->delete('/tax_rate/'.$taxRate->id.'/destroy'); 
        
         $this->assertCount($count-1, TaxRate::all());
        $response->assertRedirect('/tax_rate');
    }
}
