<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\InventoryProductSale;
use App\Models\InventoryProductSalePayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class InventoryProductSalePaymentTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_inventory_product_sale_payment_can_be_created()
    {
        $this->withoutExceptionHandling();

        $user = user::factory()->create();
        $user->assignRole('admin');

        $sale = InventoryProductSale::factory()->create();

        $response = $this->actingAs($user)
                         ->withSession(['banned' => false])
                         ->post('/inventory/sale/'.$sale->id.'/payment/store',[

                              'payment_type_id' => '2',
                              'amount' => '20.00',
                              'date' => '2022-01-17',

                         ]);

        $response->assertRedirect('/inventory/sale/' .$sale->id. '/payment');
    }

    public function test_inventory_product_sale_payment_can_be_updated()
    {
        $this->withoutExceptionHandling();

        $user = user::factory()->create();
        $user->assignRole('admin');
        
        $payment = InventoryProductSalePayment::factory()->create([

                              'payment_type_id' => '2',
                              'amount' => '20.00',
                              'date' => '2022-01-17',


        ]);

        $response = $this->actingAs($user)
                        ->withSession(['banned' => false])
                        ->put('/inventory/sale/payment/'.$payment->id.'/update',[

                              'payment_type_id' => '2',
                              'amount' => '30.00',
                              'date' => '2022-01-17',
                        ]);

        $payment->refresh();
        $response->assertRedirect('/inventory/sale/' .$payment->id. '/payment');
        $this->assertEquals('30.00', $payment->amount);
    }

    public function test_inventory_product_sale_payment_can_be_deleted()
    {
        $this->withoutExceptionHandling();

        $user = user::factory()->create();
        $user->assignRole('admin');
        
        $payment = InventoryProductSalePayment::factory()->create([

                              'payment_type_id' => '2',
                              'amount' => '20.00',
                              'date' => '2022-01-17',


        ]);

        $count = InventoryProductSalePayment::count();

        $response = $this->actingAs($user)
                        ->withSession(['banned' => false])
                        ->delete('/inventory/sale/payment/'.$payment->id.'/destroy');

              
        $this->assertCount($count - 1, InventoryProductSalePayment::all());
        $response->assertRedirect('/inventory/sale/' .$payment->id. '/payment');
    }
}
