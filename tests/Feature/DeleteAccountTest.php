<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Features;
use Tests\TestCase;

/**
 * No one deletes an account (16 October 2026): approvals, the decisions pack
 * and the append-only audit log name the people who decided, so every person
 * who used the system stays on record; a user who leaves is made inactive.
 */
class DeleteAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_deletion_is_not_offered_and_the_account_stays(): void
    {
        $this->assertFalse(Features::hasAccountDeletionFeatures());
        $this->actingAs($user = User::factory()->create());

        $this->delete('/user', ['password' => 'password']);

        $this->assertNotNull($user->fresh());
    }
}
