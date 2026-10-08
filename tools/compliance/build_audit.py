"""Build a compliance audit workbook (Excel + Markdown + PDF) from its data module (spec v4 section 12).

    python tools/compliance/build_audit.py ifrs9_eir [more modules...]
    python tools/compliance/build_audit.py --all
    python tools/compliance/build_audit.py ifrs9_eir --env=bootstrap     which database the Baselines sheet reads

Writes to docs/compliance/<META.file_stem>.{xlsx,md,pdf}. The Baselines sheet is read from the
system at generation through `php artisan eir:baselines --json`; a module's own BASELINES list
is appended to it.
"""
import importlib
import json
import os
import subprocess
import sys
from datetime import datetime

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
sys.path.insert(0, ROOT)

from tools.compliance.audit_workbook import build_markdown, build_xlsx  # noqa: E402

OUT_DIR = os.path.join(ROOT, "docs", "compliance")
STANDARDS = os.path.join(ROOT, "tools", "compliance", "standards")


def system_baselines(env):
    """The section 9 ties as the system computes them now; empty, with a note, when it cannot run."""
    cmd = ["php", "artisan"] + ([f"--env={env}"] if env else []) + ["eir:baselines", "--json"]
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


def check_tests_exist(rows):
    r"""Spec 12.7 (4): every test named in column 11 exists. A class is Tests\Feature\...; a method may follow '::'."""
    import re
    missing = []
    for r in rows:
        for token in re.findall(r"Tests(?:\\[A-Za-z0-9_]+)+", r[10] or ""):
            path = os.path.join(ROOT, "tests", *token.split("\\")[1:]) + ".php"
            if not os.path.isfile(path):
                missing.append(f"{r[1]}: {token}")
    assert not missing, "tests named that do not exist: " + "; ".join(missing)


def build(name, env=None):
    mod = importlib.import_module(f"tools.compliance.standards.{name}")
    check_tests_exist(mod.ROWS)
    sysb = system_baselines(env)
    meta = dict(mod.META, generated_at=sysb["generated_at"], database=sysb["database"])
    baselines = sysb["checks"] + list(getattr(mod, "BASELINES", []))
    stem = os.path.join(OUT_DIR, meta["file_stem"])
    build_xlsx(meta, mod.ROWS, mod.FINDINGS, baselines, stem + ".xlsx")
    build_markdown(meta, mod.ROWS, mod.FINDINGS, baselines, stem + ".md")
    subprocess.run([sys.executable, os.path.join(ROOT, "tools", "compliance", "md_to_pdf.py"), stem + ".md", stem + ".pdf"], check=True)
    counts = {}
    for r in mod.ROWS:
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
