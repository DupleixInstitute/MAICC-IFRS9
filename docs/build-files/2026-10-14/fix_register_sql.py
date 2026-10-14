"""Correct the register description: today it holds each query's shape and version; the SQL text is in the committed query files; SQL text and hash in the register come with the route 4 build."""
import shutil, subprocess, sys
from pathlib import Path
DIR = Path(r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\3. Project Execution\E-Banker Integration")
REPO = Path(r"C:\xampp\htdocs\MAICC-IFRS9")
TECH = DIR / "Data access request - E-Banker read-only access (route 4) - 14 Oct 2026.md"
PLAIN = DIR / "Plain-language guide - connecting the IFRS 9 system to E-Banker - 14 Oct 2026.md"

def fix(path, pairs):
    t = path.read_text(encoding="utf-8")
    for a, b in pairs:
        assert t.count(a) == 1, ("anchor not found once", path.name, a[:80])
        t = t.replace(a, b)
    path.write_text(t, encoding="utf-8")

fix(TECH, [
    ("| What exactly did we ask E-Banker for, and has the question changed? | The register: the query, its version and the fingerprint of its SQL |",
     "| What exactly did we ask E-Banker for, and has the question changed? | The register gives each query and its version; the exact SQL is the committed query file of that version (section 4.2) |"),
    ("(2) It will bind the watermark as a parameter rather than pasting it into the text, so the statement text, and therefore its fingerprint, stays exactly as registered.",
     "(2) It will bind the watermark as a parameter rather than pasting it into the text, so the statement text, and therefore its fingerprint, stays exactly as approved."),
    ("**What the register is.** The register (`ebanker_queries`) is the system's controlled catalogue of every statement it may run against E-Banker. After pack 10 it has **114 entries**. It is seeded from the code repository, so it changes only through the release steps in section 2.2. Each entry records the following:",
     "**What the register is.** The register (`ebanker_queries`) is the system's controlled catalogue of every query it accepts from E-Banker. After pack 10 it has **114 entries**. It is seeded from the code repository (`database/seeders/EbankerQuerySeeder.php`), so it changes only through the release steps in section 2.2. Today each entry records the query's **shape**: what it reads, its key, its account and date columns, how it is read, and its version. The **SQL text itself** is kept, by version, in the committed query files under `docs/bootstrap/queries`, which are what the pack sheets print and what the E-Banker Feed screen hands out as one download. Each entry records the following:"),
    ("| `sql`, `sha256` | The statement and its SHA-256 | The statement route 4 will execute. The hash proves that a hand-run file came from the registered text |",
     "| `sql`, `sha256` | Empty today | Filled by the route 4 build: the statement route 4 will execute, and its SHA-256, so the system runs exactly the approved text and any change to it shows |"),
    ("Under route 1, the register's statements are what the pack sheets print for your team to run in SQL Developer.",
     "Under route 1, the committed query files are what the pack sheets print for your team to run in SQL Developer."),
    ("- **Registered statements only.** The runner will execute only statements from the register (section 4.2) whose SQL fingerprint matches the registered one. It cannot run a statement typed in by hand.",
     "- **Registered statements only.** The route 4 build will store each approved statement and its fingerprint in the register (section 4.2). The runner will execute only those statements, and only when the fingerprint still matches. It cannot run a statement typed in by hand."),
    ("It also records the account and date columns, a version number, the SQL text and its hash, and whether the table is read incrementally or whole. The register is seeded from the repository, so a query is changed by changing the repository, not by editing a worksheet.",
     "It also records the account and date columns, a version number, and whether the table is read incrementally or whole. The SQL text of each version is kept in the committed query files under `docs/bootstrap/queries`; the route 4 build will also store it, with its hash, in the register. Both are in the repository, so a query is changed by changing the repository, not by editing a worksheet."),
    ("If a file's version is not the one the register holds, the `query_version` gate refuses it, so an old script cannot feed the system unnoticed. The SQL hash lets anyone confirm that the script MAIIC ran is the one in the register.",
     "If a file's version is not the one the register holds, the `query_version` gate refuses it, so an old script cannot feed the system unnoticed. What the gate checks today is the query's identifier and version as stated in the manifest, not the SQL text itself. Under route 1 the SQL run is the committed file for that version; once the route 4 build stores each statement's hash in the register, the system runs and checks the exact text itself."),
])

fix(PLAIN, [
    ("| What exactly did we ask E-Banker for? | The register, which shows each question with its version and fingerprint |",
     "| What exactly did we ask E-Banker for? | The register, which shows each question and its version; the exact wording is in the approved query file for that version |"),
    ("**What the register is.** The register is the IFRS 9 system's controlled list of **every SQL question it is allowed to ask E-Banker**. After the tenth batch there are 114. Each entry is like a library card for one question.",
     "**What the register is.** The register is the IFRS 9 system's controlled list of **every SQL question it accepts answers to from E-Banker**. After the tenth batch there are 114. Each entry is like a library card for one question. Today the card describes the question and its version; the exact wording of each version is kept in the approved query files that are part of the system's code."),
    ("| The SQL text and its fingerprint | The exact question, and a code calculated from it | It proves that the question being run is the approved one |",
     "| The SQL text and its fingerprint | Not on the card yet: today the exact question is in the approved query file | When the direct read (way 4) is built, the exact question and a code calculated from it go on the card, so the system runs only the approved wording and any change shows |"),
    ("The fingerprint lets anyone confirm that the SQL run at MAIIC is the SQL in the register.",
     "Today the check is on the question's name and version; the exact wording MAIIC ICT runs is the approved query file for that version."),
    ("Under the hand method, the register's questions are what gets printed on each batch sheet for MAIIC ICT to run.",
     "Under the hand method, the approved query files are what gets printed on each batch sheet for MAIIC ICT to run."),
    ("Every question the system asks E-Banker is written down in the register (section 4.2), with a version number and a fingerprint.",
     "Every question the system asks E-Banker is written down in the register (section 4.2), with a version number, and its exact wording is kept in an approved query file."),
])
print("both documents corrected")
for f in (TECH, PLAIN):
    r = subprocess.run([sys.executable, str(REPO / "docs/build-files/spec_render.py"), str(f)], capture_output=True, text=True)
    print(r.stdout.strip().splitlines()[-1] if r.stdout else r.stderr[-600:])
    for ext in (".md", ".pdf", ".docx"):
        shutil.copy(f.with_suffix(ext), REPO / "docs/bootstrap/queries" / f.with_suffix(ext).name)
print("copied to repo")
