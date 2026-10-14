import shutil, subprocess, sys
from pathlib import Path
DIR = Path(r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\3. Project Execution\E-Banker Integration")
REPO = Path(r"C:\xampp\htdocs\MAICC-IFRS9")
TECH = DIR / "Data access request - E-Banker read-only access (route 4) - 14 Oct 2026.md"
PLAIN = DIR / "Plain-language guide - connecting the IFRS 9 system to E-Banker - 14 Oct 2026.md"
FIG5 = "Route 4 - proposed build and test timetable - gantt.png"

def sub(t, a, b):
    assert a in t, "anchor missing: " + a[:80]
    return t.replace(a, b, 1)

table = """| Stage | What | Who | Dates (2026 to 2027) |
|---|---|---|---|
| **A** | Dr Thom approves the request; Barry sends the vendor letter (section 7) | MAIIC | Thu 15 to Fri 23 October |
| **A** | Vendor's written reply. **Decision point Fri 6 November**: if there is no reply or a refusal, the same timetable continues on route 2, which needs no vendor change | Virtual Galaxy | 23 October to 6 November |
| **B** | Test account on E-Banker's test copy, firewall rule, connection test and read-only proof (section 6, steps 1 to 8) | MAIIC ICT | Mon 26 October to Fri 6 November |
| **B** | Route 2 script written by Dupleix and installed on a MAIIC server against the test copy | Dupleix and MAIIC ICT | 26 October to 6 November |
| | *October month-end: read by hand as now (route 1)* | *Barry* | *Mon 2 November* |
| **B** | Route 4 build: Oracle client and connection on the system's test server, the query runner, the bootstrap option; route 3 hardening if route 3 is chosen | Dupleix | Mon 2 to Fri 13 November |
| **B** | Testing on the test copy into the bootstrap database; acceptance tests 1 to 7; **sign-off Fri 20 November** | Dupleix and MAIIC ICT | Mon 16 to Fri 20 November |
| **C** | Production (or replica) account by section 6, step 11; connection test against production; switch prepared in the Governance Centre | MAIIC ICT | Mon 23 to Fri 27 November |
| **C** | **Parallel run 1**: November month-end read by route 4 beside the hand pack; must tie row for row | Dupleix and Barry | Tue 1 to Fri 4 December |
| | *Possible ICT year-end change freeze: no changes, only the parallel run continues as planned* | | *14 December to 3 January* |
| **C** | **Parallel run 2**: December year-end read by route 4 beside the hand pack | Dupleix and Barry | Mon 4 to Fri 8 January 2027 |
| **C** | Switch: route 4 in force (maker-checker); route 1 kept as the fallback | MAIIC | Mon 18 to Fri 22 January 2027 |
| | **First month-end by route 4 alone: January** | | **Mon 1 February 2027** |"""

why = """### 9.1 Why this must be planned now, not after go-live

This route has to be planned early. The build itself is about a week; the time is taken by things outside the build, most of them outside Dupleix's control, and each one can only happen in a fixed order:

- **Approvals and the vendor take weeks, not days.** Dr Thom's authorisation, the vendor's written confirmation of support terms, and MAIIC's own change control for a new database account and a firewall rule all have to be in place before anything can be tested.
- **A test copy of E-Banker has to exist.** If MAIIC does not already have a UAT copy, one has to be restored from a backup; that is ICT and vendor time that needs booking.
- **Proof needs real month-ends, and there is only one a month.** The parallel run needs two month-ends read both ways and tying row for row. If the November month-end is missed, the earliest switch moves from January to February; every missed month-end moves it by a full month.
- **Year-end is the month that matters most.** The December 2026 year-end feeds the annual financial statements and Deloitte's audit. Having route 4 proven on November and run in parallel on December gives the auditors the strongest evidence: the same figures by two independent routes. Starting later means the year-end is read by hand only.
- **Year-end change freezes.** Many ICT teams freeze changes over December. Everything that changes MAIIC's systems (the accounts, the firewall rule, the production set-up) is therefore scheduled to finish by **27 November**.
- **The manual route carries risk every month it remains the only one.** Each pack so far has needed a re-run; the overdue table cannot be exported by hand at all. Until route 2 or 4 is live, those risks recur at every month-end.

### 9.2 Proposed build and test timetable

The dates assume Dr Thom approves by **Friday 23 October 2026**. Each week of delay moves the whole chain; a missed month-end moves it by a month. Month-end reading days are the first working day after month-end.

""" + table + """

![Figure 5. Proposed timetable for route 4, from approval in October 2026 to the first month-end read by route 4 alone in February 2027, with the month-ends that anchor it.](Route 4 - proposed build and test timetable - gantt.png)

"""
t = TECH.read_text(encoding="utf-8")
t = sub(t, "## 10. How the queries are refreshed", why + "## 10. How the queries are refreshed")
t = sub(t, "4. If E-Banker runs a standby or reporting replica, access to that rather than production.",
        "**Please plan this now.** The build is about a week, but approvals, the vendor's reply, a test copy of E-Banker and two month-ends of parallel running set the pace. To have the route proven on the November month-end and run in parallel on the December 2026 year-end, the request needs approval by **Friday 23 October 2026** (timetable in section 9.2).\n\n4. If E-Banker runs a standby or reporting replica, access to that rather than production.")
TECH.write_text(t, encoding="utf-8")

p = PLAIN.read_text(encoding="utf-8")
p = sub(p, "What we need: Dr Thom's approval,",
        "**This needs to be planned now.** The building takes about a week, but approvals, the vendor's reply and two months of running old and new side by side take the time. To have it proven before the December 2026 year-end, which feeds the annual accounts and the audit, we need approval by **Friday 23 October 2026** (timetable in section 7).\n\nWhat we need: Dr Thom's approval,")
ptime = """**Proposed timetable**, assuming approval by Friday 23 October 2026:

| When | What |
|---|---|
| By 23 October | Dr Thom approves; Barry writes to the vendor |
| 26 October to 6 November | MAIIC ICT sets up the test login; the scheduled program is installed on the test copy; vendor reply expected by 6 November |
| 2 to 13 November | Dupleix builds the direct read (way 4) |
| 16 to 20 November | Everything tested on the copy; signed off on 20 November |
| 23 to 27 November | The login is created on the live system, before any year-end change freeze |
| 1 to 4 December | November month-end read both ways, side by side |
| 4 to 8 January 2027 | December year-end read both ways, side by side |
| 18 to 22 January 2027 | Switch to the direct read; the hand method stays as a backup |
| 1 February 2027 | First month-end by the direct read alone |

Each week of delay moves every later date; missing a month-end moves the switch by a whole month, and missing November means the year-end is read by hand only.

![Figure 5. The proposed timetable, from approval in October 2026 to the first month-end read automatically in February 2027.](Route 4 - proposed build and test timetable - gantt.png)

"""
p = sub(p, "## 8. The bootstrap: our test copy and safety check", ptime + "## 8. The bootstrap: our test copy and safety check")
PLAIN.write_text(p, encoding="utf-8")

for f in (TECH, PLAIN):
    print(subprocess.run([sys.executable, str(REPO / "docs/build-files/spec_render.py"), str(f)], capture_output=True, text=True).stdout.strip().splitlines()[-1])
q = REPO / "docs/bootstrap/queries"
for name in (TECH.name, PLAIN.name, FIG5):
    shutil.copy(DIR / name, q / name)
print("copied")
