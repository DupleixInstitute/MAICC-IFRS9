"""Copy the reference logic the spec cites from the three source repositories into MAICC-IFRS9/docs/reference, with a manifest and an index by spec section."""
import hashlib, json, os, shutil, subprocess, datetime
DST = r"C:\xampp\htdocs\MAICC-IFRS9\docs\reference"
SRC = {
    "znbs-suite": r"C:\xampp\htdocs\Stress-Testing-App",
    "fdh-ifrs9-laravel": r"C:\xampp\htdocs\FDH_IFRS9_Laravel",
    "fdh-ifrs9-plain-php": r"C:\xampp\htdocs\ifrs9",
}
# (repo, path, spec section, what to verify)
FILES = [
    # section 6: landing zone, bootstrap, feed
    ("znbs-suite", "app/Console/Commands/IcaapBootstrapCommand.php", "6.10", "One-command bootstrap: --fresh safety, committed inputs read first, steps, automated-approver label, --verify golden table"),
    ("znbs-suite", "app/Models/SourceImportBatch.php", "6.4, 13.3", "Provenance of an import: source, address, fetched-at, who, rows, hash"),
    ("znbs-suite", "database/migrations/2026_05_20_185622_create_source_import_manifest_tables.php", "6.4", "Manifest and batch tables for landed files"),
    ("znbs-suite", "app/Console/Commands/ImportZnbsLoanListingCommand.php", "6.5 route 1", "A committed client input landed by command, idempotent on the file"),
    # section 11: the suite UI
    ("fdh-ifrs9-laravel", "resources/js/nav.js", "11.2, 11.3", "The navigation tree as single source of truth: groups, accents, items, routes"),
    ("fdh-ifrs9-laravel", "resources/js/Layouts/AppLayout.vue", "11.2, 11.4", "Shell: sidebar collapse with localStorage, mobile drawer, page header with breadcrumb derived from the tree, busy pill, per-page help"),
    ("fdh-ifrs9-laravel", "resources/js/Components/Shell/Sidebar.vue", "11.2, 11.4", "Accent-coloured groups, one open at a time, active bar, icon rail"),
    ("fdh-ifrs9-laravel", "resources/js/Components/Shell/Topbar.vue", "11.2, 11.5", "Menu toggle, period chip, theme toggle, notifications, user menu"),
    ("fdh-ifrs9-laravel", "resources/js/Components/Shell/Icon.vue", "11.2", "Icon set used by the shell"),
    ("fdh-ifrs9-laravel", "resources/js/Components/Shell/StatCard.vue", "11.2", "Dashboard stat card"),
    ("fdh-ifrs9-laravel", "resources/js/Components/Shell/EmptyState.vue", "11.2", "Empty state"),
    ("fdh-ifrs9-laravel", "resources/js/composables/useTheme.js", "11.5", "Light/dark: class mode, localStorage, one shared ref"),
    ("fdh-ifrs9-laravel", "resources/views/app.blade.php", "11.5", "The no-flash inline script that sets the dark class before paint"),
    ("fdh-ifrs9-laravel", "tailwind.config.js", "11.5", "darkMode: 'class'"),
    ("fdh-ifrs9-laravel", "resources/js/Components/CollapsibleHelp.vue", "11.2", "Per-page collapsible help panel"),
    ("fdh-ifrs9-laravel", "resources/js/Components/ManualHelpButton.vue", "11.2", "Floating help button"),
    ("znbs-suite", "resources/js/Pages/Audit/Trace.vue", "12.5", "Per-record audit trace, oldest to newest"),
    ("znbs-suite", "resources/js/Pages/Audit/Index.vue", "12.5", "Audit index"),
    # section 12: compliance audit workbooks
    ("znbs-suite", "tools/compliance/audit_workbook.py", "12.3, 12.4", "Contents / Audit / Findings sheets, nine columns, statuses, validation that refuses a misleading workbook"),
    ("znbs-suite", "tools/compliance/build_audit.py", "12.6", "One data module to three outputs"),
    ("znbs-suite", "tools/compliance/md_to_pdf.py", "12.6", "Markdown twin to PDF"),
    ("znbs-suite", "tools/compliance/directives/__init__.py", "12.6", "Directive module registry"),
    ("znbs-suite", "tools/compliance/directives/notice_1198_icaap.py", "12.6", "An example data module: META, ROWS, FINDINGS (content is BoZ-specific; the shape is what MAIIC reuses)"),
    # section 13: macro statistics
    ("znbs-suite", "app/Services/Macro/WorldBankFetcherService.php", "13.3", "World Bank fetch: endpoint, timeout, retries, country, year range, preview payload"),
    ("znbs-suite", "app/Services/Macro/ImfWeoParserService.php", "13.3", "WEO parser: UTF-16 tab file, country filter, estimates-start year to actual/forecast"),
    ("znbs-suite", "app/Console/Commands/ImportWorldBankMacroCommand.php", "13.3", "macro:import-worldbank, non-fatal per indicator"),
    ("znbs-suite", "database/seeders/MacroExternalCodesSeeder.php", "13.3, 13.4", "Indicator codes on the variable"),
    ("znbs-suite", "app/Http/Controllers/MacroController.php", "13.3", "Preview and commit endpoints for both sources; CSV template and export"),
    ("znbs-suite", "app/Models/MacroVariable.php", "13.3", "external_codes"),
    ("znbs-suite", "app/Models/MacroObservation.php", "13.3", "Observation keyed on variable, period, period type"),
    ("znbs-suite", "app/Models/MacroImportLog.php", "13.3", "Import log"),
    ("znbs-suite", "database/migrations/2026_03_30_100001_create_macro_variables_table.php", "13.3", "macro_variables"),
    ("znbs-suite", "database/migrations/2026_03_30_100002_create_macro_observations_table.php", "13.3", "macro_observations"),
    ("znbs-suite", "resources/js/Pages/Macro/Index.vue", "13.3, 15.4", "The five-tab screen; the governed assumptions tab with approval lineage"),
    # section 14: FLI
    ("fdh-ifrs9-laravel", "app/Services/Fli/CorrelationFinder.php", "14.4", "Auto-correlate: X x Y x lag grid, Spearman, Theil-Sen, Pearson, OLS, ranking, reasons, immutable runs, fail-closed"),
    ("fdh-ifrs9-laravel", "app/Services/Fli/RegressionEngine.php", "14.5", "Regression with the full coefficient vector"),
    ("fdh-ifrs9-laravel", "app/Services/Fli/Diagnostics.php", "14.4", "Normality, break window, overlap diagnostics"),
    ("fdh-ifrs9-laravel", "app/Services/Fli/Guardrail.php", "14.4, 14.5", "Guardrails on what may be approved"),
    ("fdh-ifrs9-laravel", "app/Services/Fli/AdjustmentMethodEngine.php", "14.6", "The adjustment methods"),
    ("fdh-ifrs9-laravel", "app/Services/Fli/CreditLossProxyDeriver.php", "14.4", "Deriving the credit-loss proxies (Y series)"),
    ("fdh-ifrs9-laravel", "app/Services/Fli/FliSeriesProfiler.php", "14.4", "Series profiling"),
    ("fdh-ifrs9-laravel", "app/Services/Fli/StatisticsSidecar.php", "14.4", "Statistics helpers"),
    ("fdh-ifrs9-laravel", "app/Services/Fli/StructuralEventsRegister.php", "14.6, 15.4", "Register of structural events (devaluations, droughts) used as anchors"),
    ("fdh-ifrs9-laravel", "app/Services/Fli/ScenarioWeightValidator.php", "15.6", "Scenario weight validation"),
    ("fdh-ifrs9-laravel", "app/Services/Fli/MacroModule.php", "13, 14", "Module wiring"),
    ("fdh-ifrs9-laravel", "app/Http/Controllers/CalculatorController.php", "14.8", "The FLI calculator endpoints"),
    ("fdh-ifrs9-laravel", "resources/js/Pages/Calculators/Fli.vue", "14.8", "The FLI / Correlation Finder screen"),
    ("fdh-ifrs9-laravel", "resources/js/Pages/Governance/FliRegisters.vue", "14.6, 14.8", "The FLI registers screen (suggestions, models, overlays)"),
    ("fdh-ifrs9-laravel", "docs/SCOPING_NOTES.md", "11, 14", "Scoping notes B4/B5: the suite grouping convention"),
    ("fdh-ifrs9-plain-php", "calculator/regression/regression_calculator.php", "14.4", "The original correlation tests: expected sign, R2 cut-off, red/green, slope and intercept"),
    ("fdh-ifrs9-plain-php", "calculator/regression/fetch_regression_results.php", "14.4", "Results fetch"),
    ("fdh-ifrs9-plain-php", "maintenance/regression_definitions/regression_definitions.php", "14.4", "Regression definitions: statistic, credit-loss code, expected sign, R2 cut-off"),
    ("fdh-ifrs9-plain-php", "maintenance/regression_definitions/fetch_regression_definitions.php", "14.4", "Definitions fetch"),
    ("fdh-ifrs9-plain-php", "MacroStatisticsRegressionDefinitions.htm", "14.4", "Definitions page"),
    ("fdh-ifrs9-plain-php", "README.md", "14", "The plain-PHP app's pipeline: PD, LGD, EAD, FLI, ECL"),
    # section 15: scenarios
    ("znbs-suite", "database/migrations/2026_03_30_400001_create_scenarios_table.php", "15.4", "scenarios"),
    ("znbs-suite", "database/migrations/2026_03_30_400002_create_scenario_shocks_table.php", "15.4", "Shocks: pct_change, absolute, replace, multiplier, by variable and year offset"),
    ("znbs-suite", "database/migrations/2026_04_02_100001_extend_scenario_engine.php", "15.4", "Scenario engine extension"),
    ("znbs-suite", "database/migrations/2026_05_21_260000_step19_scenario_governance.php", "15.4, 15.6", "Scenario governance: approval, lock"),
    ("znbs-suite", "database/migrations/2026_05_12_140000_create_scenario_parameter_library_table.php", "15.4", "Parameter library"),
    ("znbs-suite", "app/Http/Controllers/ScenarioController.php", "15.4, 15.9", "Scenario CRUD, propose, approve, lock"),
    ("znbs-suite", "app/Http/Controllers/ScenarioSupportingDocumentController.php", "15.4", "Evidence attachments on a scenario"),
    ("znbs-suite", "app/Models/ApprovalAuditLog.php", "15.4", "Approval audit log"),
]

def sha(p):
    h = hashlib.sha256()
    with open(p, "rb") as f:
        for b in iter(lambda: f.read(1 << 20), b""): h.update(b)
    return h.hexdigest()
def head(repo):
    try: return subprocess.check_output(["git", "-C", SRC[repo], "rev-parse", "HEAD"], text=True).strip()
    except Exception: return "not a git repository"

if os.path.isdir(DST): shutil.rmtree(DST)
entries = []; missing = []
for repo, path, section, what in FILES:
    src = os.path.join(SRC[repo], *path.split("/"))
    if not os.path.isfile(src): missing.append((repo, path)); continue
    dst = os.path.join(DST, repo, *path.split("/")); os.makedirs(os.path.dirname(dst), exist_ok=True); shutil.copyfile(src, dst)
    entries.append({"repo": repo, "path": path, "spec_section": section, "verify": what, "sha256": sha(dst), "bytes": os.path.getsize(dst)})
manifest = {"purpose": "Reference logic the specification cites, copied verbatim from Dupleix's own repositories so that the completeness of spec v4 can be verified against working code. Code only; no client data.",
            "assembled": datetime.date.today().isoformat(),
            "sources": {k: {"path": v, "commit": head(k)} for k, v in SRC.items()}, "files": entries}
json.dump(manifest, open(os.path.join(DST, "manifest.json"), "w", encoding="utf-8"), indent=2)
by = {}
for e in entries:
    for sec in [x.strip() for x in e["spec_section"].split(",")]:
        by.setdefault(sec, []).append(e)
def key(sec):
    parts = sec.replace("section ", "").split()[0].split(".")
    return tuple(int(x) if x.isdigit() else 0 for x in parts)
lines = ["# Reference logic for verifying spec v4", "",
         "Verbatim copies of the files the specification (`docs/MAIIC_EIR_Engine_Specification_v4_2026-10-07.md`) cites as the source of a design, taken from Dupleix's own repositories at the commits in `manifest.json`. They are here so that a reviewer can check each section of the specification against the code it was written from, and so that the build can port rather than reinvent. Code only: no client data, no logos, no documents of another client.", "",
         "| Source | Repository | Commit |", "|---|---|---|"]
for k, v in manifest["sources"].items(): lines.append(f"| `{k}` | `{v['path']}` | `{v['commit'][:12]}` |")
lines += ["", "## By specification section", ""]
for sec in sorted(by, key=key):
    lines.append(f"### Section {sec}"); lines.append(""); lines.append("| File | What to verify |"); lines.append("|---|---|")
    for e in by[sec]: lines.append(f"| `{e['repo']}/{e['path']}` | {e['verify']} |")
    lines.append("")
open(os.path.join(DST, "README.md"), "w", encoding="utf-8").write("\n".join(lines))
print(len(entries), "files copied;", round(sum(e["bytes"] for e in entries)/1e6, 2), "MB; missing:", missing)
