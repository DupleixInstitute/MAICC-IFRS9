# Compliance audit workbooks (spec v4 section 12)

One engine, ported from the Dupleix suite, builds each standard's workbook in four files from one data module, so they can never say different things.

```
python tools/compliance/build_audit.py --all --env=bootstrap
python tools/compliance/build_audit.py ifrs9_eir
DB_DATABASE=maiic_ifrs9_results2 python tools/compliance/build_audit.py --all
```

The last form reads the Baselines from another database without touching `.env` (there is no config cache in `bootstrap/cache`). `eir:baselines` only reads; it is run with `php -d memory_limit=-1` because the whole-ledger read passes the CLI's 1 GB limit.

- `audit_workbook.py` - the engine: Contents, Audit (eleven columns: the suite's nine, then the governance setting and the test that proves it), Findings, Baselines (the section 9 ties read from the system through `php artisan eir:baselines --json`, with a live PASS/FAIL formula). The Markdown names the database the Baselines were read from.
- `build_audit.py` - validates and writes `docs/compliance/<file_stem>.{xlsx,md,pdf,json}`. A build stops when:
  - a row lacks one of its eleven fields, has an unknown status or a duplicate reference, is Not applicable without a reason, or is Done without a test;
  - a test named in column 11 does not exist, or a test named `Class::method` does not declare that method;
  - a governance setting named in column 10 is not a key of `GovernanceService::catalogue()` (read from the PHP source; text in brackets is a note and is not checked, so `cash_source (not read)` passes and `day_count_basis` does not).
- `standards/` - one module per workbook exposing `META`, `ROWS`, `FINDINGS`, `BASELINES`: `ifrs9_eir` (the EIR and amortised cost), `ifrs9_impairment`, `ifrs7_ias1_disclosure`, `rbm_dfi_directive` (keyed to the Gazette's paragraphs), `contract_schedule1`.

Requires the Python environment with `openpyxl` and `reportlab` (the same one that renders the specifications).

The builder also writes `<file_stem>.json`, the source the register loads (`php artisan compliance:audits --load`), and reads `<file_stem>.signed.json` back (`--export-signed`) so a regenerated workbook carries the register's signed statuses. `php artisan compliance:audits --pack=YYYY-MM` builds the auditor's pack.

Last reviewed 14 October 2026: every row re-checked against the code at commit abe140d and the fresh bootstrap `maiic_ifrs9_results2`.
