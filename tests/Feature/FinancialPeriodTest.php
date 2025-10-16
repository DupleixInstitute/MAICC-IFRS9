<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\FinancialPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class FinancialPeriodTest extends TestCase
{
    use RefreshDatabase; 
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_financial_period_can_be_created()
    {
        $this->withoutExceptionHandling();

        $user = user::factory()->create();
        $user->assignRole('admin');
 
        $response = $this->actingAs($user)
                         ->withSession(['banned' => false])
                         ->post('/financial_period/store',[

                                 'name' => 'midyear',
                                 'start_date' => '2022-01-16',
            ]);

        $response->assertRedirect('/financial_period');

    }

    public function test_financial_period_can_be_updated()
    {
        $this->withoutExceptionHandling();

        $user = user::factory()->create();
        $user->assignRole('admin');
        
        $financialPeriod = FinancialPeriod::factory()->create([
                 
            'name' => 'first',
            'start_date' => '2022-01-17',
        ]);

        $response = $this->actingAs($user)
                         ->withSession(['banned' => false])
                         ->put('/financial_period/'.$financialPeriod->id.'/update',[
                                
                            'name' => 'second',
                            'start_date' => '2022-01-18',
                         ]);


        $financialPeriod->refresh();
        $response->assertRedirect('/financial_period');
        $this->assertEquals('second', $financialPeriod->name);
    }

    public function test_financial_period_can_be_deleted()
    {
        $this->withoutExceptionHandling();

        $user = user::factory()->create();
        $user->assignRole('admin');
        
        $financialPeriod = FinancialPeriod::factory()->create([
                 
            'name' => 'first',
            'start_date' => '2022-01-17',
        ]);

        $count = FinancialPeriod::count();

        $response = $this->actingAs($user)
                         ->withSession(['banned' => false])
                         ->delete('/financial_period/'.$financialPeriod->id.'/destroy');

        $this->assertCount($count - 1, FinancialPeriod::all());
        $response->assertRedirect('/financial_period');


    }
}
