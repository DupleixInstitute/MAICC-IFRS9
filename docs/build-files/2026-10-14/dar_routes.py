import re, subprocess, shutil, sys
from pathlib import Path
F = Path(r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\2. Documents from clients\Raw Query Scripts\Query Requests to MAIIC\Data access request - E-Banker read-only access (route 4) - 14 Oct 2026.md")
t = F.read_text(encoding="utf-8")

# renumber sections 3..10 -> 4..11 (from the bottom up), and the two cross-references
for n in range(10, 2, -1):
    t = t.replace(f"\n## {n}. ", f"\n## {n+1}. ")
t = t.replace("Vendor's written confirmation (section 4)", "Vendor's written confirmation (section 5)")
t = t.replace("the duplicate rules (section 7)", "the duplicate rules (section 8)")

# ask for the test environment first
old_ask = "4. If E-Banker runs a standby or reporting replica, access to that rather than production. The queries are read-only either way; the replica simply removes any question of load on the live system.\n"
new_ask = old_ask + ("5. **The same account, first, on E-Banker's test environment** (the UAT or test copy of the database, if MAIIC has one; otherwise a restored copy of a recent backup). "
                     "Everything is proved there before the production account is switched on (section 6).\n")
assert old_ask in t; t = t.replace(old_ask, new_ask, 1)

routes = """## 3. Background: the five feed routes

The system takes E-Banker data through one door, the landing zone, whatever road it arrives by. Every route produces the same thing (a pack: one file per registered query plus a manifest of row counts and hashes) and every pack passes the same checks before anything is used. Moving from one route to another therefore changes nothing in the figures; it changes only who runs the queries and how the files travel. Which route is in force is a governed setting in the system, and the manual route always stays available for a missed month or a correction.

| Route | How it works | What it needs | Status |
|---|---|---|---|
| **1. Manual pack** | Barry runs the versioned queries in SQL Developer, exports one CSV per query and saves them to the shared OneDrive folder; the system's Feed screen lands them | Nothing new | **In use since 7 October.** Every pack to date has come this way. Exposed to the three problems in section 2 |
| **2. Scheduled export at MAIIC** | A script (SQLcl or SQL\\*Plus, written by Dupleix) runs the same queries on a MAIIC server by Windows Task Scheduler on the first working day after month-end, and writes the pack to a shared folder; the system polls the folder at 06:30 and lands it | A read-only Oracle account for the script; one shared folder; Barry installs the task | **Built on the system side** (the folder poller runs daily); waits for the account and the MAIIC-side task |
| **3. Push by API** | The same script sends the pack to the system over HTTPS with a token, instead of through a folder | A token issued by the system; outbound HTTPS from the MAIIC server | **Built on the system side** (the endpoint exists); waits for the token and the script. The alternative to route 2 where a shared folder is awkward |
| **4. Direct read over the VPN** (this request) | The system connects to E-Banker's Oracle database read-only over the existing VPN, runs the registered queries itself on schedule and lands the result; the pack and manifest are generated inside the system, so the audit trail is the same as for a manual pack | The read-only account and firewall rule of section 1; the Oracle client on the system server; the vendor's consent (section 5); Dr Thom's authorisation | **Not built yet**: about a week once the account exists. The destination: no person in the loop, no export limits |
| **5. Vendor view or API** | Virtual Galaxy provides a reporting schema of views, or an endpoint, carrying the same columns; route 4 reads that instead of the tables | A change request to the vendor, likely paid | **Not built.** Only if the vendor will not allow route 4 on the tables themselves |

Routes 2 and 4 use the **same read-only account**. That is why the sequence in section 6 starts with route 2: it proves the account, the queries and the month-end timing with no new software on either side, while the vendor's consent for route 4 is obtained.

"""
anchor = "## 4. What the account will do, and will not do"
assert anchor in t; t = t.replace(anchor, routes + anchor, 1)

# sequence: test environment first, then production
old_seq = t[t.index("## 6. Proposed sequence"):t.index("## 7. How the queries are refreshed")]
new_seq = """## 6. Proposed sequence: test environment first, then production

Nothing touches production until it has worked end to end on the test environment. The system has its own test databases (separate from the live one, already used for every build and audit to date), so the test stage pairs E-Banker's test environment with the system's test database: no live data on either side is read or written until stage C.

| Stage | Step | Who | When |
|---|---|---|---|
| **A. Authorise** | Dr Thom authorises the request; Barry raises section 5 with the vendor | MAIIC | This week |
| **B. Test environment** | Read-only account created on E-Banker's test environment (UAT, or a restored recent backup), with the grants in the annex; firewall rule from the system's test server | MAIIC ICT | On authorisation |
| | Route 2 on test: the scheduled script run against the test database, the pack written to a test folder, landed into the system's test database | Dupleix writes the script, Barry installs the task | Within two weeks of the account |
| | Route 4 on test: the Oracle client and connection installed on the system's test server; the system runs the queries itself against the test database | Dupleix | On the vendor's confirmation; about a week |
| | **Acceptance on test**, signed off by Barry and Dupleix: (1) every registered query runs under the account and nothing outside the annex can be read; (2) a month read by route 4 lands with the same rows, hashes and check results as the same month landed by hand (route 1); (3) a re-read of the same month lands as all unchanged, with no duplicates; (4) a deliberately broken file is quarantined and nothing downstream moves; (5) the read takes under 15 minutes and the database shows no measurable load; (6) a backup is restored and re-read from its watermarks | Dupleix and MAIIC ICT | At the end of stage B |
| **C. Production** | The same account created on production (or the replica, per the vendor); the connection pointed at it; the route setting changed to route 4 under maker-checker | MAIIC ICT; Dupleix; approver in the Governance Centre | After acceptance on test |
| | **Parallel run**: two month-ends read by route 4 alongside the manual pack; each pair must tie row for row before the manual pack is stood down | Dupleix and Barry | Two month-ends |
| | Switch: route 4 in force; route 1 kept as the fallback | MAIIC | After the parallel run |

If the vendor declines route 4 (section 5, item 5), stages B and C run the same way on route 2, which needs no vendor change, or on the vendor's views under route 5.

"""
t = t.replace(old_seq, new_seq, 1)
F.write_text(t, encoding="utf-8")
print([l for l in t.splitlines() if l.startswith("## ")])
REPO = Path(r"C:\xampp\htdocs\MAICC-IFRS9")
shutil.copy(F, REPO / "docs/bootstrap/queries" / F.name)
print(subprocess.run([sys.executable, str(REPO / "docs/build-files/spec_render.py"), str(F)], capture_output=True, text=True).stdout)
