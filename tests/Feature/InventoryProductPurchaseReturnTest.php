<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\InventoryProductPurchaseReturn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class InventoryProductPurchaseReturnTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_inventory_product_purchase_return_can_be_created()
    {
        $this->withoutExceptionHandling();

        $user = user::factory()->create();
        $user->assignRole('admin');
 
        $response = $this->actingAs($user)
                         ->withSession(['banned' => false])
                         ->post('/inventory/purchase/return/store',[

                            'purchase_return_date' => '2022-01-16',
                            'inventory_warehouse_id' => '2',
                            'supplier_id' => '3',
                            'status' =>'ordered',
                         ]);

        $response->assertRedirect('inventory/purchase/return');

    }

    public function test_inventory_product_purchase_return_can_be_updated()
    {
        $this->withoutExceptionHandling();

        $user = user::factory()->create();
        $user->assignRole('admin');
 

        $purchaseReturn = InventoryProductPurchaseReturn::factory()->create([
                      
                            'purchase_return_date' => '2022-01-17',
                            'inventory_warehouse_id' => '3',
                            'supplier_id' => '4',
                            'status' =>'ordered',
                              
                      

        ]);

        $response = $this->actingAs($user)
                        ->withSession(['banned' => false])
                        ->put('/inventory/purchase/'.$purchaseReturn->id.'/return/update',[


                            'purchase_return_date' => '2022-01-18',
                            'inventory_warehouse_id' => '2',
                            'supplier_id' => '3',
                            'status' =>'received',
                        ]);

       $purchaseReturn->refresh();
       $response->assertRedirect('/inventory/purchase/return');
       $this->assertEquals('received', $purchaseReturn->status);

    }

    public function test_inventory_product_purchase_return_can_be_deleted()
    {
        $this->withoutExceptionHandling();

        $user = user::factory()->create();
        $user->assignRole('admin');
 

        $purchaseReturn = InventoryProductPurchaseReturn::factory()->create([
                      
                            'purchase_return_date' => '2022-01-17',
                            'inventory_warehouse_id' => '3',
                            'supplier_id' => '4',
                            'status' =>'ordered',
                              
                      

        ]);

        $count = InventoryProductPurchaseReturn::count();

        $response = $this->actingAs($user)
                        ->withSession(['banned' => false])
                        ->delete('/inventory/purchase/return/'.$purchaseReturn->id.'/destroy');

        $this->assertCount($count - 1, InventoryProductPurchaseReturn::all());
        $response->assertRedirect('/inventory/purchase/return');
    }
}
