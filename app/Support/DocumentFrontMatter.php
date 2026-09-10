<?php

namespace App\Support;

/**
 * Cover page, document control, how-to-use and role map for the four
 * System Documentation PDFs (contract Schedule 1 deliverables 5 to 7).
 * One place holds the per-document facts so the User Manual,
 * Administrator Manual, Technical Manual and Installation Guide open with
 * the same front matter, in the house style of the Dupleix manuals.
 */
class DocumentFrontMatter
{
    public const VERSION = '1.0';

    /** @return array<string, mixed> */
    public static function for(string $key, string $company, string $generatedAt): array
    {
        $base = [
            'company' => $company,
            'institution' => strcasecmp(trim($company), 'MAIIC') === 0
                ? 'Malawi Agricultural & Industrial Investment Corporation plc'
                : $company,
            'platform' => 'IFRS 9 ECL & EIR Platform',
            'platformLong' => 'IFRS 9 Expected Credit Loss and Effective Interest Rate platform',
            'version' => self::VERSION,
            'preparedDate' => $generatedAt,
            'classification' => 'Confidential',
            'developedBy' => 'Dupleix Institute - Risk | Strategy | Data Analytics',
            'reviewCycle' => 'Reviewed at least annually and on any material change to the platform or to the IFRS 9 methodology.',
            'retention' => 'Retained for 10 years in line with the records-management policy.',
            'revisions' => [
                [self::VERSION, $generatedAt, 'Dupleix Institute', 'Initial release under the Implementation, Licence and Support Agreement.'],
            ],
        ];

        $docs = [
            'user' => [
                'title' => 'User Manual',
                'subtitle' => 'Daily use of the platform: data, models, ECL, EIR and reports',
                'deliverable' => 'Schedule 1, deliverable 5: User Manual',
                'owner' => 'Chief Financial Officer',
                'approvedBy' => 'Pending - Chief Financial Officer',
                'status' => 'Draft - pending MAIIC approval',
                'appliesTo' => $company . ' IFRS 9 ECL and EIR platform, all business users',
                'audience' => 'staff, auditors and the Board',
                'howToUse' => [
                    'This manual is task-based. Each article says what the screen is for, where to find it, the numbered steps to follow and what the result feeds downstream.',
                    ['Follow the numbered steps in order within each article; figures are numbered and captioned.', 'Every screen is scoped to a reporting period; check the period selector before reading any figure.', 'Locked and closed results cannot be edited; create a new draft rather than changing an approved figure.', 'Maker and checker applies to fee classification and EIR approval: the person who prepares is never the person who approves.', 'Use the Glossary and the Troubleshooting article at the back for any term or error message.'],
                    'Before a period close, work through the Workspace checklist: import, segment, stage, PD, LGD, forward-looking adjustment, ECL run, stress run, report review, sign-off.',
                ],
                'roles' => [
                    ['New user', 'Read Getting Started and Daily Use end to end before anything else.'],
                    ['Credit or risk analyst', 'Customer and Loan Data, IFRS 9 Model Setup and ECL and Reporting: load, stage, calculate and reconcile.'],
                    ['Finance preparer', 'EIR and Revenue Recognition and the reports: prepare the figures a reviewer approves.'],
                    ['Reviewer or CFO', 'Dashboard, Workspace and the IFRS 9 Reports hub: review, approve and sign off the period.'],
                    ['Auditor', 'Account-level ECL trail, reconciliation reports, audit trail and the EIR coverage and reconciliation screens.'],
                    ['Administrator', 'See the Administrator Manual for users, settings, periods and governance.'],
                ],
                'distribution' => [
                    ['Board of Directors and Audit Committee', 'Governance and approval', 'Controlled'],
                    ['Chief Financial Officer', 'Document owner', 'Master'],
                    ['Finance and Credit teams', 'Preparers and reviewers', 'Controlled'],
                    ['Internal Audit and Deloitte', 'Independent assurance', 'Controlled'],
                    ['Reserve Bank of Malawi (on request)', 'Regulator', 'Read-only'],
                    ['Dupleix Institute', 'Developer and support', 'Controlled'],
                ],
            ],
            'admin' => [
                'title' => 'Administrator Manual',
                'subtitle' => 'Configuration, access control, periods, data operations and support',
                'deliverable' => 'Schedule 1, deliverable 6: Administrator / Technical Manual (administration volume)',
                'owner' => 'Head of ICT',
                'approvedBy' => 'Pending - Head of ICT',
                'status' => 'Draft - pending MAIIC approval',
                'appliesTo' => $company . ' IFRS 9 ECL and EIR platform, administrators',
                'audience' => 'administrators, auditors and Dupleix support staff',
                'howToUse' => [
                    'This manual covers everything that is not a calculation: who may sign in, what they may do, the organisation settings and reference data, periods, the governance of imports and locked results, monitoring and support.',
                    ['Each article gives the purpose of the screen, the steps to follow and the controls that apply.', 'Access changes take effect on the next request; there is no need to sign users out.', 'Overrides of maker and checker controls are recorded in the audit trail and should be exceptional.', 'Server-side tasks (backups, the queue worker, upgrades) are in the Installation Guide.', 'Read the Technical Manual for how the engines calculate.'],
                    'Before go-live, complete the first-run checklist in the Installation Guide: change the seeded password, create MAIIC accounts, set the organisation details and currency, create the financial year, and verify all four documents download.',
                ],
                'roles' => [
                    ['Head of ICT', 'Users and Access, Housekeeping, and the Installation Guide for the server.'],
                    ['Finance administrator', 'Organisation Settings, Periods and Close, Data Operations.'],
                    ['Accounting owner (EIR)', 'Data Operations, EIR data governance: rules, fee review, schedule approval, EIR lock.'],
                    ['Internal Audit', 'Monitoring and Support: audit trail, tickets, notifications.'],
                    ['Dupleix support', 'Documentation Maintenance and Getting help.'],
                ],
                'distribution' => [
                    ['Head of ICT', 'Document owner', 'Master'],
                    ['Chief Financial Officer', 'Sponsor and approver', 'Controlled'],
                    ['Finance administrators', 'Configuration and periods', 'Controlled'],
                    ['Internal Audit', 'Independent assurance', 'Controlled'],
                    ['Dupleix Institute', 'Developer and support', 'Controlled'],
                ],
            ],
            'technical' => [
                'title' => 'Technical Manual',
                'subtitle' => 'Architecture, data model, engines, security and operations',
                'deliverable' => 'Schedule 1, deliverable 6: Administrator / Technical Manual (technical volume)',
                'owner' => 'Head of ICT',
                'approvedBy' => 'Pending - Head of ICT',
                'status' => 'Draft - pending MAIIC review',
                'appliesTo' => $company . ' IFRS 9 ECL and EIR platform, source code as installed',
                'audience' => 'ICT staff, Dupleix engineers and auditors',
                'howToUse' => [
                    'This manual describes how the platform is built and how each figure is produced, so that a reported number can be traced to the code that computed it.',
                    ['Chapters 2 to 4 explain the architecture, data model and import pipeline; chapters 5 and 6 quote the formulas the engines apply.', 'File paths in backticks point to the code; the live schema appendix is read from the connected database at render time.', 'Where a behaviour is guarded by a lock, an approval or a permission, the guard is named.', 'Chapters 9 to 12 are for operators: jobs, configuration, testing and troubleshooting.', 'The chapter files live in the repository and are updated in the same change as the code they describe.'],
                    'Never run the full test suite against a live database: the sqlite override in phpunit.xml must stay active (chapter 11).',
                ],
                'roles' => [
                    ['ICT administrator', 'Chapters 1, 2, 8, 9, 10 and 12: what runs where, security, jobs, configuration, operations.'],
                    ['Developer', 'Chapters 2 to 7, 11 and 13: architecture, data model, engines, reports, tests, extending.'],
                    ['Auditor or model validator', 'Chapters 3 to 7 and the schema appendix: data, formulas, locks and traceability.'],
                    ['Finance reviewer', 'Chapters 5 and 6: the IFRS 9 and EIR calculations in plain terms with their formulas.'],
                ],
                'distribution' => [
                    ['Head of ICT', 'Document owner', 'Master'],
                    ['Chief Financial Officer', 'Sponsor', 'Controlled'],
                    ['Internal Audit and Deloitte', 'Independent assurance and model review', 'Controlled'],
                    ['Dupleix Institute', 'Developer and support', 'Controlled'],
                ],
            ],
            'installation' => [
                'title' => 'Installation and Configuration Guide',
                'subtitle' => 'Installation, configuration and maintenance of the platform in the MAIIC environment',
                'deliverable' => 'Schedule 1, deliverable 7: Installation and Configuration Guide',
                'owner' => 'Head of ICT',
                'approvedBy' => 'Pending - Head of ICT',
                'status' => 'Draft - pending MAIIC review',
                'appliesTo' => $company . ' on-premises environment as specified in Schedule 2',
                'audience' => 'ICT staff and Dupleix deployment engineers',
                'howToUse' => [
                    'This guide is sequential. Work through the chapters in order for a new installation; use chapters 10 to 12 for backups, upgrades and troubleshooting on a running system.',
                    ['Commands are given for Ubuntu 22.04 and for Windows with XAMPP; the Linux path is the production one.', 'Turn on the HTTPS flags only after a certificate is working, in the order given in chapter 6.', 'The queue worker must run as a service; imports and calculations stay pending without it.', 'Take a database backup before every release that contains migrations.', 'Record the go-live facts in the certificate template from Schedule 5 of the agreement.'],
                    'Contract Schedule 3 acceptance: imports reconcile 100 percent on record counts and within 0.1 percent on values; ECL within 5 percent or MWK 50 million; EIR within 2 percent of the independent recalculation.',
                ],
                'roles' => [
                    ['Server administrator', 'Chapters 2, 3, 6, 7 and 10: prerequisites, installation, web server, services, backups.'],
                    ['Application administrator', 'Chapters 5, 8 and 9: environment, first run, data loading.'],
                    ['Dupleix deployment engineer', 'Chapters 3, 9, 11 and 12: installation, data loading, deployment, troubleshooting.'],
                ],
                'distribution' => [
                    ['Head of ICT', 'Document owner', 'Master'],
                    ['ICT operations', 'Server and database administration', 'Controlled'],
                    ['Dupleix Institute', 'Deployment and support', 'Controlled'],
                ],
            ],
        ];

        return array_merge($base, $docs[$key]);
    }
}
