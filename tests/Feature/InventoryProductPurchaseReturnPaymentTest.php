<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\InventoryProductPurchaseReturn;
use App\Models\InventoryProductPurchaseReturnPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class InventoryProductPurchaseReturnPaymentTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_inventory_product_purchase_return_payment_can_be_created()
    {
        $this->withoutExceptionHandling();

        $user = user::factory()->create();
        $user->assignRole('admin');

        $purchaseReturn = InventoryProductPurchaseReturn::factory()->create();

        $response = $this->actingAs($user)
                         ->withSession(['banned' => false])
                         ->post('/inventory/purchase/return/'.$purchaseReturn->id.'/payment/store',[

                                  'payment_type_id' =>'3',
                                  'amount' => '20.00',
                                  'date' => '2022-01-16',
                         ]);
        $response->assertRedirect('/');
    }

    public function test_inventory_product_purchase_return_payment_can_be_updated()
    {
        $this->withoutExceptionHandling();

        $user = user::factory()->create();
        $user->assignRole('admin');
 

        $payment = InventoryProductPurchaseReturnPayment::factory()->create([

                            'payment_type_id' =>'3',
                            'amount' => '20.00',
                            'date' => '2022-01-16',
        ]);

        $response = $this->actingAs($user)
                        ->withSession(['banned' => false])
                        ->put('/inventory/purchase/return/payment/'.$payment->id.'/update',[

                                'payment_type_id' =>'4',
                                'amount' => '40.00',
                                'date' => '2022-01-16',
                        ]);


        $payment->refresh();
        $response->assertRedirect('/');
        $this->assertEquals('40.00', $payment->amount);
                 
    }

    public function test_inventory_product_purchase_return_payment_can_be_deleted()
    {

        $this->withoutExceptionHandling();

        $user = user::factory()->create();
        $user->assignRole('admin');
 

        $payment = InventoryProductPurchaseReturnPayment::factory()->create([

                            'payment_type_id' =>'3',
                            'amount' => '20.00',
                            'date' => '2022-01-16',
        ]);

        $count = InventoryProductPurchaseReturnPayment::count();

        $response = $this->actingAs($user)
                        ->withSession(['banned' => false])
                        ->delete('/inventory/purchase/return/payment/'.$payment->id.'/destroy');

        $this->assertCount($count - 1, InventoryProductPurchaseReturnPayment::all());
        $response->assertRedirect('/');

    }
}
