<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\InventoryProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class InventoryProductCategoryTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_inventory_product_category_can_be_created()
    {
        $this->withoutExceptionHandling();

        $user = user::factory()->create();
        $user->assignRole('admin');
 
        $response = $this->actingAs($user)
                         ->withSession(['banned' => false])
                         ->post('/inventory/category/store',[

                            'name' =>'tablets',
                         ]);

       $response->assertRedirect('inventory/category');
    }

    public function test_inventory_product_category_can_be_updated()
    {
        $this->withoutExceptionHandling();

        $user = user::factory()->create();
        $user->assignRole('admin');
 

        $category = InventoryProductCategory::factory()->create([

                              
                       'name' =>'fluids',

        ]);

        $response = $this->actingAs($user)
                        ->withSession(['banned' => false])
                        ->put('/inventory/category/'.$category->id.'/update',[


                            'name'=>'tablets',
                        ]);

       $category->refresh();
       $response->assertRedirect('/inventory/category');
       $this->assertEquals('tablets', $category->name);

    }

    public function test_inventory_product_category_can_be_deleted()
    {
        $this->withoutExceptionHandling();

        $user = user::factory()->create();
        $user->assignRole('admin');
 

        $category = InventoryProductCategory::factory()->create([

                              
                       'name' =>'fluids',

        ]);

        $count = InventoryProductCategory::count();

        $response = $this->actingAs($user)
                        ->withSession(['banned' => false])
                        ->delete('/inventory/category/'.$category->id.'/destroy');

        $this->assertCount($count - 1, InventoryProductCategory::all());
        $response->assertRedirect('/inventory/category');
    }
}
