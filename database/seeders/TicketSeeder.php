<?php

namespace Database\Seeders;

use App\Models\Ticket;
use App\Models\TicketUpdate;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Seeds Ticket #001 — the three platform-review enhancements (CAPTCHA, SSL,
 * landing-page redesign) recorded together, as agreed in the correspondence.
 *
 * Timeline: requested 06 Aug 2026, completed 07 Aug 2026.
 * Idempotent: it will not duplicate the ticket or its activity trail.
 *
 *   php artisan db:seed --class=TicketSeeder
 */
class TicketSeeder extends Seeder
{
    public function run(): void
    {
        $requestedAt = Carbon::parse('2026-08-06 09:00:00');
        $completedAt = Carbon::parse('2026-08-07 17:00:00');

        // Responsible person: first admin user if present (single-user installs),
        // otherwise left unassigned to be set in the UI.
        $owner = User::whereHas('roles', fn ($q) => $q->where('name', 'admin'))->first()
            ?? User::orderBy('id')->first();

        $description = <<<TXT
Platform-review enhancements raised by MAIIC (Barry), recorded together under a single reference for tracking:

1. Addition of CAPTCHA on the login page.
2. SSL implementation.
3. Review and enhancement of the landing page design, using the shared samples and logos where appropriate.

This ticket is updated as the work progresses so both MAIIC and Dupleix can follow the status, progress, responsible person and resolution.
TXT;

        $resolution = <<<TXT
All three items delivered and handed over on 07 Aug 2026:
• CAPTCHA — self-hosted, offline image CAPTCHA added to the login and verified through Fortify.
• Landing / login page — redesigned to the approved MAIIC brand direction (Sample 4) with the shared logos.
• SSL — application-level HTTPS hardening delivered (force-https, secure cookies, HSTS, security headers, redirect). Final step is installing the TLS certificate and enabling the Apache SSL vhost on the server — documented in docs/SSL_SETUP.md.
TXT;

        $ticket = Ticket::firstOrCreate(
            ['reference' => '001'],
            [
                'title' => 'Platform enhancements — CAPTCHA, SSL & landing page',
                'description' => $description,
                'category' => 'enhancement',
                'priority' => 'high',
                'status' => 'resolved',
                'requested_by' => 'Barry — MAIIC',
                'source' => 'email',
                'assigned_to' => $owner?->id,
                'created_by' => $owner?->id,
                'resolution' => $resolution,
            ]
        );

        if (! $ticket->wasRecentlyCreated) {
            $this->command?->info('Ticket #001 already exists — left unchanged.');
            return;
        }

        // Anchor the ticket's own timestamps to the real request/completion dates.
        $ticket->timestamps = false;
        $ticket->created_at = $requestedAt;
        $ticket->updated_at = $completedAt;
        $ticket->resolved_at = $completedAt;
        $ticket->save();
        $ticket->timestamps = true;

        $updates = [
            [$requestedAt->copy()->addMinutes(5),  true,  null, 'open',
                'Ticket logged from the platform-review correspondence with MAIIC.'],
            [$requestedAt->copy()->addMinutes(30), false, null, null,
                "Three items acknowledged and grouped under this reference:\n• CAPTCHA on login\n• SSL implementation\n• Landing-page design enhancement using the shared samples and logos."],
            [$requestedAt->copy()->addHour(),      true,  'open', 'in_progress',
                'Status changed from Open to In Progress.'],
            [$completedAt->copy()->subHours(5),    false, null, null,
                'Landing / login page redesigned to the approved MAIIC brand direction (Sample 4) and a self-hosted, offline CAPTCHA added to the sign-in form. Items 1 and 3 delivered in-app.'],
            [$completedAt->copy()->subHours(2),    false, null, null,
                'SSL — application-level HTTPS hardening delivered. Remaining: install the TLS certificate and enable the Apache SSL vhost on the server, then switch the flags on (docs/SSL_SETUP.md).'],
            [$completedAt,                          true,  'in_progress', 'resolved',
                'Marked resolved. Requested 06 Aug 2026, completed 07 Aug 2026.'],
            [$completedAt->copy()->addHours(19),    false, null, null,
                'Post-delivery review feedback applied: security-check (CAPTCHA) box '
                . 'enlarged with a bolder, higher-contrast code image for visibility, '
                . 'and the browser-tab icon replaced with the MAIIC emblem.'],
        ];

        foreach ($updates as [$at, $isSystem, $old, $new, $body]) {
            $u = new TicketUpdate([
                'ticket_id' => $ticket->id,
                'user_id' => $isSystem ? null : $owner?->id,
                'body' => $body,
                'old_status' => $old,
                'new_status' => $new,
                'is_system' => $isSystem,
            ]);
            $u->timestamps = false;
            $u->created_at = $at;
            $u->updated_at = $at;
            $u->save();
        }

        $this->command?->info('Ticket #001 seeded (requested 06 Aug, resolved 07 Aug) with its activity trail.');
    }
}
