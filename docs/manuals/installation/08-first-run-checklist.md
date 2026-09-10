## 8. First-Run Checklist

Work through this list once the application answers over https and the queue worker is running.

### 8.1 Sign in and secure the seeded account

The seeder creates one administrator, `admin@localhost.com`, with the password `password`. Sign in, open the profile menu and change the password immediately. Enable two-factor authentication on this account. Then create the real MAIIC administrator accounts (chapter 8.3) and either deactivate the seeded account or change its email to a monitored MAIIC address.

The login page shows a security code image. If it does not render, the `gd` extension is missing (chapter 12).

### 8.2 Organisation settings

1. Settings, Organisation: company name (MAIIC), logo, address and contacts. The name and logo appear on every report and manual cover.
2. Settings, Organisation, Currencies: confirm MWK exists and is the organisation currency.
3. Settings, Email: SMTP details if not set in `.env`; send a test.
4. Administration, Financial Periods: create the current financial year.
5. Settings, Licence: enter the licence details supplied by Dupleix.

### 8.3 Users and roles

Administration, Roles and Permissions shows the permission matrix grouped by module. The `admin` role holds every permission. Create MAIIC's roles as needed (for example a read-only role for auditors) by ticking the required permissions, then create users under User Management and assign roles. Users must be active to sign in.

### 8.4 Documentation

Open System Documentation and confirm all four items load: User Manual, Administrator Manual, Technical Manual, Installation Guide. Download each PDF once to confirm DomPDF works (fonts, memory). On a workstation with a browser, refresh the manual screenshots so figures match MAIIC's branding:

```bash
php artisan manual:screenshots --base-url=http://127.0.0.1:8000 --email=admin@localhost.com --password=<password>
```

### 8.5 Smoke checks

| Check | Expected |
|---|---|
| Dashboard | Loads; shows "No data available" until an ECL run exists, which is correct on a fresh database |
| Loan Book | Opens on the latest period, or empty |
| Imports | Page loads; a test CSV import moves from pending to completed within a minute (proves the worker) |
| IFRS 9 Reports | Hub loads; after data is present, one report exports to PDF and to Excel |
| Audit Trail | Shows the sign-in and settings changes just made |
| Support Tickets | Lists tickets #001 to #011 from the seeder |
| Notifications | Creating a ticket shows a bell notification for the assignee |

### 8.6 Hand-over record

Record in the go-live certificate (contract Schedule 5): hostname, server specification, database name, the administrator accounts created, the backup schedule (chapter 10), the deployment method chosen (chapter 11) and the date the HTTPS flags were enabled.
