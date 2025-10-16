<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ChartOfAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ChartOfAccountsTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_chart_of_account_can_be_created()
    {

        $user = user::factory()->create();
        $user->assignRole('admin');

        $response = $this->actingAs($user)
                         ->withSession(['banned'=>false])
                         ->post('/chart_of_account/store',[
                            'name'=>'cabs',
                            'gl_code'=>'5',
                            'account_type'=>'expense',
        ]);    

        $response->assertRedirect('/chart_of_account');

    }


    public function test_chart_of_account_can_be_updated()
    {

        $user = user::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)
             ->withSession(['banned'=>false])
             ->post('/chart_of_account/store',[
                         'name'=>'cabs',
                         'gl_code'=>'5',
                         'account_type'=>'expense',
        ]);    

        
        $chart_of_account = ChartOfAccount::first();

        $response=  $this->actingAs($user)
                         ->withSession(['banned'=>false])
                         ->put('/chart_of_account/'.$chart_of_account->id.'/update',[
                                    'name'=>'swift',
                                    'gl_code'=>'5',
                                    'account_type'=>'expense',
        ]);  

        $this->assertEquals('swift',ChartOfAccount::first()->name);
        $response->assertRedirect('/chart_of_account');


    }

    public function test_chart_of_account_can_be_deleted()
    {

        $user = user::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)
        ->withSession(['banned'=>false])
        ->post('/chart_of_account/store',[
                    'name'=>'cabs',
                    'gl_code'=>'5',
                    'account_type'=>'expense',
        ]);    

        
        
        $chart_of_account = ChartOfAccount::first();
        $count = ChartOfAccount::count();


        $response=  $this->actingAs($user)
                         ->withSession(['banned'=>false])
                         ->delete('/chart_of_account/'.$chart_of_account->id.'/destroy');

        $this->assertCount($count-1, ChartOfAccount::all());
         $response->assertRedirect('/chart_of_account');
    }
}
