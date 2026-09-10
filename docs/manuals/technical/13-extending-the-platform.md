## 13. Extending the Platform

### 13.1 Adding a page and menu item

1. Add the route in `routes/web.php` with a name; keep it inside an `auth` group or gate it in the controller constructor.
2. Create the controller action returning `Inertia::render('Folder/Page', [...])` with database-driven props; whitelist any filter values against the database.
3. Create the Vue page under `resources/js/Pages/Folder/Page.vue` using `AppLayout`, `maiic-panel`, `maiic-table` and `RowActions` (chapter 2.5). Format numbers in accounting style and right-align them with `.num`.
4. Seed the permission with a `module` and `display_name` in the relevant seeder and grant it to the admin role.
5. Add a leaf to `config/menu.php` in the group that matches the contract component; pass `true` as the download flag for file responses.
6. Add the route name to the screenshot list in `app/Console/Commands/CaptureManualScreenshotsCommand.php` and document the page in the User or Administrator Manual through the help centre.
7. Write a feature test that renders the page as an admin and asserts a guest is redirected.

### 13.2 Adding a hub report

Add an entry to the catalogue in `Reports/Ifrs9ReportsController.php` (key, title, subtitle, category) and a method that returns the normalised payload through `respond()`. The page, PDF and Excel come for free. Use the shared EAD constant and the period helper; compute compare figures with the same scoping as the current period.

### 13.3 Adding an import mapping

For a new EIR extract type, add the required and optional field lists to `MappedFileReader`, an alias class under `app/Imports`, a service under `app/Services/Eir` that returns loaded, held, skipped and conflict counts, and a branch in `ProcessEirImportJob`. Save mappings through the intake screen so they persist in `import_mappings`.

### 13.4 Design rules

- Colours: MAIIC green, gold, red and grey only. No blue, indigo, teal, purple, pink or rose.
- Row actions: green eye to view, gold pencil to edit, red bin to delete, grey for neutral actions.
- Numbers: thousands separators, two decimals, negatives in parentheses; PD and LGD as percentages; currency from the organisation setting.
- No em or en dashes in any user-facing text, seed data or document.
- Everything database-driven: no placeholder, proxy or fallback data; a missing input is reported, never defaulted.
- Route names, not paths, in configuration and tooling.
- Fail loud: errors surface in the global modal with a plain explanation.

### 13.5 Maintaining the documentation

| Document | Source | How to update |
|---|---|---|
| User Manual, Administrator Manual | Help centre tables | Edit in the application under System Documentation, Edit manual; refresh figures with `manual:screenshots`; PDF is generated from the same rows |
| Technical Manual (this document) | `docs/manuals/technical/*.md` | Edit the chapter file in the same commit as the code change; the in-app page and PDF pick it up immediately |
| Installation Guide | `docs/manuals/installation/*.md` | Same as above |
| EIR specification and build log | `docs/MAIIC_EIR_Revenue_Recognition_Engine_Spec.md`, `docs/Development_of_EIR.md` | Update the build status table when a phase changes state |

Chapter files start with a level-2 heading that becomes the chapter title and use level-3 headings for sections; the renderer anchors both for the contents rail and the PDF table of contents.
