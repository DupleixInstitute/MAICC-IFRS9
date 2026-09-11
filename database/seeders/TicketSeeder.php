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
                'status' => 'resolved',
                'resolution' => 'Delivered on the eir_revenue_recognition branch (commit 1a18176), 07 Aug 2026:' . "\n"
                    . '. Excel: every one of the 30 IFRS 9 hub reports downloads as a branded .xlsx via a single generic exporter (brand header, green column bands, gold section rules, zebra rows, frozen panes, accounting number formats with negatives in parentheses, real typed numbers that remain summable).' . "\n"
                    . '. PDF: MAIIC logo in the running header, tricolor green/gold/red brand bar, generated-by user, confidentiality note and page numbers in the footer.' . "\n"
                    . '. Excel button beside Download PDF on every report page; covered by feature tests (render, PDF, Excel, permission denial).' . "\n"
                    . 'Out of scope: legacy CSV extracts (loan book / ECL export) keep their existing plain format.',
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
            [
                'reference' => '007',
                'title' => 'User manual overhaul with live system screenshots',
                'priority' => 'high',
                'description' => "Replace the hardcoded user manual with the database-driven help centre model (reference: Eswatini credit scoring help centre):\n\n"
                    . "1. Help tables: chapters, articles (versioned, role-scoped), numbered steps, uploaded images with captions and figure numbers, per-page route mapping for the contextual help button.\n"
                    . "2. Authoring UI with real image upload (no base64 into a 64KB column) behind a manage permission.\n"
                    . "3. Automated screenshot capture command that signs into the running system and refreshes the manual images after UI changes, plus a manual:pdf command rendering the same content to a branded PDF.\n"
                    . "4. Immediate fixes shipped separately: manuals CRUD is now authenticated; the broken show route and validation gaps are tracked here.",
                'status' => 'resolved',
                'resolved_at' => '2026-09-11 17:00:00',
                'resolution' => 'Delivered 13 Aug 2026 (commit 8316044): the help centre tables, the authoring screen with real image upload, the screenshot capture command, and the branded PDF rendered from the same rows under #010. '
                    . 'Extended far beyond the original scope on 11 Sep 2026 (cf39a8b, 2f274a2): the User Manual rewritten screen by screen to 11 chapters, 75 articles and about 47,000 words, carrying 329 numbered steps, 113 figures and 293 page mappings, rendering to a 171-page PDF with a cover page and document control. The capture command now covers 120 pages, warns instead of saving a blank picture, and every captured figure is used in a manual.',
            ],
            [
                'reference' => '008',
                'title' => 'Settings consolidation and role-aware workspace',
                'priority' => 'high',
                'description' => "Consolidate administration configuration under one aligned Settings area and make the workspace role-aware end to end:\n\n"
                    . "1. Settings hub: group Organisation, General, System, Email, SMS, Manual, Licence and reference data under clear headers; retire or hide legacy loan-template sections (score bands, SMS) behind flags; move Payment Calculation to LGD Model Setup; add IFRS 9 policy settings (default reporting period, rounding, discounting convention).\n"
                    . "2. Fixes shipped separately and tracked here: settings updates permission-gated, General settings crash fixed, Licence permissions corrected, Financial Periods permissions seeded.\n"
                    . "3. Workspace roles: editable checklist definitions with responsible role per step, maker-checker statuses (prepared/reviewed), completed-by as a user reference, notifications to the responsible role when a step becomes actionable.\n"
                    . "4. Seed proper IFRS 9 roles (Preparer, Reviewer, Approver, Read-only) with sensible permission sets.",
            ],
            [
                'reference' => '009',
                'title' => 'Remove legacy credit-scoring modules from the codebase',
                'priority' => 'medium',
                'description' => "The credit-scoring-era roles and permissions were removed from the Roles matrix (56 permissions and the client role). Finish the job by removing the now-orphaned code so the routes cannot be reached at all:\n\n"
                    . "1. Routes, controllers and pages: loan applications and approval stages, scoring attributes, client financial analysis (shareholders, balance sheets, income statements, ratio analysis, Porter's five forces), communication campaigns/templates/gateways, locations (provinces, districts, villages, wards, inkhundla, regions), member portal, medical-era leftovers (vitals, prescriptions, consultations, POS prints) and the dead Calculator page.\n"
                    . "2. Motivation: their controllers still reference the deleted permissions, so a manually typed URL now raises a permission-does-not-exist error instead of a clean 403.\n"
                    . "3. Retire the legacy tests for those modules at the same time so the full suite can go green.",
            ],
            [
                'reference' => '010',
                'title' => 'Database-driven manual authoring and single-source PDF',
                'priority' => 'high',
                'description' => "Follow-on to #007: the manual text lived in a hardcoded Vue page and a separate PDF template, so the two drifted.\n\n"
                    . "1. Help-centre tables (chapters, articles, numbered steps, figures, per-page route mappings).\n"
                    . "2. In-app reader with search and a branded PDF rendered from the same rows.\n"
                    . "3. Authoring screen behind the settings permission with figure upload and draft status.\n"
                    . '4. Seeder that ports the full manual with the captured screenshots and never clobbers authored edits.',
                'status' => 'resolved',
                'resolution' => 'Delivered 13 Aug 2026 (commit 7e8599d): help_categories, help_articles, help_article_steps, '
                    . 'help_article_images and help_article_routes; reader at /help with PDF export; authoring at /help/manage; '
                    . 'HelpContentSeeder with 8 chapters and 28 articles; the hardcoded manual page and template retired.',
            ],
            [
                'reference' => '011',
                'title' => 'Administrator Manual, Technical Manual and Installation Guide (contract deliverables 6 and 7)',
                'priority' => 'high',
                'description' => "Schedule 1 of the signed agreement (19 Aug 2026) lists three documentation deliverables: the User Manual (item 5, delivered under #010), "
                    . "the Administrator / Technical Manual (item 6) and the Installation and Configuration Guide (item 7). Items 6 and 7 gate Milestone 4 (handover, 45 percent).\n\n"
                    . "1. Administrator Manual as a second manual in the help centre: access and roles, organisation settings and reference data, periods and close, data operations and locks, audit trail, tickets, documentation maintenance, housekeeping. Same authoring, screenshots and PDF pipeline as the User Manual.\n"
                    . "2. Technical Manual as versioned Markdown in the repository (architecture, data model with a live schema appendix, import pipeline, IFRS 9 engines with formulas, EIR engine, reports, security, jobs and commands, configuration, testing, operations, extending, glossary), rendered in-app and to PDF from the same source.\n"
                    . "3. Installation and Configuration Guide as versioned Markdown (prerequisites from Schedule 2, Linux and Windows installation, environment reference, web server and TLS, queue and scheduler, first-run checklist, data loading, backup and recovery, upgrades, troubleshooting), rendered in-app and to PDF.\n"
                    . '4. All four documents listed under System Documentation in the navigation.',
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
                    'status' => $item['status'] ?? 'open',
                    'requested_by' => 'MAIIC platform review',
                    'source' => 'meeting',
                    'assigned_to' => $owner?->id,
                    'created_by' => $owner?->id,
                    'resolution' => $item['resolution'] ?? null,
                    'resolved_at' => ($item['status'] ?? null) === 'resolved'
                        ? (isset($item['resolved_at']) ? Carbon::parse($item['resolved_at']) : $raisedAt->copy()->addDay())
                        : null,
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

        $this->command?->info('Backlog tickets #003 to #011 ensured.');

        $this->seedDataReviewFindings($owner);
    }

    /**
     * Findings from the reconciliation of MAIIC's December 2025 close pack
     * (trial balances, the general ledger spools, the three data extracts and
     * the signed financial statements) carried out on 11 Sep 2026. Each one
     * changes what the platform should accept from the client extracts, so
     * they are logged rather than left in a review note.
     */
    private function seedDataReviewFindings(?User $owner): void
    {
        $raisedAt = Carbon::parse('2026-09-11 10:00:00');

        $findings = [
            [
                'reference' => '012',
                'title' => 'General ledger reconciliation residual is zero by construction and proves nothing',
                'priority' => 'high',
                'category' => 'issue',
                'description' => "The reconciliation in the December 2025 pack derives its balancing figure from the same totals it then reconciles, so the residual is algebraically zero whatever the underlying data says. A clean reconciliation therefore carries no assurance at all, and the same shape must not be built into the platform's own reconciliation report.

"
                    . "1. Rework the check so both sides are derived independently: the ledger movement from the general ledger spools, and the expected movement from the loan book and the effective interest rate amortisation.
"
                    . "2. Show the two sides and the difference between them, rather than a single residual line.
"
                    . "3. Fail the check, visibly, when the two sides disagree beyond the Schedule 3 tolerance of 0.1 percent.
"
                    . '4. Note the limitation in the Technical Manual so no one reads a zero as evidence.',
            ],
            [
                'reference' => '013',
                'title' => 'NASCOMEX balance on general ledger 3065 is outside the Extract A totals',
                'priority' => 'high',
                'category' => 'issue',
                'description' => "About MWK 2.06 billion sitting on general ledger account 3065 (NASCOMEX) is carried in the trial balance but is not inside the Extract A loan totals, so the extract understates the book by that amount. Anyone tying Extract A to the trial balance will find a gap and will not be told why.

"
                    . "1. Confirm with MAIIC finance whether the balance is a loan exposure, and if so why it is outside the loan extract.
"
                    . "2. If it is in scope, agree how it is delivered: inside Extract A, or as a named reconciling item.
"
                    . "3. Until it is resolved, report it as a standing reconciling item on the general ledger reconciliation rather than absorbing it silently.
"
                    . '4. Record the treatment in the Technical Manual data hazards section.',
            ],
            [
                'reference' => '014',
                'title' => 'Extract B typed dates have the day and month transposed throughout',
                'priority' => 'critical',
                'category' => 'issue',
                'description' => "Every one of the 775 typed Excel dates in Extract B has a day of 12 or lower, which cannot happen by chance and shows the day and month are transposed across the whole column. Text dates in the same columns are correct. Left alone this misdates disbursements and maturities, which moves ageing, staging and the effective interest rate schedules.

"
                    . "1. Ask MAIIC to re-export Extract B with dates as text in ISO form, which removes the problem at source.
"
                    . "2. Until that lands, detect the condition on import: if no day in the column exceeds 12, the column is ambiguous, so reject the file rather than guessing.
"
                    . "3. Never silently swap the values. A guessed date is worse than a rejected file because nothing downstream shows it was guessed.
"
                    . '4. Document the test and the rejection message in the Technical Manual and the Administrator Manual import governance article.',
            ],
        ];

        foreach ($findings as $item) {
            $ticket = Ticket::firstOrCreate(
                ['reference' => $item['reference']],
                [
                    'title' => $item['title'],
                    'description' => $item['description'],
                    'category' => $item['category'],
                    'priority' => $item['priority'],
                    'status' => 'open',
                    'requested_by' => 'Dupleix data review',
                    'source' => 'review',
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
                'body' => 'Raised from the end-to-end reconciliation of the MAIIC December 2025 close pack on 11 Sep 2026. Awaiting MAIIC confirmation before the fix is scheduled.',
                'new_status' => 'open',
                'is_system' => true,
            ]);
            $u->timestamps = false;
            $u->created_at = $raisedAt;
            $u->updated_at = $raisedAt;
            $u->save();
        }

        $this->command?->info('Data review tickets #012 to #014 ensured.');
    }
}
