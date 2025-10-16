<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\InventoryProductVariationType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class InventoryProductVariationTypeTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_inventory_product_variation_type_can_be_created()
    {
        $this->withoutExceptionHandling();

        $user = user::factory()->create();
        $user->assignRole('admin');
 
        $response = $this->actingAs($user)
                         ->withSession(['banned' => false])
                         ->post('/inventory/variation/store',[

                            'name' => 'hhgh',
                            
                         ]);  

        $response->assertRedirect('inventory/variation');
    }

    public function test_inventory_product_variation_type_can_be_updated()
    {
        $this->withoutExceptionHandling();

        $user = user::factory()->create();
        $user->assignRole('admin');

        $variationType = InventoryProductVariationType::factory()->create([

                            'name' => 'www',
                            
        ]);


        $response = $this->actingAs($user)
                        ->withSession(['banned' => false])
                        ->put('/inventory/variation/'.$variationType->id.'/update',[

                            'name' => 'uuu',
                            
                        ]);

       $variationType->refresh();
       $response->assertRedirect('/inventory/variation');
       $this->assertEquals('uuu', $variationType->name);
    }

    public function test_inventory_product_variation_type_can_be_deleted()
    {
        $this->withoutExceptionHandling();

        $user = user::factory()->create();
        $user->assignRole('admin');

        $variationType = InventoryProductVariationType::factory()->create([

                            'name' => 'sds',
                            
        ]);

        $count = InventoryProductVariationType::count();

        $response = $this->actingAs($user)
                        ->withSession(['banned' => false])
                        ->delete('/inventory/variation/'.$variationType->id.'/destroy');


        $this->assertCount($count - 1, InventoryProductVariationType::all());
        $response->assertRedirect('/inventory/variation');
    }
}


