<?php

namespace Database\Seeders;

use App\Models\Ticket;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Seeds Ticket #001 — the three platform-review enhancements (CAPTCHA, SSL,
 * landing-page redesign) recorded together, as agreed in the correspondence.
 * Idempotent: it will not duplicate the ticket or its activity trail.
 *
 *   php artisan db:seed --class=TicketSeeder
 */
class TicketSeeder extends Seeder
{
    public function run(): void
    {
        $description = <<<TXT
Platform-review enhancements raised by MAIIC (Barry), recorded together under a single reference for tracking:

1. Addition of CAPTCHA on the login page.
2. SSL implementation.
3. Review and enhancement of the landing page design, using the shared samples and logos where appropriate.

This ticket is updated as the work progresses so both MAIIC and Dupleix can follow the status, progress, responsible person and resolution.
TXT;

        $ticket = Ticket::firstOrCreate(
            ['reference' => '001'],
            [
                'title' => 'Platform enhancements — CAPTCHA, SSL & landing page',
                'description' => $description,
                'category' => 'enhancement',
                'priority' => 'high',
                'status' => 'in_progress',
                'requested_by' => 'Barry — MAIIC',
                'source' => 'email',
            ]
        );

        // Only build the activity trail on first creation.
        if (! $ticket->wasRecentlyCreated) {
            $this->command?->info('Ticket #001 already exists — left unchanged.');
            return;
        }

        $base = Carbon::now();

        $updates = [
            [
                'is_system' => true,
                'new_status' => 'open',
                'body' => 'Ticket logged from the platform-review correspondence with MAIIC.',
                'at' => $base->copy()->subMinutes(30),
            ],
            [
                'is_system' => false,
                'body' => "Three items acknowledged and grouped under this reference:\n"
                    . "• CAPTCHA on login\n• SSL implementation\n• Landing-page design enhancement using the shared samples and logos.",
                'at' => $base->copy()->subMinutes(25),
            ],
            [
                'is_system' => true,
                'old_status' => 'open',
                'new_status' => 'in_progress',
                'body' => 'Status changed from Open to In Progress.',
                'at' => $base->copy()->subMinutes(20),
            ],
            [
                'is_system' => false,
                'body' => 'Landing / login page redesigned to the approved MAIIC brand direction '
                    . '(clean light layout, brand palette and logos) and a self-hosted, offline '
                    . 'CAPTCHA added to the sign-in form. Items 1 and 3 delivered in-app.',
                'at' => $base->copy()->subMinutes(15),
            ],
            [
                'is_system' => false,
                'body' => 'SSL — application-level HTTPS hardening delivered (force-https, secure '
                    . 'session cookies, HSTS, baseline security headers, and a ready http→https '
                    . 'redirect). Remaining: install the TLS certificate and enable the Apache SSL '
                    . 'vhost on the server, then switch the flags on — steps documented in '
                    . 'docs/SSL_SETUP.md. Item 2 pending server-side certificate.',
                'at' => $base->copy()->subMinutes(10),
            ],
        ];

        foreach ($updates as $u) {
            $ticket->updates()->create([
                'user_id' => null,
                'body' => $u['body'],
                'old_status' => $u['old_status'] ?? null,
                'new_status' => $u['new_status'] ?? null,
                'is_system' => $u['is_system'],
                'created_at' => $u['at'],
                'updated_at' => $u['at'],
            ]);
        }

        $this->command?->info('Ticket #001 seeded with its activity trail.');
    }
}
