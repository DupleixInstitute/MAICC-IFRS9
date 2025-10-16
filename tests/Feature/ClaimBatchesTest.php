<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ClaimBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ClaimBatchesTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_batches_claim_can_be_created()
    {

        $user = user::factory()->create();
        $user->assignRole('admin');

        $response = $this->actingAs($user)
                         ->withSession(['banned'=>false])
                         ->post('/billing/claim_batch/store',[
                            'co_payer_id' => '1',
                            'date' => '2022-01-15',
        ]);    

        $response->assertRedirect('/billing/claim_batch');

    }

    // public function test_batches_claim_can_be_sent()
    // {
    //     $response = $this->get('/billing/claim_batches/send');
    // }

    public function test_batches_claim_can_be_updated()
    {

        $user = user::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)
             ->withSession(['banned'=>false])
            ->post('/billing/claim_batch/store',[
                    'co_payer_id' => '1',
                    'date' => '2022-01-15',
        ]); 
        
        $claim_batch = ClaimBatch::first();

        $response=  $this->actingAs($user)
                         ->withSession(['banned'=>false])
                         ->put('/billing/claim_batch/'.$claim_batch->id.'/update',[
                            'co_payer_id' => '1',
                            'date' => '2022-01-20',
        ]);  

        $this->assertEquals('2022-01-20',ClaimBatch::first()->date);
        $response->assertRedirect('/billing/claim_batch');


    }

    public function test_batches_claim_can_be_deleted()
    {

        $user = user::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)
             ->withSession(['banned'=>false])
             ->post('/billing/claim_batch/store',[
                     'co_payer_id' => '1',
                     'date' => '2022-01-15',
        ]); 
        
        $claim_batch = ClaimBatch::first();
        $count = ClaimBatch::count();


        $response=  $this->actingAs($user)
                         ->withSession(['banned'=>false])
                         ->delete('/billing/claim_batch/'.$claim_batch->id.'/destroy');

        $this->assertCount($count-1, ClaimBatch::all());
         $response->assertRedirect('/billing/claim_batch');
    }
}
