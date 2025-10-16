<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ExpenseType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ExpenseTypeTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_expense_type_can_be_created()
    {
       
       $user = user::factory()->create();
       $user->assignRole('admin');

       $response = $this->actingAs($user)
                        ->withSession(['banned' => false])
                        ->post('/expense/type/store',[

                              'name' =>'medicine',
           ]);

       $response->assertRedirect('/expense/type');
           
    }

    public function test_expense_type_can_be_updated()
    {
        

       $user = user::factory()->create();
       $user->assignRole('admin');

       $expenseType = ExpenseType::factory()->create([

               'name'=>'op',
       ]);

       $response = $this->actingAs($user)
                        ->withSession(['banned' => false])
                        ->put('/expense/type/'.$expenseType->id.'/update',[

                                   'name'=>'medicine',
            ]);

    
       $expenseType->refresh();
       $response->assertRedirect('/expense/type');
       $this->assertEquals('medicine', $expenseType->name);
    }

    public function test_expense_type_can_be_deleted()
    {
       
        $user = user::factory()->create();
        $user->assignRole('admin');
 
        $expenseType = ExpenseType::factory()->create([
 
                'name'=>'op',
        ]);
 
        $count = ExpenseType::count();

        $response = $this->actingAs($user)
                         ->withSession(['banned' => false])
                         ->delete('/expense/type/'.$expenseType->id.'/destroy');

        $this->assertCount($count - 1, ExpenseType::all());
        $response->assertRedirect('/expense/type');

    }
}
