## 8. First-run checklist

Work through this list once the application answers over https and the queue worker is running.

### 8.1 Sign in and secure the seeded account

The seeder (`UsersTableSeeder`, run by `db:seed` and by the bootstrap) creates one administrator, `admin@localhost.com`, with the password `password`. Sign in, open the profile menu and change the password immediately. Enable two-factor authentication on this account. Then create the real MAIIC administrator accounts (chapter 8.3) and either deactivate the seeded account or change its email to a monitored MAIIC address.

The login page shows a security code image. If it does not render, the `gd` extension is missing (chapter 12).

### 8.2 Organisation settings

1. Settings, Organisation: company name (MAIIC), logo, address and contacts. The name and logo appear on every report and manual cover.
2. Settings, Currencies: confirm MWK exists and is the organisation currency.
3. Settings, Email: SMTP details if not set in `.env`; send a test.
4. Administration & support, Access & Setup, Financial Periods: create the current financial year.
5. Settings, Licence: enter the licence details supplied by Dupleix.

### 8.3 Users and roles

Administration & support, Access & Setup, Users & Roles, Roles & Permissions shows the permission matrix grouped by module. The `admin` role holds every permission. Create MAIIC's roles as needed (for example a read-only role for auditors) by ticking the required permissions, then create users on the Users tab and assign roles. Users must be active to sign in.

Every governed change (a loan book build, a schedule, a fee review, a scenario set, a write-off, a restructure, a Governance Centre setting) is proposed by one person and approved by another, and the proposer cannot approve. Create at least two named users who hold the approval permissions (for example `eir.govern`, which the E-Banker feed, write-off and restructure approvals require) before the first month-end. Only the `admin` role can start a job on the Maintenance screen.

### 8.4 Documentation

Open Administration & support, Support & Maintenance, System Documentation and confirm all four items load: User Manual, Administrator Manual, Technical Manual, Installation Guide. Download each PDF once to confirm DomPDF works (fonts, memory). On a workstation with a browser, refresh the manual screenshots so figures match MAIIC's branding:

```bash
php artisan manual:screenshots --base-url=http://127.0.0.1:8000 --email=admin@localhost.com --password=<password>
```

This works only on a workstation with `APP_ENV=local` and `MANUAL_SHOT_CAPTCHA` set (chapter 4.5); a production server refuses the CAPTCHA bypass. The images go to `public/manual/screenshots`, which is committed, so they reach the server with the next deployment.

### 8.5 Smoke checks

| Check | Expected |
|---|---|
| Dashboard | Loads; shows "No data available" until an ECL run exists, which is correct on a fresh database |
| Loan Book | Opens on the latest period, or empty |
| Imports | Customer & Loan Data, Imports & Feeds: the page loads; a test CSV import moves from pending to completed within a minute (proves the worker) |
| E-Banker data | After the bootstrap (chapter 9.8): E-Banker Loads lists the landed packs, Loan Book Builds the approved builds, Periods Built every month from July 2024 |
| Maintenance | Administration & support, Support & Maintenance, Maintenance, Data and checks, "Check the system" (`maiic:task health-check`): the environment, the migration status, the golden numbers (`eir:baselines`) and every report with its PDF |
| IFRS 9 Reports | Hub loads; after data is present, one report exports to PDF and to Excel |
| Audit Trail | Shows the sign-in and settings changes just made |
| Support Tickets | Opens (it needs the `tickets.index` permission from `TicketsPermissionsSeeder`, which `db:seed`, the bootstrap and `maiic:task update` all run). It is empty until `php artisan db:seed --class=TicketSeeder --force` loads the implementation history, tickets #001 to #014; neither `db:seed` nor the bootstrap runs that seeder |
| Notifications | Creating a ticket shows a bell notification for the assignee |

### 8.6 Hand-over record

Record in the go-live certificate (contract Schedule 5): hostname, server specification, database name, the administrator accounts created, the backup schedule (chapter 10), the deployment method chosen (chapter 11) and the date the HTTPS flags were enabled.
