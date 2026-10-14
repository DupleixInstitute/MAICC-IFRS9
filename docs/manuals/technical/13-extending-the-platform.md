## 13. Extending the Platform

### 13.1 Adding a page and menu item

1. Add the route in `routes/web.php` with a name; keep it inside an `auth` group or gate it in the controller constructor.
2. Create the controller action returning `Inertia::render('Folder/Page', [...])` with database-driven props; whitelist any filter values against the database.
3. Create the Vue page under `resources/js/Pages/Folder/Page.vue` using `AppLayout`, `maiic-panel`, `maiic-table` and `RowActions` (chapter 2.6). Format numbers in accounting style and right-align them with `.num`. Do not draw a path row in the page header: the layout's breadcrumbs come from the menu (chapter 2.4). A detail page that the menu does not hold passes its own last crumbs through the layout's `extraCrumbs` prop.
4. Seed the permission with a `module` and `display_name` in the relevant seeder and grant it to the admin role.
5. Add the screen to `config/menu.php` in the group that matches the contract component (chapter 2.3): a `$leaf`, or a `$tab` in the `$section` of the screens it works with, rather than a new entry beside them. A new top-level entry carries the `heading` of its sidebar block (Data foundation, Financial modelling, Risk & regulatory or Administration & support). Pass the permission the controller enforces so the entry is hidden from users who lack it, and `true` as the download flag for file responses. Keep a row of tabs to seven at most. `tests/Feature/NavigationTest.php` fails if the route is not registered, the top-level order changes or a row carries more than seven tabs; extend its breadcrumb tests when the page sits somewhere new.
6. Add the route name to the screenshot list (`SHOTS`) in `app/Console/Commands/CaptureManualScreenshotsCommand.php` and document the page in the User or Administrator Manual: in the shipped seed content (`database/seeders/data/help_user_content/*.php`, `database/seeders/data/help_admin_content.php`) so a fresh install carries it, or through the help centre.
7. Write a feature test that renders the page as an admin and asserts a guest is redirected.

### 13.2 Adding a hub report

Add an entry to the catalogue in `Reports/Ifrs9ReportsController.php` (key, title, subtitle, category) and a method that returns the normalised payload through `respond()`. The page, PDF and Excel come for free. Use the shared EAD constant and the period helper; compute compare figures with the same scoping as the current period.

### 13.3 Adding an import mapping

Every file import is described once, in the registry `app/Support/Imports/ImportSpecs.php`: its title, numbered steps, the upload format, the template kind, how headings are read, and each column with its type, whether it is required, its format, allowed values and an example. That entry drives the downloadable sample file and the XLSX column guide (`ImportTemplateBuilder`, through the `import-templates.download` and `import-templates.guide` routes), the instructions panel on the screen (`ImportSpecs::forScreen()`, served by the `import-templates.spec` route and drawn by `resources/js/Components/Imports/ImportSpecPanel.vue`) and the server-side check (`ImportSpecValidator::checkFile()`), which refuses the whole file with a row-by-row list before anything is written; Check file runs the check alone (`import-templates.check`). A new import adds its entry there, lists only the columns its importer actually reads, and calls the validator before it writes. `tests/Feature/Imports/ImportTemplateDownloadTest.php` and `ImportValidationTest.php` cover the registry.

For a new EIR extract type, also add the required and optional field lists to `MappedFileReader`, an alias class under `app/Imports`, a service under `app/Services/Eir` that returns loaded, held, skipped and conflict counts, and a branch in `ProcessEirImportJob`. Save mappings through the intake screen so they persist in `import_mappings`.

### 13.4 Design rules

- Colours: MAIIC green, gold, red and grey only. No blue, indigo, teal, purple, pink or rose on a page; the soft icon and block tints of the dark green sidebar are the one exception.
- Row actions: green eye to view, gold pencil to edit, red bin to delete, grey for neutral actions.
- Numbers: thousands separators, two decimals, negatives in parentheses; PD and LGD as percentages; currency from the organisation setting.
- No em or en dashes in any user-facing text, seed data or document.
- Everything database-driven: no placeholder, proxy or fallback data; a missing input is reported, never defaulted.
- Route names, not paths, in configuration and tooling.
- Fail loud: errors surface in the global modal with a plain explanation.

### 13.5 Maintaining the documentation

| Document | Source | How to update |
|---|---|---|
| User Manual, Administrator Manual | Help centre tables, seeded from `database/seeders/data/help_user_content/*.php` and `help_admin_content.php` | Edit in the application from System Documentation, User Manual or Administrator Manual, with the Edit manual button (`settings` permission), or change the seed content. On an installed system the seeders skip a manual that has content; run them with `HELP_SEED_REFRESH=1` to bring the shipped articles up to date by chapter and title (articles authored in the system are left alone), or `php artisan manual:reseed user --force` (or `admin`) to replace the whole manual with the shipped content. Refresh figures with `manual:screenshots`; the PDF is generated from the same rows |
| Technical Manual (this document) | `docs/manuals/technical/*.md` | Edit the chapter file in the same commit as the code change; the in-app page and PDF pick it up immediately |
| Installation Guide | `docs/manuals/installation/*.md` | Same as above |
| EIR and FLI specifications and build log | `docs/MAIIC_EIR_Engine_Specification_v4_2026-10-07.md` (the EIR specification in force), `docs/MAIIC_FLI_Module_Specification_v1_2026-10-13.md`, the earlier `docs/MAIIC_EIR_Revenue_Recognition_Engine_Spec.md` and the build log `docs/Development_of_EIR.md` | Update the build status of the section a change touches in the same commit |

Chapter files are read in file-name order. Each starts with one level-2 heading that becomes the chapter title (a second level-2 heading would replace it) and uses level-3 headings for sections; the renderer (`SystemDocsController`, GitHub-flavoured Markdown with raw HTML stripped) anchors both for the contents rail and the PDF table of contents. The revision date shown is the newest modification time of the chapter files, so there is no date to edit by hand.
