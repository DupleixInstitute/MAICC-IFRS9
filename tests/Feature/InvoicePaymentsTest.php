<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class InvoicePaymentsTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_invoice_payment_can_be_created()
    {

        $user= user::factory()->create();
        $user->assignRole('admin');

        $invoice= invoice::factory()->create();
        $invoice= Invoice::first();

        $response= $this->actingAs($user)
                        ->withSession(['banned'=>false])
                        ->post('/billing/'.$invoice->id.'/payment/store',[
                            'date' => '2022-02-24',
                            'paid_by' => 'co_payer',
                            'amount' => '20.00',
                            'payment_type_id' => '2',
                            'co_payer_id' =>'1',
                        ]);


        $response->assertRedirect('/billing/'.$invoice->id.'/show');
    }

    public function test_invoice_payment_can_be_updated()
    {

        $user= user::factory()->create();
        $user->assignRole('admin');

        $invoice= invoice::factory()->create();
        $invoice= Invoice::first();

        $this->actingAs($user)
             ->withSession(['banned'=>false])
             ->post('/billing/'.$invoice->id.'/payment/store',[
                            'date' => '2022-02-24',
                            'paid_by' => 'co_payer',
                            'amount' => '20.00',
                            'payment_type_id' => '2',
                            'co_payer_id' =>'1',
                        ]);

        $invoice_payment= InvoicePayment::first();

        $response = $this->actingAs($user)
                         ->withSession(['banned'=>false])
                         ->put('/billing/payment/'.$invoice_payment->id.'/update',[
                            'date' => '2022-03-24',
                            'paid_by' => 'co_payer',
                            'amount' => '30.00',
                            'payment_type_id' => '2',
                            'co_payer_id' =>'1',
                         ]);

        $this->assertEquals('2022-03-24',InvoicePayment::first()->date);
        $this->assertEquals('30.00',InvoicePayment::first()->amount);

        $response->assertRedirect('/billing/'.$invoice->id.'/show');



    }

    public function test_invoice_payment_can_be_deleted()
    {

        $user= user::factory()->create();
        $user->assignRole('admin');

        $invoice= invoice::factory()->create();
        $invoice= Invoice::first();

        $this->actingAs($user)
             ->withSession(['banned'=>false])
             ->post('/billing/'.$invoice->id.'/payment/store',[
                            'date' => '2022-02-24',
                            'paid_by' => 'co_payer',
                            'amount' => '20.00',
                            'payment_type_id' => '2',
                            'co_payer_id' =>'1',
                        ]);

        $invoice_payment= InvoicePayment::first();
        $count = InvoicePayment::count();

        $response = $this->actingAs($user)
                         ->withSession(['banned'=>false])
                         ->delete('/billing/payment/'.$invoice_payment->id.'/destroy');

        $this->assertCount($count-1, InvoicePayment::all());
        $response->assertRedirect('/billing/'.$invoice->id.'/show');


    }
}
