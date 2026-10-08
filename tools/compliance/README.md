# Compliance audit workbooks (spec v4 section 12)

One engine, ported from the Dupleix suite, builds each standard's workbook in three formats from one data module, so the three can never say different things.

```
python tools/compliance/build_audit.py --all --env=bootstrap
python tools/compliance/build_audit.py ifrs9_eir
```

- `audit_workbook.py` — the engine: Contents, Audit (eleven columns: the suite's nine, then the governance setting and the test that proves it), Findings, Baselines (the section 9 ties read from the system through `php artisan eir:baselines --json`, with a live PASS/FAIL formula).
- `build_audit.py` — validates (eleven fields, a known status, no duplicate reference, a reason on every Not applicable, a test on every Done, every test named exists) and writes `docs/compliance/<file_stem>.{xlsx,md,pdf}`.
- `standards/` — one module per workbook exposing `META`, `ROWS`, `FINDINGS`, `BASELINES`: `ifrs9_eir` (the EIR and amortised cost), `ifrs9_impairment`. Still to draft: IFRS 7 / IAS 1 disclosure, the RBM directive, Contract Schedule 1.

Requires the Python environment with `openpyxl` and `reportlab` (the same one that renders the specifications).
