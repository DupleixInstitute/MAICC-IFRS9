<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\InventoryWarehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class InventoryWarehouseTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_inventory_warehouse_can_be_created()
    {
        $this->withoutExceptionHandling();

        $user = user::factory()->create();
        $user->assignRole('admin');
 
        $response = $this->actingAs($user)
                         ->withSession(['banned' => false])
                         ->post('/inventory/warehouse/store',[

                            'name' => 'hhgh',
                            
                         ]);  

        $response->assertRedirect('inventory/warehouse');
    }

    public function test_inventory_warehouse_can_be_updated()
    {
        $this->withoutExceptionHandling();

        $user = user::factory()->create();
        $user->assignRole('admin');

        $warehouse = InventoryWarehouse::factory()->create([

                            'name' => 'www',
                            
        ]);


        $response = $this->actingAs($user)
                        ->withSession(['banned' => false])
                        ->put('/inventory/warehouse/'.$warehouse->id.'/update',[

                            'name' => 'uuu',
                            
                        ]);

       $warehouse->refresh();
       $response->assertRedirect('/inventory/warehouse');
       $this->assertEquals('uuu', $warehouse->name);
    }

    public function test_inventory_warehouse_can_be_deleted()
    {
        $this->withoutExceptionHandling();

        $user = user::factory()->create();
        $user->assignRole('admin');

        $warehouse = InventoryWarehouse::factory()->create([

                            'name' => 'sds',
                            
        ]);

        $count = InventoryWarehouse::count();

        $response = $this->actingAs($user)
                        ->withSession(['banned' => false])
                        ->delete('/inventory/warehouse/'.$warehouse->id.'/destroy');


        $this->assertCount($count - 1, InventoryWarehouse::all());
        $response->assertRedirect('/inventory/warehouse');
    }
}
