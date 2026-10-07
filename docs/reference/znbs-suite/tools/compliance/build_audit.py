"""Build a directive compliance audit (Excel + Markdown + PDF) from its data module.

    python tools/compliance/build_audit.py notice_1197_market_risk [more modules...]
    python tools/compliance/build_audit.py --all

Writes to docs/final specs/Directive Compliance Audits/<META.file_stem>.{xlsx,md,pdf}.
"""
import importlib
import os
import subprocess
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
sys.path.insert(0, ROOT)

from tools.compliance.audit_workbook import build_markdown, build_xlsx  # noqa: E402

OUT_DIR = os.path.join(ROOT, "docs", "final specs", "Directive Compliance Audits")
DIRECTIVES = os.path.join(ROOT, "tools", "compliance", "directives")


def build(name):
    mod = importlib.import_module(f"tools.compliance.directives.{name}")
    stem = os.path.join(OUT_DIR, mod.META["file_stem"])
    build_xlsx(mod.META, mod.ROWS, mod.FINDINGS, stem + ".xlsx")
    build_markdown(mod.META, mod.ROWS, mod.FINDINGS, stem + ".md")
    subprocess.run([sys.executable, os.path.join(ROOT, "tools", "compliance", "md_to_pdf.py"), stem + ".md", stem + ".pdf"], check=True)
    counts = {}
    for r in mod.ROWS:
        counts[r[4]] = counts.get(r[4], 0) + 1
    print(f"{name}: {len(mod.ROWS)} sections {counts} | findings {len(mod.FINDINGS)}")


if __name__ == "__main__":
    os.makedirs(OUT_DIR, exist_ok=True)
    names = sys.argv[1:]
    if names == ["--all"]:
        names = sorted(f[:-3] for f in os.listdir(DIRECTIVES) if f.endswith(".py") and not f.startswith("_"))
    for n in names:
        build(n)
