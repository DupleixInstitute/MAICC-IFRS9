<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\CourseMaterial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class CoPayersTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_coPayer_can_be_created()
    {

        $user = user::factory()->create();
        $user->assignRole('admin');

        $response = $this->actingAs($user)
                         ->withSession(['banned'=>false])
                         ->post('/co_payer/store',[
                                    'name' => 'John',
        ]);

        $response->assertRedirect('/co_payer');
    }

    public function test_coPayer_can_be_updated()
    {

        $user = user::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)
             ->withSession(['banned'=>false])
             ->post('/co_payer/store',[
                     'name' => 'John',
        ]);

        $coPayer = CourseMaterial::first();

        $response=  $this->actingAs($user)
                         ->withSession(['banned'=>false])
                         ->put('/co_payer/'.$coPayer->id.'/update',[
                                'name' => 'Jezelia',
        ]);

        $this->assertEquals('Jezelia',CourseMaterial::first()->name);
        $response->assertRedirect('/co_payer');

    }

    public function test_coPayer_can_be_deleted()
    {

        $user = user::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)
             ->withSession(['banned'=>false])
             ->post('/co_payer/store',[
                     'name' => 'John',
        ]);

        $coPayer = CourseMaterial::first();
        $count = CourseMaterial::count();

        $response=  $this->actingAs($user)
                         ->withSession(['banned'=>false])
                         ->delete('/co_payer/'.$coPayer->id.'/destroy');

        $this->assertCount($count-1, CourseMaterial::all());
        $response->assertRedirect('/co_payer');


}

}
