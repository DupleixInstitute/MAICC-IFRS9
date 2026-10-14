import re, shutil, subprocess, sys
from pathlib import Path
OLD = Path(r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\2. Documents from clients\Raw Query Scripts\Query Requests to MAIIC")
NEW = Path(r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\3. Project Execution\E-Banker Integration")
NAME = "Data access request - E-Banker read-only access (route 4) - 14 Oct 2026"
t = (OLD / f"{NAME}.md").read_text(encoding="utf-8")
print("before:", re.findall(r"section \d+", t))

t = t.replace("A script (SQLcl or SQL\\*Plus, written by Dupleix)", "A script (Oracle SQLcl, written by Dupleix)")
assert "SQL\\*Plus" not in t

# renumber: headings 4..11 -> 5..12 and in-text references to sections >= 4
for n in range(11, 3, -1):
    t = t.replace(f"\n## {n}. ", f"\n## {n+1}. ")
t = re.sub(r"\bsection (\d+)\b(?!\.\d)", lambda m: f"section {int(m.group(1)) + 1}" if int(m.group(1)) >= 4 else m.group(0), t)
print("after:", re.findall(r"section \d+", t))

landing = """## 4. The landing zone technique

**What it is.** A landing zone is a holding area inside the system where data from a source system is stored exactly as it arrived, before anything is calculated from it. Nothing in the IFRS 9 system reads E-Banker's files directly: every engine reads only what has passed through the landing zone. It is the single door between E-Banker and the system's figures, whichever of the five routes delivered the data.

**Why it is built this way.**

- **One set of checks for every route.** The checks are written once and every pack passes them, so a month that came by hand and a month read directly are held to the same standard.
- **Nothing is ever overwritten.** E-Banker changes its own records (a posting reversed, an account re-graded). If the system overwrote what it held, a figure reported last quarter could not be reproduced. The landing zone keeps every version of every row with the time it was superseded, so any reported month can be re-derived exactly as it was.
- **Bad data stops at the door.** A pack that fails a check is quarantined whole. Its rows are kept so the problem can be seen, but no engine reads them, so a short export or a wrong date format can never reach the ECL or the revenue figures.
- **The audit trail is built in.** For each figure the system can say which pack, which file, which query version and which row it came from, and show the hash proving the file is the one E-Banker produced.
- **Changing the route changes nothing downstream.** Because every route ends in the same pack and the same door, moving from the manual route to the direct read is invisible to the engines; it only removes the manual steps.

**How it works, step by step.**

1. **The pack arrives.** One CSV per registered query and a manifest stating, for each file, the query identifier and version, the row count, the SHA-256 hash and the date format. Under the direct read the system writes the pack and the manifest itself.
2. **The pack is identified.** The manifest's hash is checked against every pack already loaded. A pack already landed is reported as such and nothing is loaded twice.
3. **The gates run.** Each file: present, hash and row count as declared, key unique, query version the one in the register, every date and amount parses. Across the files: every ledger account is in the account master, the balance history ties to the ledger, every account in scope has its month-end row, the fee GL movement ties to the trial balance.
4. **Landed or quarantined.** If every error-level gate passes, the pack is LANDED; otherwise it is QUARANTINED with each failure named by file and row. Warnings are recorded but do not stop the pack.
5. **The rows are stored, versioned.** Each row is stored verbatim under the E-Banker table's own key with a hash of its content: a new key becomes version 1, an unchanged row is skipped, a changed row becomes the next version and the earlier one is marked superseded, never deleted.
6. **The watermark is recorded.** For each history table, the highest key loaded. The next read starts above it.
7. **The builds read.** The monthly loan book, the contract inputs, the fees and the trial balance lines are built from landed rows only, and each build records which loads it read. The engines (staging, PD and LGD, ECL, EIR and revenue, the GL reconciliation, the regulatory return) run on the builds.

The landing zone is four tables in the system's database: the **query register** (each query's identifier, source table, key, version, SQL and its hash); the **loads** (one record per pack: its hash, route, status, gate results and watermarks); the **pack files** (one record per file: hash, rows declared, rows new, versioned and unchanged); and the **raw rows** (every row of every file, all versions).

![Figure 1. The five feed routes and the landing zone. Every route produces the same pack; the landing zone checks it, stores it versioned, and only then do the builds and engines read it.](Feed routes and landing zone - diagram.png)

**How each route works, end to end.** The last two columns are the same for every route, which is the point of the design.

| Route | Who runs the queries | Where and when | How the pack travels | What starts the landing | Recorded in the load as | Checks and storage |
|---|---|---|---|---|---|---|
| **1. Manual pack** | Barry, in SQL Developer | His workstation, on request | CSVs saved to the shared OneDrive folder, then uploaded on the Feed screen | A person clicks Land | ROUTE_1_MANUAL, with the user's name | Same gates, same versioned storage |
| **2. Scheduled export** | A script, under the read-only account | A MAIIC server, first working day after month-end (Task Scheduler) | Written to a shared folder | The system polls the folder at 06:30 and lands what it finds | ROUTE_2_FOLDER | Same gates, same versioned storage |
| **3. Push by API** | The same script | A MAIIC server, same schedule | Sent over HTTPS with a token | Arrival at the endpoint | ROUTE_3_API | Same gates, same versioned storage |
| **4. Direct read (this request)** | The IFRS 9 system itself, under the read-only account | The system's server over the VPN; monthly, or daily for the overdue run | Never leaves the system: the pack is built in place | The system's scheduler | ROUTE_4_DIRECT | Same gates, same versioned storage |
| **5. Vendor view or API** | The IFRS 9 system, reading the vendor's views or endpoint | As route 4 | As route 4 | The system's scheduler | ROUTE_5_VENDOR | Same gates, same versioned storage |

"""
anchor = "## 5. What the account will do, and will not do"
assert anchor in t; t = t.replace(anchor, landing + anchor, 1)
NEW.mkdir(parents=True, exist_ok=True)
(NEW / f"{NAME}.md").write_text(t, encoding="utf-8")
REPO = Path(r"C:\xampp\htdocs\MAICC-IFRS9")
print(subprocess.run([sys.executable, str(REPO / "docs/build-files/spec_render.py"), str(NEW / f"{NAME}.md")], capture_output=True, text=True))
# repo copies: the md and the diagram beside it
q = REPO / "docs/bootstrap/queries"
shutil.copy(NEW / f"{NAME}.md", q / f"{NAME}.md")
shutil.copy(NEW / "Feed routes and landing zone - diagram.png", q / "Feed routes and landing zone - diagram.png")
print([l for l in t.splitlines() if l.startswith("## ")])
