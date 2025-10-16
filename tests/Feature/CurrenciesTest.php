<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Currency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class CurrenciesTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic feature test example.
     *
     * @return void
     */

    public function test_currency_can_be_created()
    {
        $user = user::factory()->create();
        $user->assignRole('admin');

        $response = $this->actingAs($user)
                         ->withSession(['banned'=>false])
                         ->post('/currency/store',[
                                    'name' => 'dollar',
                                    'decimals'=>'2',
        ]);    

        $response->assertRedirect('/currency');
    }

    public function test_currency_can_be_updated()
    {
        $this->withoutExceptionHandling();
        
        $user = user::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)
                        ->withSession(['banned'=>false])
                        ->post('/currency/store',[
                                'name' => 'pula',
                                 'decimals'=>'2',
        ]);  
        
        $currency = Currency::latest()->first();

        $response=  $this->actingAs($user)
                         ->withSession(['banned'=>false])
                         ->put('/currency/'.$currency->id.'/update',[
                                'name' => 'ZimDollar',
                                'decimals'=>'2',
        ]);  

        $this->assertEquals('ZimDollar',Currency::latest()->first()->name);
    }

    public function test_currency_can_be_deleted()
    {
        $user = user::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)
             ->withSession(['banned'=>false])
             ->post('/currency/store',[
                     'name' => 'dollar',
                     'decimals'=>'2',
        ]);  
        
        $currency = Currency::latest()->first();
        $count = Currency::count();

        $response=  $this->actingAs($user)
                         ->withSession(['banned'=>false])
                         ->delete('/currency/'.$currency->id.'/destroy');

        $this->assertCount($count-1, Currency::all());
        $response->assertRedirect('/currency');
    }
}
