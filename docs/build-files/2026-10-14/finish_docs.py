import shutil, subprocess, sys, importlib.util
from pathlib import Path
SCR = Path(__file__).parent
spec = importlib.util.spec_from_file_location("rs", SCR / "register_sections.py"); rs = importlib.util.module_from_spec(spec); spec.loader.exec_module(rs)
DIR = Path(r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\3. Project Execution\E-Banker Integration")
REPO = Path(r"C:\xampp\htdocs\MAICC-IFRS9")
TECH = DIR / "Data access request - E-Banker read-only access (route 4) - 14 Oct 2026.md"
PLAIN = DIR / "Plain-language guide - connecting the IFRS 9 system to E-Banker - 14 Oct 2026.md"

p = PLAIN.read_text(encoding="utf-8")
assert "### 4.2 What the register is" in p or "@@REGISTER@@\n" in p
p = p.replace("@@REGISTER@@\n", rs.PLAIN)
PLAIN.write_text(p, encoding="utf-8")

t = TECH.read_text(encoding="utf-8")
if "### 4.2 The query register" not in t:
    anchor = "## 5. What the account will do, and will not do"
    assert anchor in t
    t = t.replace(anchor, rs.TECH + anchor, 1)
# the register is mentioned in 4.1; point to 4.2
t = t.replace("1. **The register** (`ebanker_queries`) is the list of everything the system is allowed to ask E-Banker: about sixty questions,",
              "1. **The register** (`ebanker_queries`, section 4.2) is the list of everything the system is allowed to ask E-Banker: 114 questions after pack 10,")
TECH.write_text(t, encoding="utf-8")
assert "about sixty" not in t, "stale count left"
assert t.count("Barry") == 1 and "Barry" not in p

for f in (TECH, PLAIN):
    r = subprocess.run([sys.executable, str(REPO / "docs/build-files/spec_render.py"), str(f)], capture_output=True, text=True)
    print(r.stdout.strip().splitlines()[-1] if r.stdout else r.stderr[-800:])
q = REPO / "docs/bootstrap/queries"
for name in [TECH.name, PLAIN.name, "Dupleix logo.png", "Feed routes and landing zone - diagram.png", "Route 4 - proposed build and test timetable - gantt.png"]:
    shutil.copy(DIR / name, q / name)
print("copied to repo")
