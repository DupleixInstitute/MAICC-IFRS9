<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\InventoryProductBrand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class InventoryProductBrandTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_inventory_product_brand_can_be_created()
    {
        $this->withoutExceptionHandling();

        $user = user::factory()->create();
        $user->assignRole('admin');
 
        $response = $this->actingAs($user)
                         ->withSession(['banned' => false])
                         ->post('inventory/brand/store',[

                            'name' =>'painkiller',
                         ]);

        $response->assertRedirect('inventory/brand');
    }

    public function test_inventory_product_brand_can_be_updated()
    {
        $this->withoutExceptionHandling();

        $user = user::factory()->create();
        $user->assignRole('admin');
 

        $brand = InventoryProductBrand::factory()->create([

                              
                       'name' =>'paineaze',

        ]);

        $response = $this->actingAs($user)
                        ->withSession(['banned' => false])
                        ->put('/inventory/brand/'.$brand->id.'/update',[


                            'name'=>'brufen',
                        ]);

       $brand->refresh();
       $response->assertRedirect('/inventory/brand');
       $this->assertEquals('brufen', $brand->name);

    }

    public function test_inventory_product_brand_can_be_deleted()
    {
        $this->withoutExceptionHandling();

        $user = user::factory()->create();
        $user->assignRole('admin');
 

        $brand = InventoryProductBrand::factory()->create([

                              
                       'name' =>'paineaze',

        ]);

        $count = InventoryProductBrand::count();

        $response = $this->actingAs($user)
                        ->withSession(['banned' => false])
                        ->delete('/inventory/brand/'.$brand->id.'/destroy');

        $this->assertCount($count - 1, InventoryProductBrand::all());
        $response->assertRedirect('/inventory/brand');
    }
}
