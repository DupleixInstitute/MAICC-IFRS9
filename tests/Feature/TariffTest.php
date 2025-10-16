<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Tariff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class TariffTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_tariffs_can_be_created()
    {

        $user = user::factory()->create();
        $user->assignRole('admin');

        $response = $this->actingAs($user)
                         ->withSession(['banned'=>false])
                         ->post('/tariff/store',[
                            'name' => 'hamilton',
                            'amount' =>'20.00',
        ]);    

        $response->assertRedirect('/tariff');

        
    }

    public function test_tariffs_can_be_updated()
    {


        $user = user::factory()->create();
        $user->assignRole('admin');

        $response = $this->actingAs($user)
                         ->withSession(['banned'=>false])
                         ->post('/tariff/store',[
                            'name' => 'hamilton',
                            'amount' =>'20.00',
        ]); 

        $tariff = Tariff::first();

        $response=  $this->actingAs($user)
                         ->withSession(['banned'=>false])
                         ->put('/tariff/'.$tariff->id.'/update',[
                            'name' => 'compound',
                            'amount' =>'20.00',
        ]);  

        $this->assertEquals('compound',Tariff::first()->name);
        $response->assertRedirect('/tariff');

    }

    public function test_tariffs_can_be_deleted()
    {

        $user = user::factory()->create();
        $user->assignRole('admin');

        $response = $this->actingAs($user)
                         ->withSession(['banned'=>false])
                         ->post('/tariff/store',[
                            'name' => 'hamilton',
                            'amount' =>'20.00',
        ]); 

        $tariff = Tariff::first();
        $count = Tariff::count();

        $response=  $this->actingAs($user)
                         ->withSession(['banned'=>false])
                         ->delete('/tariff/'.$tariff->id.'/destroy');

        $this->assertCount($count-1, Tariff::all());
                         $response->assertRedirect('/tariff');
                 

    }
}
