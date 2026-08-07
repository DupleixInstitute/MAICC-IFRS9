<?php

namespace Database\Seeders;

use App\Models\Ticket;
use App\Models\TicketUpdate;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Seeds Ticket #001 - the three platform-review enhancements (CAPTCHA, SSL,
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
• CAPTCHA: self-hosted, offline image CAPTCHA added to the login and verified through Fortify.
• Landing / login page: redesigned to the approved MAIIC brand direction (Sample 4) with the shared logos.
• SSL: application-level HTTPS hardening delivered (force-https, secure cookies, HSTS, security headers, redirect). Final step is installing the TLS certificate and enabling the Apache SSL vhost on the server - documented in docs/SSL_SETUP.md.
TXT;

        $ticket = Ticket::firstOrCreate(
            ['reference' => '001'],
            [
                'title' => 'Platform enhancements: CAPTCHA, SSL and landing page',
                'description' => $description,
                'category' => 'enhancement',
                'priority' => 'high',
                'status' => 'resolved',
                'requested_by' => 'Barry (MAIIC)',
                'source' => 'email',
                'assigned_to' => $owner?->id,
                'created_by' => $owner?->id,
                'resolution' => $resolution,
            ]
        );

        if (! $ticket->wasRecentlyCreated) {
            $this->command?->info('Ticket #001 already exists - left unchanged.');
            $this->seedTicket002($owner);
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
                'SSL: application-level HTTPS hardening delivered. Remaining: install the TLS certificate and enable the Apache SSL vhost on the server, then switch the flags on (docs/SSL_SETUP.md).'],
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

        $this->seedTicket002($owner);
    }

    /**
     * Ticket #002 - navigation restructure, contract-aligned reports, audit
     * trail page and dashboard global filters (raised 07 Aug 2026).
     */
    private function seedTicket002(?User $owner): void
    {
        $requestedAt = Carbon::parse('2026-08-07 12:00:00');

        $description = <<<TXT
Platform review of the navigation pane and dashboard, raised during implementation:

1. Remove duplicated navigation entries and regroup the menu by function, aligned to the contract Schedule 1 solution components.
2. Review the reports set against the contracted report families; surface missing pages and remove duplicates.
3. Add an Audit Trail screen (contract component with no UI previously).
4. Dashboard: global filters (reporting period, portfolio, compare-to period) driving all KPIs and charts, all values database-driven.
5. Restyle the navigation pane to the MAIIC brand direction.
TXT;

        $ticket = Ticket::firstOrCreate(
            ['reference' => '002'],
            [
                'title' => 'Navigation restructure, audit trail & dashboard filters',
                'description' => $description,
                'category' => 'enhancement',
                'priority' => 'high',
                'status' => 'resolved',
                'requested_by' => 'MAIIC platform review',
                'source' => 'meeting',
                'assigned_to' => $owner?->id,
                'created_by' => $owner?->id,
                'resolution' => 'Menu regrouped to contract modules with duplicates removed (EWS/AI commentary live '
                    . 'as report-hub tiles; EIR pipeline unified; reports/exports moved under Reports; ECL '
                    . 'Reconciliation and Collateral Register surfaced; Financial Periods and Audit Trail added '
                    . 'under Administration). New unified Audit Trail page over both audit stores. Dashboard '
                    . 'gained period / portfolio / compare-to filters (all database-driven), a working period '
                    . 'selector, organisation-currency labels and an auto-scaled coverage trend. Sidebar '
                    . 'restyled to the MAIIC brand. Unauthenticated maintenance routes locked down.',
            ]
        );

        if (! $ticket->wasRecentlyCreated) {
            $this->command?->info('Ticket #002 already exists - left unchanged.');
            $this->seedBacklogTickets($owner);
            return;
        }

        $completedAt = $requestedAt->copy()->addHours(6);

        $ticket->timestamps = false;
        $ticket->created_at = $requestedAt;
        $ticket->updated_at = $completedAt;
        $ticket->resolved_at = $completedAt;
        $ticket->save();
        $ticket->timestamps = true;

        $trail = [
            [$requestedAt, true, null, 'open', 'Ticket logged from the navigation & dashboard platform review.'],
            [$requestedAt->copy()->addMinutes(30), true, 'open', 'in_progress', 'Status changed from Open to In Progress.'],
            [$completedAt->copy()->subHours(2), false, null, null,
                'Full page-by-page navigation audit completed (all 40 menu items + 30-report hub catalogue mapped against contract Schedule 1).'],
            [$completedAt, true, 'in_progress', 'resolved', 'Marked resolved. Delivered same day (07 Aug 2026).'],
        ];

        foreach ($trail as [$at, $isSystem, $old, $new, $body]) {
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

        $this->command?->info('Ticket #002 seeded with its activity trail.');

        $this->seedBacklogTickets($owner);
    }

    /**
     * Open backlog raised in the 07 Aug 2026 platform review: #003 to #006.
     * Each is created once with a single logged entry; work is tracked in
     * the UI from there.
     */
    private function seedBacklogTickets(?User $owner): void
    {
        $raisedAt = Carbon::parse('2026-08-07 14:00:00');

        $backlog = [
            [
                'reference' => '003',
                'title' => 'Consolidate duplicate stress and scenario engines',
                'priority' => 'medium',
                'description' => "Engine-level duplicates flagged by the navigation audit, needing consolidation without changing approved calculation results:\n\n"
                    . "1. Two stress engines: the standalone Stress Testing page (loan-level PD multipliers / LGD add-ons, scenario save) and the report-hub Sensitivity tile (aggregate shocks plus a macro/regression mode). Agree the canonical engine, port the missing mode across, retire the duplicate.\n"
                    . "2. Two scenario systems: Scenario Profiles (FLI) and Economic Scenarios (Management Overlays) are parallel implementations of scenario weighting. Agree the canonical store, migrate data, rewire dependents.\n\n"
                    . 'Requires side-by-side reconciliation of results before any switch-over.',
            ],
            [
                'reference' => '004',
                'title' => 'App-wide UI standardisation: tables, forms, modals, tabs',
                'priority' => 'high',
                'description' => "Standardise every page's tables, forms, modals and tabs to one MAIIC design system (reference: Eswatini Credit Scoring's esw/esf component CSS, recoloured to MAIIC green/gold):\n\n"
                    . "1. Extract a shared design-system CSS layer (tables with brand header band, zebra rows, hover, numeric alignment, gold-ruled totals rows; icon action buttons; filter bars; badges).\n"
                    . "2. Forms: section headings, consistent inputs/selects/validation, gold primary submit.\n"
                    . "3. Modals and tabs to the shared style.\n"
                    . '4. One colour system: MAIIC green, gold, red, grey only. Reference spec: docs/UI_REPORT_SPEC.md.',
            ],
            [
                'reference' => '005',
                'title' => 'PDF and Excel report formatting overhaul',
                'priority' => 'high',
                'description' => "All PDF and Excel outputs formatted to board-pack standard (reference: ZNBS Stress-Testing-App AuditWorkbook/MpdfRenderer and Eswatini CreditWorkbookBuilder, recoloured to MAIIC):\n\n"
                    . "1. Excel: branded cover sheet with logo, hyperlinked contents, KPI summary with live formulas, data as native Excel tables with freeze panes, accounting number formats, RAG conditional formatting, section tab colours.\n"
                    . "2. PDF: running header/footer with logo, page numbers, confidentiality label, dotted-leader table of contents, tinted table headers, zebra rows, gold total rules.\n"
                    . "3. Every report database-driven and reporting-period scoped; no placeholder, proxy or fallback data anywhere.\n"
                    . '4. Reference spec: docs/UI_REPORT_SPEC.md.',
            ],
            [
                'reference' => '006',
                'title' => 'Notifications and workspace enrichment',
                'priority' => 'medium',
                'description' => "Extend the notification bell and the Workspace using the reference apps' patterns:\n\n"
                    . "1. Dispatch database notifications from workflow events (ticket assignment/updates, import completion, ECL run completion, EIR approvals) so the bell has live content.\n"
                    . "2. Workspace: personal work-queue view (my items, awaiting my action) alongside the period-close checklist, with counted tabs and KPI strip.\n"
                    . '3. Reference: Eswatini My Workspace and notification wiring; ZNBS workspaces.',
            ],
        ];

        foreach ($backlog as $item) {
            $ticket = Ticket::firstOrCreate(
                ['reference' => $item['reference']],
                [
                    'title' => $item['title'],
                    'description' => $item['description'],
                    'category' => 'enhancement',
                    'priority' => $item['priority'],
                    'status' => 'open',
                    'requested_by' => 'MAIIC platform review',
                    'source' => 'meeting',
                    'assigned_to' => $owner?->id,
                    'created_by' => $owner?->id,
                ]
            );

            if (! $ticket->wasRecentlyCreated) {
                continue;
            }

            $ticket->timestamps = false;
            $ticket->created_at = $raisedAt;
            $ticket->updated_at = $raisedAt;
            $ticket->save();
            $ticket->timestamps = true;

            $u = new TicketUpdate([
                'ticket_id' => $ticket->id,
                'user_id' => null,
                'body' => 'Ticket logged from the 07 Aug 2026 platform review. Scoped and awaiting scheduling.',
                'new_status' => 'open',
                'is_system' => true,
            ]);
            $u->timestamps = false;
            $u->created_at = $raisedAt;
            $u->updated_at = $raisedAt;
            $u->save();
        }

        $this->command?->info('Backlog tickets #003 to #006 ensured.');
    }
}
