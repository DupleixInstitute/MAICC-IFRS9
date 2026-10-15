p = 'docs/manuals/technical/02-architecture.md'
s = open(p, encoding='utf-8').read()
def rep(a, b):
    global s
    assert s.count(a) == 1, a[:60]
    s = s.replace(a, b)
rep("| `app/Services` | Calculation and import services: `Ecl/`, `Eir/`, `Imports/`, `Reports/`, transition matrix and macro services |",
    "| `app/Services` | Calculation and import services, one folder per area: `Ebanker/` (landing zone, packs, take-on, loan book build, write-off classifier, GL fee route), `Eir/`, `Ecl/`, `Pd/`, `Lgd/`, `Ifrs9/`, `Fli/`, `Scenario/`, `Macro/`, `MegaFarm/`, `Rbm/`, `Reports/`, `Stress/`, `Compliance/`, `Maintenance/`, `Imports/`, plus the transition matrix and regression services |")
rep("| `config/menu.php` | The navigation tree |",
    "| `app/Support` | Shared helpers: `Breadcrumbs` and `SectionTabCounts` (navigation), `ReportingPeriodLock` (the period lock every writer checks), `LedgerReadLimits`, `ReportDownload`, `Tenor`, and `Imports/` (`ImportSpecs`, `ImportSpecValidator`, `ImportTemplateBuilder`: the sample file, column guide and fail-closed check of every import) |\n| `config/menu.php` | The navigation tree |")
rep("| `scripts/` | Deployment script and the puppeteer capture scripts |",
    "| `scripts/` | `deploy.sh` (production deployment), `manual-screenshots.cjs` (the headless capture behind `manual:screenshots`) and the EIR explainer document generator |")
rep("| Customer & Loan Data | `ClientsController`, `LoanBookController`, `ImportsController`, `GeneralImportController`, `EirFeedController`, `EirTakeonController` | `Client`, `LoanBook`, `Import` | `Clients/`, `LoanBooks/`, `Imports/` |",
    "| Customer & Loan Data | `ClientsController`, `LoanBookController`, `ImportsController`, `GeneralImportController`, `ImportTemplateController`, `ImportSampleController`, `EirFeedController`, `EirTakeonController` | `Client`, `LoanBook`, `Import`; the landing-zone tables are read and written by the services in `app/Services/Ebanker` | `Clients/`, `LoanBooks/`, `Imports/`, `Eir/Feed/`, `Eir/Takeon/` |")
rep("`EirDrawdownController`, `EirReferenceRateController`, `EirAsAtController`, `EirGovernanceController` | `ContractEir`, `ContractCashflowSchedule`, `ContractRemainingCashflowSchedule`, `ContractFee`, `EirAccountingRule`, `EirAmortisation`, `GlInterestPosting`, `GlTrialBalanceLine`, `GlAccountScope` | `Eir/` |",
    "`EirDrawdownController`, `EirReferenceRateController`, `EirTrialBalanceController`, `EirWriteOffController`, `EirRestructureController`, `EirAsAtController` | `ContractEir`, `ContractCashflowSchedule`, `ContractRemainingCashflowSchedule`, `ContractFee`, `EirAccountingRule`, `EirAmortisation`, `GlInterestPosting`, `GlTrialBalanceLine`, `GlAccountScope`, `Scheme`; the write-off, restructure and rate-reset registers through their services in `app/Services/Eir` | `Eir/` |")
rep("| IFRS 9 Model Setup | `StageingRulesController`,", "| IFRS 9 Model Setup | `StageingRulesController`, `StagingThresholdController`,")
rep("| Portfolio Setup | `LoanPortfoliosController`, `IndustryTypesController`, `LoanProductGroupsController` |",
    "| Portfolio Setup | `LoanPortfoliosController`, `LoanProductCategoriesController` (Product Groups), `IndustryTypesController` |")
rep("| Reports | `Reports/Ifrs9ReportsController`, `Reports/StressTestingController`, `ReportsController` |",
    "| Reports | `Reports/Ifrs9ReportsController`, `Reports/StressTestingController`, `ReportsController`, `RbmReturnController`, `AuditorPackController` |")
rep("| Administration | `UsersController`, `RolesController`, `FinancialPeriodController`, `AuditTrailController`, `AuditTraceController`, `ComplianceAuditController`, `TicketsController`, `SettingsController`, `LicenseController`, `MaintenanceController` |",
    "| Governance, Access & Setup, Support & Maintenance (Administration & support) | `EirGovernanceController`, `AuditTrailController`, `AuditTraceController`, `ComplianceAuditController`, `UsersController`, `RolesController`, `FinancialPeriodController`, `SettingsController`, `TicketsController`, `MaintenanceController`, `LicenseController` |")
rep("| `app/Http/Controllers` | One controller per screen family. IFRS 9 reports sit under `Reports/`. EIR controllers are prefixed `Eir`. |",
    "| `app/Http/Controllers` | One controller per screen family. IFRS 9 reports sit under `Reports/`. EIR and E-Banker feed controllers are prefixed `Eir`. |")
open(p, 'w', encoding='utf-8', newline='\n').write(s)
