<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\InventoryProductUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class InventoryProductUnitTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_inventory_product_unit_can_be_created()
    {
        $this->withoutExceptionHandling();

        $user = user::factory()->create();
        $user->assignRole('admin');
 
        $response = $this->actingAs($user)
                         ->withSession(['banned' => false])
                         ->post('/inventory/unit/store',[

                            'name' => 'pharmacy',
                            'code' => 'hvgvh',
                         ]);  

        $response->assertRedirect('inventory/unit');
    }

    public function test_inventory_product_unit_can_be_updated()
    {
        $this->withoutExceptionHandling();

        $user = user::factory()->create();
        $user->assignRole('admin');

        $unit = InventoryProductUnit::factory()->create([

                            'name' => 'pharmacy',
                            'code' => 'hvgvh',
        ]);


        $response = $this->actingAs($user)
                        ->withSession(['banned' => false])
                        ->put('/inventory/unit/'.$unit->id.'/update',[

                            'name' => 'radiography',
                            'code' => 'hvgvh',
                        ]);

       $unit->refresh();
       $response->assertRedirect('/inventory/unit');
       $this->assertEquals('radiography', $unit->name);
    }

    public function test_inventory_product_unit_can_be_deleted()
    {
        $this->withoutExceptionHandling();

        $user = user::factory()->create();
        $user->assignRole('admin');

        $unit = InventoryProductUnit::factory()->create([

                            'name' => 'pharmacy',
                            'code' => 'hvgvh',
        ]);

        $count = InventoryProductUnit::count();

        $response = $this->actingAs($user)
                        ->withSession(['banned' => false])
                        ->delete('/inventory/unit/'.$unit->id.'/destroy');


        $this->assertCount($count - 1, InventoryProductUnit::all());
        $response->assertRedirect('/inventory/unit');
    }
}
