<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class BranchesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_branch_can_be_created()
    {

        $user = user::factory()->create();
        $user->assignRole('admin');

        $response = $this->actingAs($user)
            ->withSession(['banned' => false])
            ->post('/branch/store', [
                'name' => 'pharmacy',
                'open_date' => '2022-01-15',
            ]);

        $response->assertRedirect('/branch');


    }

    public function test_branch_can_be_updated()
    {
        $this->withoutExceptionHandling();

        $user = user::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)
            ->withSession(['banned' => false])
            ->post('/branch/store', [
                'name' => 'pharmacy',
                'open_date' => '2022-01-15',
            ]);
        //create a branch here
        $branch = Branch::factory()->create([
            'name' => 'Sample',
            'open_date' => '2022-01-01',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['banned' => false])
            ->put('/branch/' . $branch->id . '/update', [
                'name' => 'surgical',
                'open_date' => '2022-01-15',
            ]);
        //refresh this branch to reflect updated data
        $branch->refresh();
        $response->assertRedirect('/branch');
        $this->assertEquals('surgical', $branch->name);
        $this->assertEquals('2022-01-15', $branch->open_date);



    }

    public function test_branch_can_be_deleted()
    {

        $user = user::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)
            ->withSession(['banned' => false])
            ->post('/branch/store', [
                'name' => 'pharmacy',
                'open_date' => '2022-01-15',
            ]);

        $branch = Branch::first();
        $count = Branch::count();

        $response = $this->actingAs($user)
            ->withSession(['banned' => false])
            ->delete('/branch/' . $branch->id . '/destroy');


        $this->assertCount($count - 1, Branch::all());

        $response->assertRedirect('/branch');


    }

}
