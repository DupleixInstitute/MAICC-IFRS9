import sys
sys.path.insert(0, r'C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/inst')
from edhelp import *
rep('08-first-run-checklist.md', [
("## 8. First-Run Checklist", "## 8. First-run checklist"),
("The seeder creates one administrator, `admin@localhost.com`, with the password `password`.",
 "The seeder (`UsersTableSeeder`, run by `db:seed` and by the bootstrap) creates one administrator, `admin@localhost.com`, with the password `password`."),
("2. Settings, Organisation, Currencies: confirm MWK exists and is the organisation currency.",
 "2. Settings, Currencies: confirm MWK exists and is the organisation currency."),
("4. Administration, Financial Periods: create the current financial year.",
 "4. Administration & support, Access & Setup, Financial Periods: create the current financial year."),
("Administration, Roles and Permissions shows the permission matrix grouped by module. The `admin` role holds every permission. Create MAIIC's roles as needed (for example a read-only role for auditors) by ticking the required permissions, then create users under User Management and assign roles. Users must be active to sign in.",
 "Administration & support, Access & Setup, Users & Roles, Roles & Permissions shows the permission matrix grouped by module. The `admin` role holds every permission. Create MAIIC's roles as needed (for example a read-only role for auditors) by ticking the required permissions, then create users on the Users tab and assign roles. Users must be active to sign in.\n\nEvery governed change (a loan book build, a schedule, a fee review, a scenario set, a write-off, a restructure, a Governance Centre setting) is proposed by one person and approved by another, and the proposer cannot approve. Create at least two named users with `eir.govern` before the first month-end. Only the `admin` role can start a job on the Maintenance screen."),
("Open System Documentation and confirm all four items load: User Manual, Administrator Manual, Technical Manual, Installation Guide.",
 "Open Administration & support, Support & Maintenance, System Documentation and confirm all four items load: User Manual, Administrator Manual, Technical Manual, Installation Guide."),
("```bash\nphp artisan manual:screenshots --base-url=http://127.0.0.1:8000 --email=admin@localhost.com --password=<password>\n```",
 "```bash\nphp artisan manual:screenshots --base-url=http://127.0.0.1:8000 --email=admin@localhost.com --password=<password>\n```\n\nThis works only on a workstation with `APP_ENV=local` and `MANUAL_SHOT_CAPTCHA` set (chapter 4.5); a production server refuses the CAPTCHA bypass. The images go to `public/manual/screenshots`, which is committed, so they reach the server with the next deployment."),
("| Imports | Page loads; a test CSV import moves from pending to completed within a minute (proves the worker) |",
 "| Imports | Customer & Loan Data, Imports & Feeds: the page loads; a test CSV import moves from pending to completed within a minute (proves the worker) |\n| E-Banker data | After the bootstrap (chapter 9.8): E-Banker Loads lists the landed packs, Loan Book Builds the approved builds, Periods Built every month from July 2024 |\n| Maintenance | Administration & support, Support & Maintenance, Maintenance, Data and checks, \"Check the system\" (`maiic:task health-check`): the environment, the migration status, the golden numbers (`eir:baselines`) and every report with its PDF |"),
("| Support Tickets | Lists tickets #001 to #011 from the seeder |",
 "| Support Tickets | Opens (it needs the `tickets.index` permission from `TicketsPermissionsSeeder`, which `db:seed`, the bootstrap and `maiic:task update` all run). It is empty until `php artisan db:seed --class=TicketSeeder --force` loads the implementation history, tickets #001 to #014; neither `db:seed` nor the bootstrap runs that seeder |"),
])
