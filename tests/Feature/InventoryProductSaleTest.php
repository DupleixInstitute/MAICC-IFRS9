<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\InventoryProductSale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class InventoryProductSaleTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_inventory_product_sale_can_be_created()
    {
        $this->withoutExceptionHandling();

        $user = user::factory()->create();
        $user->assignRole('admin');
 
        $response = $this->actingAs($user)
                         ->withSession(['banned' => false])
                         ->post('/inventory/sale/store',[

                            'sale_date' => '2022-01-16',
                            'sale_time' => '08:30:00',
                            'inventory_warehouse_id' => '1',
                            'items' => 'brufen',
                            'status' => 'done',
                            


                         ]);

        $response->assertRedirect('inventory/sale');
    }

    public function test_inventory_product_sale_can_be_updated()
    {
        $this->withoutExceptionHandling();

        $user = user::factory()->create();
        $user->assignRole('admin');
        
        $sale = InventoryProductSale::factory()->create([
                            'sale_date' => '2022-01-16',
                            'sale_time' => '08:30:00',
                            'inventory_warehouse_id' => '1',
                            'items' => 'brufen',
                            'status' => 'done',

        ]);

        $response = $this->actingAs($user)
                        ->withSession(['banned' => false])
                        ->put('/inventory/sale/'.$sale->id.'/update',[

                            'sale_date' => '2022-01-16',
                            'sale_time' => '08:30:00',
                            'inventory_warehouse_id' => '1',
                            'items' => 'brufen',
                            'status' => 'completed',
 

                        ]);

       $sale->refresh();
       $response->assertRedirect('/inventory/sale');
       $this->assertEquals('completed', $sale->status);
    }

    public function test_inventory_product_sale_can_be_deleted()
    {
        $this->withoutExceptionHandling();

        $user = user::factory()->create();
        $user->assignRole('admin');
        
        $sale = InventoryProductSale::factory()->create([

                            'sale_date' => '2022-01-16',
                            'sale_time' => '08:30:00',
                            'inventory_warehouse_id' => '1',
                            'items' => 'brufen',
                            'status' => 'done',

        ]);

        $count = InventoryProductSale::count();

        $response = $this->actingAs($user)
                        ->withSession(['banned' => false])
                        ->delete('/inventory/sale/'.$sale->id.'/destroy');

        $this->assertCount($count - 1, InventoryProductSale::all());
        $response->assertRedirect('/inventory/sale');

    }
}
