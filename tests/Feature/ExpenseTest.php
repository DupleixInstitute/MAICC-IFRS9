<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Expense;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ExpenseTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_expense_can_be_created()
    {


        $user = user::factory()->create();
        $user->assignRole('admin');

        $response = $this->actingAs($user)
                         ->withSession(['banned' => false])
                         ->post('/expense/store',[
                                   'expense_type_id' => '1',
                                   'amount' => '10.00',
                                   'date' => '2022-01-15',
                                   'currency_id'=>'2'
            ]);

        $response->assertRedirect('/expense');
    }

    public function test_expense_can_be_updated()
    {
        

        $user = user::factory()->create();
        $user->assignRole('admin');

        $expense = Expense::factory()->create([

                'expense_type_id' => '2',
                'amount' => '20.00',
                'date' => '2022-01-16',
                'currency_id'=>'3'
        ]);

        $response = $this->actingAs($user)
                         ->withSession(['banned' => false])
                         ->put('/expense/'.$expense->id.'/update',[

                                    'expense_type_id' => '3',
                                    'amount' => '30.00',
                                    'date' => '2022-01-17',
                                    'currency_id'=>'4'

            ]);

        $expense->refresh();
        $response->assertRedirect('/expense');
        $this->assertEquals('30.00', $expense->amount);
        $this->assertEquals('2022-01-17', $expense->date);


    }

    public function test_expense_can_be_deleted()
    {
        

        $user = user::factory()->create();
        $user->assignRole('admin');

        $expense = Expense::factory()->create([

                'expense_type_id' => '2',
                'amount' => '20.00',
                'date' => '2022-01-16',
                'currency_id'=>'3'
        ]);

        $count = Expense::count();

        $response = $this->actingAs($user)
                         ->withSession(['banned' => false])
                         ->delete('/expense/'.$expense->id.'/destroy');

        $this->assertCount($count - 1, Expense::all());
        $response->assertRedirect('/expense');

    }
}
