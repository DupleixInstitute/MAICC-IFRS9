<?php

namespace Tests\Feature;

use App\Models\Vital;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class VitalsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_vitals_page_can_be_rendered()
    {
        $response = $this->get('/vital');

        $response->assertStatus(200);
    }

    public function test_vital_can_be_created()
    {
        $response = $this->post('/vital/store', [
            'name' => 'Test',
        ]);

        $response->assertRedirect('/vital');
    }

    public function test_vital_can_be_updated()
    {
        $vital = Vital::factory()->create([
            'name' => 'Test',
        ]);
        $response = $this->put('/vital/' . $vital->id . '/update', [
            'name' => 'Vital',
        ]);

        $this->assertEquals('Vital', $vital->fresh()->name);
    }

    public function test_vital_can_be_deleted()
    {
        $vital = Vital::factory()->create();
        $count = Vital::count();
        $response = $this->delete('/vital/' . $vital->id . '/destroy');
        $this->assertDatabaseCount('vitals', $count - 1);
    }
}
