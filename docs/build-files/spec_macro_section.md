## 13. Macro statistics: ingestion from the World Bank and the IMF

### 13.1 In plain language

The forward-looking part of IFRS 9 (B5.5.49 to B5.5.54) asks MAIIC to adjust its expected credit losses for what is reasonably expected to happen to the economy: growth, inflation, the exchange rate, interest rates, the harvest. Today those figures are typed into the system by hand from whatever source the analyst had open, with no record of where a number came from or when. The Dupleix suite does this differently: the system fetches the published series itself from the World Bank's open data service and the IMF's World Economic Outlook, shows the analyst what it found, and writes it only when the analyst says so, with the source, the address and the time kept against every figure. Decision D27 adopts that for MAIIC.

### 13.2 What MAIIC has today

The module exists and its tables are sound. `macro_statistics` holds the definition of each series (code, name, unit, frequency, how many historical and forecast periods, source, website link, active flag); `macro_statistics_data` holds one value per series, period and scenario, with an actual-or-forecast flag, an FLI flag, confidence bounds, assumptions and a free-text source, unique on series, period and scenario. Six screens use them under IFRS 9 Model Setup: Macro Elements (manual entry), Scenario Profiles, Weighted Forecast, Credit Loss Data, Adjusted Forecast and Regression Analysis, and the Management Overlays group holds the economic scenarios and external calculations.

What is missing is everything before the first screen: no external source, no preview before a write, no record of where a value came from, no command a bootstrap or a scheduler could run, and no rule for what happens when two sources disagree. The scenario-on-the-row design is in one respect ahead of the suite's and is kept.

### 13.3 What the suite does, and is adopted

| Element | What it does | MAIIC adoption |
|---|---|---|
| **Indicator codes on the series** | Each macro variable carries the external codes that identify it at each source (`external_codes`, for example `{"world_bank": "NY.GDP.MKTP.KD.ZG"}`), seeded once. A series with no code says so on the screen; it is not an error | Two columns added to `macro_statistics`: `external_codes` (JSON) and `country` (ISO3, default `MWI`); a seeder with the Malawi codes of 13.4 |
| **World Bank fetcher** | Pulls an annual series for a country from `api.worldbank.org/v2/country/{ISO3}/indicator/{code}`: public, no key, 60-second timeout, two retries, year range optional, country from configuration with a screen override. Returns a normalised preview; writes nothing | `WorldBankFetcherService` ported as it is; `services.worldbank.country = MWI` |
| **IMF WEO parser** | Reads the World Economic Outlook download (a tab-delimited UTF-16 text file despite its `.xls` name), filters to the country and indicator, and marks each year actual or forecast from the "Estimates Start After" column. This is where the forecast periods come from | `ImfWeoParserService` ported; the file is uploaded by hand from the IMF site, which has no key-free API |
| **Preview, then commit** | Two endpoints per source: a preview the viewer may call, and a commit that writes, for the manager only. Nothing reaches the table until a person has seen the rows | Routes `macro-statistics.preview-worldbank`, `.import-worldbank`, `.preview-imf-weo`, `.import-imf-weo`; permissions `macro.view` and `macro.manage` added to the role set |
| **Provenance per import** | Every commit is a batch: source, address, fetched-at, who, how many rows, the file's hash where there is one; every observation points to its batch | `macro_source_import_batches`; `source_import_batch_id` on `macro_statistics_data`; the free-text `source` kept for manual entries |
| **Upsert, never duplicate** | Observations are keyed on series, period and period type, so a refresh updates the value and keeps the key | MAIIC's unique key on series, period and scenario already does this; an API import writes to the base scenario |
| **A command** | `macro:import-worldbank {--code=}` imports every series with a code, non-fatal per series, for the bootstrap and the scheduler | The same command; run by `eir:bootstrap` and monthly by the scheduler; a committed JSON snapshot under `docs/bootstrap/macro/` is the offline fallback so a server without internet still bootstraps |
| **The screen** | One page with five tabs: Dashboard, Variables, Data Entry, governed assumptions, Import / Export (World Bank with country and year range, IMF upload, CSV template and export) | Data Foundation, Macro Statistics (section 11.3), five tabs; the governed-assumptions tab is **Scenario Assumptions**, the base, mild and severe paths that feed Scenario Profiles and the Weighted Forecast, with the same approval lineage |

Two things the suite does not have are added for MAIIC. **Reserve Bank of Malawi series by file**: the policy rate and the prime lending rate are not on the World Bank; the PLR is already in the landing zone (`P2_06`) and the policy rate arrives as a small CSV through the same preview-and-commit screen, one more source tab rather than a special case. **A rule for disagreement**: a Governance Centre setting, `macro_source_precedence`, says which source wins where two overlap.

### 13.4 The Malawi series, seeded

| Code | Series | World Bank indicator | Why MAIIC needs it |
|---|---|---|---|
| GDP_GROWTH | Real GDP growth, annual % | `NY.GDP.MKTP.KD.ZG` | The headline driver of default rates |
| CPI | Inflation, consumer prices, annual % | `FP.CPI.TOTL.ZG` | Real income of borrowers; the policy-rate path |
| MWK_USD | Official exchange rate, MWK per USD | `PA.NUS.FCRF` | Import-dependent borrowers; the industrial book |
| LENDING_RATE | Lending interest rate, % | `FR.INR.LEND` | Debt service burden; cross-check to the PLR series |
| REAL_RATE | Real interest rate, % | `FR.INR.RINR` | Affordability after inflation |
| PRIVATE_CREDIT | Domestic credit to private sector, % of GDP | `FS.AST.PRVT.GD.ZS` | Credit conditions |
| AGRI_GROWTH | Agriculture, forestry and fishing value added, annual growth % | `NV.AGR.TOTL.KD.ZG` | MAIIC's book is agricultural and agro-industrial; the harvest is its own driver |
| BROAD_MONEY | Broad money growth, annual % | `FM.LBL.BMNY.ZG` | Liquidity conditions |
| RESERVES | Total reserves in months of imports | `FI.RES.TOTL.MO` | Exchange-rate and import-cover stress |
| DEBT_GDP | Central government debt, % of GDP | `GC.DOD.TOTL.GD.ZS` | Sovereign stress and crowding out |
| CURRENT_ACCOUNT | Current account balance, % of GDP | `BN.CAB.XOKA.GD.ZS` | External balance |
| POLICY_RATE | RBM policy rate, % | by file (RBM) | The rate the PLR follows |
| PLR | Prime lending rate, % | the landing zone, `P2_06` | The floating-rate reference of the EIR engine, so the ECL and EIR modules read one series |

The IMF WEO gives the forecast years for GDP growth, inflation, the current account and government debt; the World Bank gives the actuals. A code that the World Bank later retires is a seeder change, not a code change.

### 13.5 Behaviour, stated precisely

- **Country.** `MWI` by default; the screen may fetch another country for comparison, and such rows carry their country and are never used by the FLI model.
- **Periods.** World Bank and WEO series are annual; MAIIC's FLI model runs on the frequency the series definition states. An annual value is held as the year's observation; the Weighted Forecast interpolates to quarters or months as it does today, and says so on the row.
- **Actual against forecast.** A World Bank value is an actual. A WEO value is an actual up to the estimates-start year and a forecast after it; a later WEO vintage replaces an earlier forecast and the earlier one is kept in the batch history. A forecast is never overwritten by hand without a reason.
- **Precedence** (`macro_source_precedence`, section 4.2): World Bank for actuals, IMF WEO for forecasts, the RBM file for rates; a manual entry overrides any of them only with a reason, and the override is shown on the row and in the audit log.
- **Failure.** A series the source cannot supply is reported on the preview and skipped on the command; the import never fails as a whole because one indicator is missing. A fetch that cannot reach the source after the retries says so and leaves the table as it was.
- **Provenance to the audit workbook.** The IFRS 9 impairment workbook of section 12 cites, for B5.5.49 to B5.5.54, the batch behind each series in use: source, address, fetched-at, who committed it.

### 13.6 What changes in the code

| Change | Where |
|---|---|
| `external_codes`, `country` on `macro_statistics`; `macro_source_import_batches`; `source_import_batch_id` on `macro_statistics_data` | Two migrations |
| `WorldBankFetcherService`, `ImfWeoParserService`, `SourceImportBatch`, the RBM file parser | `app/Services/Macro`, `app/Models` |
| `MacroExternalCodesSeeder` with the series of 13.4 | `database/seeders` |
| Preview and commit endpoints for the three sources; `macro.view` and `macro.manage` | `MacroStatsController`, `routes/web.php`, the permission seeder |
| `macro:import-worldbank`; a step in `eir:bootstrap`; a monthly schedule; the JSON snapshot | `app/Console/Commands`, `routes/console.php`, `docs/bootstrap/macro/` |
| The Macro Statistics screen with five tabs in the suite layout | `resources/js/Pages/FLI/MacroStats` |
| `macro_source_precedence` in the Governance Centre catalogue | `GovernanceService::catalogue()` |
| Tests: the fetcher against a recorded response, the WEO parser against a sample file, preview writes nothing, commit writes a batch, the command skips a missing series | `tests/Feature/Macro` |

Not changed: Scenario Profiles, Weighted Forecast, Credit Loss Data, Adjusted Forecast and Regression Analysis read the same tables as before; the FLI model's arithmetic is untouched.

### 13.7 Acceptance

1. Every series of 13.4 with a World Bank code previews and imports for Malawi, and the rows carry a batch with source, address and time.
2. A WEO file previews with each year marked actual or forecast, and commits with the forecast years flagged.
3. A preview writes nothing; a commit by a viewer is refused; a commit by a manager is audit-logged.
4. `macro:import-worldbank` with the network unavailable reports every series skipped and leaves the tables unchanged; `eir:bootstrap` falls back to the snapshot.
5. The Weighted Forecast and Regression screens show the same results on imported data as on the same values typed by hand.
6. The impairment workbook's forward-looking rows cite the batches in use.

### 13.8 Order of work

MS-1 the migrations, the ported services, the seeder and the command, with tests (one day); MS-2 the screen in the suite layout, with the RBM file tab (one day); MS-3 the bootstrap step, the snapshot, the schedule and the audit-workbook rows (half a day). MS-1 can start with P4b; MS-2 is the first Data Foundation screen rebuilt in the suite shape and sits with UI-2.
