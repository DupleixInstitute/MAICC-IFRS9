<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Support / change-request ticketing module (introduced with Ticket #001).
 */
class TicketsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->syncRoles(['admin']); // admin holds all tickets.* permissions

        return $user;
    }

    public function test_guests_are_redirected_to_login()
    {
        $this->get('/tickets')->assertRedirect('/login');
    }

    public function test_user_without_permission_is_forbidden()
    {
        $user = User::factory()->create();
        $user->syncRoles(['client']); // external role — no tickets access

        $this->actingAs($user)->get('/tickets')->assertStatus(403);
    }

    public function test_admin_can_view_ticket_index()
    {
        $this->actingAs($this->admin())->get('/tickets')->assertStatus(200);
    }

    public function test_ticket_references_start_at_001_and_increment()
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/tickets/store', [
            'title' => 'First ticket',
            'category' => 'enhancement',
            'priority' => 'high',
            'status' => 'open',
        ]);
        $this->actingAs($admin)->post('/tickets/store', [
            'title' => 'Second ticket',
            'category' => 'issue',
            'priority' => 'medium',
            'status' => 'open',
        ]);

        $this->assertSame(
            ['001', '002'],
            Ticket::orderBy('id')->pluck('reference')->all()
        );
        $this->assertSame('#001', Ticket::where('reference', '001')->first()->reference_display);
    }

    public function test_creating_a_ticket_logs_a_system_update()
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/tickets/store', [
            'title' => 'Logged with trail',
            'category' => 'enhancement',
            'priority' => 'medium',
            'status' => 'open',
        ]);

        $ticket = Ticket::firstOrFail();
        $this->assertSame(1, $ticket->updates()->count());
        $this->assertTrue((bool) $ticket->updates()->first()->is_system);
        $this->assertSame($admin->id, $ticket->created_by);
    }

    public function test_status_change_is_recorded_in_the_activity_trail()
    {
        $admin = $this->admin();
        $ticket = Ticket::create([
            'reference' => Ticket::nextReference(),
            'title' => 'Status flow',
            'category' => 'enhancement',
            'priority' => 'medium',
            'status' => 'open',
        ]);

        $this->actingAs($admin)->post("/tickets/{$ticket->id}/updates", [
            'body' => 'Work started.',
            'new_status' => 'in_progress',
        ]);

        $ticket->refresh();
        $this->assertSame('in_progress', $ticket->status);
        $last = $ticket->updates()->latest('id')->first();
        $this->assertSame('open', $last->old_status);
        $this->assertSame('in_progress', $last->new_status);
        $this->assertSame('Work started.', $last->body);
    }

    public function test_resolving_a_ticket_sets_resolved_at()
    {
        $admin = $this->admin();
        $ticket = Ticket::create([
            'reference' => Ticket::nextReference(),
            'title' => 'To resolve',
            'category' => 'enhancement',
            'priority' => 'medium',
            'status' => 'in_progress',
        ]);

        $this->actingAs($admin)->post("/tickets/{$ticket->id}/updates", [
            'body' => 'Done and verified.',
            'new_status' => 'resolved',
        ]);

        $ticket->refresh();
        $this->assertSame('resolved', $ticket->status);
        $this->assertNotNull($ticket->resolved_at);
    }

    public function test_admin_can_update_ticket_details()
    {
        $admin = $this->admin();
        $ticket = Ticket::create([
            'reference' => Ticket::nextReference(),
            'title' => 'Old title',
            'category' => 'enhancement',
            'priority' => 'low',
            'status' => 'open',
        ]);

        $this->actingAs($admin)->put("/tickets/{$ticket->id}/update", [
            'title' => 'New title',
            'category' => 'change_request',
            'priority' => 'critical',
            'status' => 'open',
            'requested_by' => 'Barry — MAIIC',
            'assigned_to' => $admin->id,
        ]);

        $ticket->refresh();
        $this->assertSame('New title', $ticket->title);
        $this->assertSame('change_request', $ticket->category);
        $this->assertSame('critical', $ticket->priority);
        $this->assertSame($admin->id, $ticket->assigned_to);
    }

    public function test_admin_can_delete_a_ticket()
    {
        $admin = $this->admin();
        $ticket = Ticket::create([
            'reference' => Ticket::nextReference(),
            'title' => 'Disposable',
            'category' => 'other',
            'priority' => 'low',
            'status' => 'open',
        ]);

        $this->actingAs($admin)->delete("/tickets/{$ticket->id}/destroy");

        $this->assertDatabaseMissing('tickets', ['id' => $ticket->id]);
    }

    public function test_ticket_seeder_creates_ticket_001_resolved_with_trail()
    {
        $this->seed(\Database\Seeders\TicketSeeder::class);

        $ticket = Ticket::where('reference', '001')->firstOrFail();

        $this->assertSame('resolved', $ticket->status);
        $this->assertSame('Barry — MAIIC', $ticket->requested_by);
        // Requested 06 Aug 2026, completed 07 Aug 2026.
        $this->assertSame('2026-08-06', $ticket->created_at->toDateString());
        $this->assertSame('2026-08-07', $ticket->resolved_at->toDateString());
        $this->assertNotNull($ticket->resolution);
        $this->assertGreaterThanOrEqual(6, $ticket->updates()->count());

        // Idempotent: reseeding must not duplicate.
        $this->seed(\Database\Seeders\TicketSeeder::class);
        $this->assertSame(1, Ticket::where('reference', '001')->count());
    }
}
