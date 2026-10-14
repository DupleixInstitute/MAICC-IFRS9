"""Build a compliance audit workbook (Excel + Markdown + PDF) from its data module (spec v4 section 12).

    python tools/compliance/build_audit.py ifrs9_eir [more modules...]
    python tools/compliance/build_audit.py --all
    python tools/compliance/build_audit.py ifrs9_eir --env=bootstrap     which database the Baselines sheet reads

Writes to docs/compliance/<META.file_stem>.{xlsx,md,pdf,json}. The Baselines sheet is read from the
system at generation through `php artisan eir:baselines --json`; a module's own BASELINES list
is appended to it. To read another database without touching .env (there is no config cache):

    DB_DATABASE=maiic_ifrs9_results2 python tools/compliance/build_audit.py --all

Before anything is written the rows are checked, and a failure stops the build:
  * every test named in column 11 exists, and a test named Class::method declares that method;
  * every governance setting named in column 10 is a key of GovernanceService::catalogue()
    (text in brackets is a note and is not checked).
"""
import importlib
import json
import os
import re
import subprocess
import sys
from datetime import datetime

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
sys.path.insert(0, ROOT)

from tools.compliance.audit_workbook import build_markdown, build_xlsx  # noqa: E402

OUT_DIR = os.path.join(ROOT, "docs", "compliance")
STANDARDS = os.path.join(ROOT, "tools", "compliance", "standards")
GOVERNANCE_SOURCE = os.path.join(ROOT, "app", "Services", "Eir", "GovernanceService.php")


def system_baselines(env):
    """The section 9 ties as the system computes them now; empty, with a note, when it cannot run."""
    # the whole-ledger read needs more than the CLI's 1 GB memory limit (it stopped there on 14 October 2026)
    cmd = ["php", "-d", "memory_limit=-1", "artisan"] + ([f"--env={env}"] if env else []) + ["eir:baselines", "--json"]
    try:
        out = subprocess.run(cmd, cwd=ROOT, capture_output=True, text=True, timeout=600).stdout
        start = out.find("{")
        data = json.loads(out[start:]) if start >= 0 else {}
    except Exception as e:  # noqa: BLE001
        return {"database": f"not read ({e})", "generated_at": datetime.now().strftime("%Y-%m-%d %H:%M"), "checks": []}
    rows = []
    for c in data.get("checks", []):
        exp = c["expected_value"] if c.get("expected_value") is not None else c["expected"]
        act = c["actual_value"] if c.get("actual_value") is not None else c["actual"]
        rows.append({"what": c["what"], "expected": exp, "actual": act, "ok": c["ok"], "tolerance": 1.0 if isinstance(exp, float) and exp > 1e6 else 0.01,
                     "note": "" if c["ok"] else "the bootstrap's verify reports this row"})
    return {"database": data.get("database", "?"), "generated_at": data.get("generated_at", ""), "checks": rows}


TEST_TOKEN = re.compile(r"(Tests(?:\\[A-Za-z0-9_]+)+)(?:::([A-Za-z0-9_]+))?")


def check_tests_exist(rows):
    r"""Spec 12.7 (4): every test named in column 11 exists. A class is Tests\Feature\... or Tests\Unit\...;
    when a method follows '::' the class file must declare it."""
    missing = []
    for r in rows:
        for token, method in TEST_TOKEN.findall(r[10] or ""):
            path = os.path.join(ROOT, "tests", *token.split("\\")[1:]) + ".php"
            if not os.path.isfile(path):
                missing.append(f"{r[1]}: {token}")
                continue
            if method:
                with open(path, encoding="utf-8") as fh:
                    src = fh.read()
                if not re.search(r"function\s+" + re.escape(method) + r"\s*\(", src):
                    missing.append(f"{r[1]}: {token}::{method}")
    assert not missing, "tests named that do not exist: " + "; ".join(missing)


def catalogue_keys():
    """The setting keys of GovernanceService::catalogue(), read from the PHP source (no database needed)."""
    with open(GOVERNANCE_SOURCE, encoding="utf-8") as fh:
        src = fh.read()
    body = src[src.index("function catalogue()"):src.index("function keys()")]
    keys = set(re.findall(r"^ {12}'([a-z0-9_]+)'\s*=>\s*\[", body, re.M))
    assert keys, "no catalogue keys found in " + GOVERNANCE_SOURCE
    return keys


def check_governance_keys(rows):
    """Every governance setting named in column 10 is a catalogue key. Column 10 is a ';'-separated
    list; anything in brackets is a note, so '(section 4.2)' or 'cash_source (not read)' pass."""
    keys = catalogue_keys()
    wrong = []
    for r in rows:
        for part in (r[9] or "").split(";"):
            name = re.sub(r"\([^)]*\)", "", part).strip()
            if name and name not in keys:
                wrong.append(f"{r[1]}: {name!r}")
    assert not wrong, f"governance settings named that are not in the catalogue of {len(keys)} keys: " + "; ".join(wrong)


def build(name, env=None):
    mod = importlib.import_module(f"tools.compliance.standards.{name}")
    check_tests_exist(mod.ROWS)
    check_governance_keys(mod.ROWS)
    sysb = system_baselines(env)
    meta = dict(mod.META, generated_at=sysb["generated_at"], database=sysb["database"])
    baselines = sysb["checks"] + list(getattr(mod, "BASELINES", []))
    stem = os.path.join(OUT_DIR, meta["file_stem"])
    # the signed state from the register (php artisan compliance:export-signed) overrides a row's status and sign-off
    signed_path = stem + ".signed.json"
    rows = list(mod.ROWS)
    if os.path.isfile(signed_path):
        signed = {r["reference"]: r for r in json.load(open(signed_path, encoding="utf-8")).get("rows", [])}
        rows = [tuple(list(r[:4]) + [signed[r[1]]["status"]] + list(r[5:])) if r[1] in signed and signed[r[1]].get("status") else r for r in rows]
    # the same rows as JSON, the source the register's seeder reads
    with open(stem + ".json", "w", encoding="utf-8") as fh:
        json.dump({"key": name, "meta": mod.META, "rows": [dict(zip(["part", "reference", "section_name", "requirement", "status", "engine_comment", "general_comment", "compliance_comment", "where_to_see", "governance_setting", "test"], r)) for r in rows],
                   "findings": [dict(zip(["number", "reference", "finding", "what_was_found", "impact", "recommended_action", "owner", "status"], f)) for f in mod.FINDINGS]}, fh, indent=1, ensure_ascii=False)
    build_xlsx(meta, rows, mod.FINDINGS, baselines, stem + ".xlsx")
    build_markdown(meta, rows, mod.FINDINGS, baselines, stem + ".md")
    subprocess.run([sys.executable, os.path.join(ROOT, "tools", "compliance", "md_to_pdf.py"), stem + ".md", stem + ".pdf"], check=True)
    counts = {}
    for r in rows:
        counts[r[4]] = counts.get(r[4], 0) + 1
    passing = sum(1 for b in baselines if b.get("ok"))
    print(f"{name}: {len(mod.ROWS)} sections {counts} | findings {len(mod.FINDINGS)} | baselines {passing}/{len(baselines)} PASS ({sysb['database']})")


if __name__ == "__main__":
    os.makedirs(OUT_DIR, exist_ok=True)
    args = sys.argv[1:]
    env = next((a.split("=", 1)[1] for a in args if a.startswith("--env=")), None)
    names = [a for a in args if not a.startswith("--env=")]
    if names == ["--all"] or names == []:
        names = sorted(f[:-3] for f in os.listdir(STANDARDS) if f.endswith(".py") and not f.startswith("_"))
    for n in names:
        build(n, env)
