<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\InventorySupplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class InventorySupplierTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_inventory_supplier_can_be_created()
    {
        $this->withoutExceptionHandling();

        $user = user::factory()->create();
        $user->assignRole('admin');
 
        $response = $this->actingAs($user)
                         ->withSession(['banned' => false])
                         ->post('/inventory/supplier/store',[

                            'name' => 'hhgh',
                            
                         ]);  

        $response->assertRedirect('inventory/supplier');
    }

    public function test_inventory_supplier_can_be_updated()
    {
        $this->withoutExceptionHandling();

        $user = user::factory()->create();
        $user->assignRole('admin');

        $supplier = InventorySupplier::factory()->create([

                            'name' => 'www',
                            
        ]);


        $response = $this->actingAs($user)
                        ->withSession(['banned' => false])
                        ->put('/inventory/supplier/'.$supplier->id.'/update',[

                            'name' => 'uuu',
                            
                        ]);

       $supplier->refresh();
       $response->assertRedirect('/inventory/supplier');
       $this->assertEquals('uuu', $supplier->name);
    }

    public function test_inventory_supplier_can_be_deleted()
    {
        $this->withoutExceptionHandling();

        $user = user::factory()->create();
        $user->assignRole('admin');

        $supplier = InventorySupplier::factory()->create([

                            'name' => 'sds',
                            
        ]);

        $count = InventorySupplier::count();

        $response = $this->actingAs($user)
                        ->withSession(['banned' => false])
                        ->delete('/inventory/supplier/'.$supplier->id.'/destroy');


        $this->assertCount($count - 1, InventorySupplier::all());
        $response->assertRedirect('/inventory/supplier');
    }
}
