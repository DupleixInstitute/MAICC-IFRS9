<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\InventoryProductPurchase;
use App\Models\InventoryProductPurchasePayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class InventoryProductPurchasePaymentTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_inventory_product_purchase_payment_can_be_created()
    {
        $this->withoutExceptionHandling();

        $user = user::factory()->create();
        $user->assignRole('admin');

        $purchase = InventoryProductPurchase::factory()->create();
 
        $response = $this->actingAs($user)
                         ->withSession(['banned' => false])
                         ->post('inventory/purchase/'.$purchase->id.'/payment/store',[

                            'payment_type_id' => '2',
                            'amount' => '20.00',
                            'date' => '2022-01-15',
                         ]);

        $response->assertRedirect('/');
    
    }

    public function test_inventory_product_purchase_payment_can_be_updated()
    {
        
            $this->withoutExceptionHandling();
    
            $user = user::factory()->create();
            $user->assignRole('admin');
     
    
            $payment = InventoryProductPurchasePayment::factory()->create([
    
                          'payment_type_id' => '2',
                          'amount' => '20.00',
                         'date' => '2022-01-15',
                           
    
            ]);
    
            $response = $this->actingAs($user)
                            ->withSession(['banned' => false])
                            ->put('/inventory/purchase/payment/'.payment->id.'/update',[
    
                                'payment_type_id' => '2',
                                'amount' => '40.00',
                                'date' => '2022-01-15',
                                
                            ]);
    
           $payment->refresh();
           $response->assertRedirect('/');
           $this->assertEquals('40.00', $payment->amount);
    
        }
    
        public function test_inventory_product_purchase_payment_can_be_deleted()
        {
            $this->withoutExceptionHandling();
    
            $user = user::factory()->create();
            $user->assignRole('admin');
     
    
            $payment = InventoryProductPurchasePayment::factory()->create([
    
                'payment_type_id' => '2',
                'amount' => '20.00',
                'date' => '2022-01-15',
                  
                           
    
            ]);
    
            $count = InventoryProductPurchasePayment::count();
    
            $response = $this->actingAs($user)
                            ->withSession(['banned' => false])
                            ->delete('/inventory/purchase/payment/'.$payment->id.'/destroy');
    
    
            $this->assertCount($count - 1, InventoryProductPurchasePayment::all());
            $response->assertRedirect('/');
            
        }
    
}

