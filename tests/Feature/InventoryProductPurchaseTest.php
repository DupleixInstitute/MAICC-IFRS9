<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\InventoryProductPurchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class InventoryProductPurchaseTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_inventory_product_purchase_can_be_created()
    {
        $this->withoutExceptionHandling();

        $user = user::factory()->create();
        $user->assignRole('admin');
 
        $response = $this->actingAs($user)
                         ->withSession(['banned' => false])
                         ->post('inventory/purchase/store',[

                            'purchase_date' => '2022-01-15',
                            'purchase_time' =>  '083000' ,
                            'inventory_warehouse_id' => '1',
                            'status' =>'ordered',
                         ]);

        $response->assertRedirect('inventory/purchase');
    
    }

    public function test_inventory_product_purchase_can_be_updated()
    {
        $this->withoutExceptionHandling();

        $user = user::factory()->create();
        $user->assignRole('admin');
 

        $purchase = InventoryProductPurchase::factory()->create([

            'purchase_date' => '2022-01-15',
            'purchase_time' => '083000' ,
            'inventory_warehouse_id' => '1',
            'status' =>'ordered',        
                       

        ]);

        $response = $this->actingAs($user)
                        ->withSession(['banned' => false])
                        ->put('/inventory/purchase/'.$purchase->id.'/update',[

                            'purchase_date' => '2022-01-19',
                            'purchase_time' =>  '083000' ,
                            'inventory_warehouse_id' => '1',
                            'status' =>'ordered',
                            
                        ]);

       $purchase->refresh();
       $response->assertRedirect('/inventory/purchase');
       $this->assertEquals('2022-01-19', $purchase->purchase_date);

    }

    public function test_inventory_product_purchase_can_be_deleted()
    {
        $this->withoutExceptionHandling();

        $user = user::factory()->create();
        $user->assignRole('admin');
 

        $purchase = InventoryProductPurchase::factory()->create([

            'purchase_date' => '2022-01-15',
            'purchase_time' =>  '083000' ,
            'inventory_warehouse_id' => '1',
            'status' =>'ordered',        
                       

        ]);

        $count = InventoryProductPurchase::count();

        $response = $this->actingAs($user)
                        ->withSession(['banned' => false])
                        ->delete('/inventory/purchase/'.$purchase->id.'/destroy');


        $this->assertCount($count - 1, InventoryProductPurchase::all());
        $response->assertRedirect('/inventory/purchase');
        
    }
}
