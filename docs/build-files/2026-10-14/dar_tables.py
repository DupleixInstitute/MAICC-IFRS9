import re, shutil, subprocess, sys
from pathlib import Path
DIR = Path(r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\3. Project Execution\E-Banker Integration")
REPO = Path(r"C:\xampp\htdocs\MAICC-IFRS9")
TECH = DIR / "Data access request - E-Banker read-only access (route 4) - 14 Oct 2026.md"
PLAIN = DIR / "Plain-language guide - connecting the IFRS 9 system to E-Banker - 14 Oct 2026.md"
FIG4 = "Landing zone - the four tables, one payment followed through - diagram.png"

def sub(t, a, b):
    assert a in t, "anchor missing: " + a[:80]
    return t.replace(a, b, 1)

t = TECH.read_text(encoding="utf-8")

# ---------------------------------------------------------------- 4.1 the four tables, as a lesson
lesson = f"""### 4.1 Inside the landing zone: the four tables, explained

This part is written so that anyone at MAIIC can follow it, not only ICT. If you have run a receiving office, you already know how the landing zone is organised: it keeps four record books.

1. **The register** (`ebanker_queries`) is the list of everything the system is allowed to ask E-Banker for: about sixty questions, each with a code (P1_01, P2_08, OD_01 …). For each one it records which E-Banker table it reads, which column is that table's own unique number for a record, which columns hold the account and the date, the version of the question, and whether to fetch everything each time or only what is new. If a question is not in the register, the system will not accept its answers.
2. **The deliveries book** (`ebanker_loads`) has one line for every pack that arrives: when, by which route, its fingerprint, whether it was accepted (LANDED) or set aside (QUARANTINED), the result of every check, and the **watermark**, meaning the last record received for each question so that the next delivery fetches only what is new.
3. **The files book** (`ebanker_pack_files`) has one line for every file inside a pack: its fingerprint, how many records the pack said it held, how many were actually stored, and how many of those were new, changed or already held.
4. **The records book** (`ebanker_raw_rows`) holds the records themselves, one line for each version of each E-Banker record. A few facts are copied into labelled columns so they can be found quickly: E-Banker's own record number, the account, the date, the version, and whether this version has been replaced. The full record, every column exactly as E-Banker sent it, is kept beside them unchanged.

**One real payment, followed through** (Figure 4, from the bootstrap database). MUSECO repaid K200,000,000 on 24 July 2025. In E-Banker that is ledger record 194427. It arrived in delivery 1 (by hand, accepted on 9 October 2026), in the file `P1_01_ledger_all.csv`. That file said it held 3,640 records and 3,640 were stored. In the records book it is line 20: account 000104420000005, dated 24 July 2025, version 1, not replaced, with all 83 of E-Banker's columns kept as they were (amount 200,000,000.00, transaction type 305, "By Trf Loan repayment", loan GL 1050101).

![Figure 4. The four record books of the landing zone, following MUSECO's K200,000,000 repayment of 24 July 2025 from E-Banker's ledger to line 20 of the records book, and what happens if E-Banker later corrects it.](Landing zone - the four tables, one payment followed through - diagram.png)

**What happens if E-Banker later changes that record.** Suppose the payment is corrected in E-Banker to K195,000,000. At the next delivery the system sees record 194427 again with a different fingerprint. It does **not** overwrite line 20. It marks line 20 with the date and time it was replaced, and adds a new line, version 2, holding the corrected amount. Figures already reported for October were calculated from version 1 and can be reproduced exactly; figures from November use version 2. If the record arrives again unchanged, its fingerprint matches and nothing is added. (So far no record has needed a second version; the figure shows the case as an illustration.)

**Questions MAIIC can answer from the four books**, without asking Dupleix:

| Question | Where the answer is |
|---|---|
| Did this month's pack arrive, and was it accepted? | The deliveries book: the pack's line, its status and its check results |
| Was any file short or altered? | The files book: records declared against records stored, and the fingerprint |
| Which E-Banker record is behind a figure in a report? | The records book: E-Banker's record number, the delivery it came in, its version |
| Has E-Banker changed a record we had already received? | The records book: any line with a version above 1, and the date its earlier version was replaced |
| What exactly did we ask E-Banker for, and has the question changed? | The register: the query, its version and the fingerprint of its SQL |

**Have the tables been created?** Yes, in the system's test databases, where they hold the data received so far: the bootstrap database holds 65 registered questions, 23 deliveries, 75 files and 88,977 records. In the production database they are created, empty, when the system is installed on MAIIC's server (the installation runs the same database set-up), ready for the first pack. Their full column-by-column definition is in the system's code.

"""
t = sub(t, "## 5. What the account will do, and will not do", lesson + "## 5. What the account will do, and will not do")

# ---------------------------------------------------------------- step 6a: test the connection
t = sub(t, "**Step 7. Prove the account is read-only, before handing it over.**",
        """**Step 6a. Test the connection.** Before anything else is tried, confirm that the account can reach the database from the right computer. Do this from the computer that will actually use the account: the IFRS 9 system's server for route 4 (with Dupleix on the call), or the MAIIC server for routes 2 and 3.

1. **Is the database reachable on the network?** In PowerShell on that computer: `Test-NetConnection <database host> -Port 1521`. It must show `TcpTestSucceeded : True`. If it shows False, the firewall rule of step 6 is not yet in place.
2. **Can the account log in?** With SQLcl: `sql maiic_ifrs9_ro@//<database host>:1521/<service name>`, enter the password when asked. (In SQL Developer, a new connection with the same details and **Test** must show "Status: Success".)
3. **Who, where and when: the test-connection query.** Once logged in, run:

`SELECT USER AS connected_as, SYS_CONTEXT('USERENV','DB_NAME') AS database_name, SYS_CONTEXT('USERENV','SERVICE_NAME') AS service_name, SYS_CONTEXT('USERENV','IP_ADDRESS') AS connected_from, TO_CHAR(SYSDATE, 'YYYY-MM-DD HH24:MI:SS') AS database_time FROM dual;`

It must return one row: `MAIIC_IFRS9_RO`, the name of the **test** database at stage B (the **production** or replica name at stage C, never the other way round), and as `connected_from` the IP address of the computer you are on. A wrong database name here is the one mistake that matters most, so check it before going further.
4. **Can it read what it should?** Run:

`SELECT COUNT(*) AS maiic_loan_accounts, MAX(a.account_open_date) AS latest_account_opened FROM vgcbs.acmaster a WHERE a.scheme_mst_id IN (84,85,86,87,88,89,90,91,92,93,94,95,140,141,142,143,144,145);`

It must return a count above zero and a recent date. On the test environment the figures reflect the date the test copy was taken.

Save the four results in a text file named `Connection test - <test or production> - <date>.txt` in the E-Banker Integration folder, together with the output of step 7.

**Step 7. Prove the account is read-only, before handing it over.**""")
t = sub(t, "(1) every registered query runs under the account and nothing outside the annex can be read (the saved output of section 6, step 7);",
        "(1) the connection test of section 6, step 6a passes from the system's test server, every registered query runs under the account, and nothing outside the annex can be read (the saved outputs of steps 6a and 7);")
t = sub(t, "**Step 11. Repeat on production at stage C.** Steps 2 to 8 again on production (or the replica the vendor names), with a new password and the production server's IP. Run step 7 again and save its output beside the test one.",
        "**Step 11. Repeat on production at stage C.** Steps 2 to 8 again on production (or the replica the vendor names), with a new password and the production server's IP. Run steps 6a and 7 again and save their output beside the test ones; check that step 6a now names the production (or replica) database.")

# ---------------------------------------------------------------- address MAIIC personnel directly
t = t.replace("Barry's team", "MAIIC ICT")
t = sub(t, "The same steps are done twice: first on", "This section is for MAIIC ICT. You do the same steps twice: first on")
t = sub(t, "Every statement below is standard Oracle and is run by a database administrator (a user with the DBA role or equivalent).",
        "Every statement below is standard Oracle and is run by your database administrator (a user with the DBA role or equivalent).")
t = sub(t, "If the vendor wants to run the steps themselves, this section is what to send them.",
        "If you would rather the vendor ran the steps, this section is what to send them. Dupleix will join any of the steps on a call if that helps.")
TECH.write_text(t, encoding="utf-8")

# ---------------------------------------------------------------- plain-language guide
p = PLAIN.read_text(encoding="utf-8")
plain = """### Inside the checkpoint: four record books

The checkpoint keeps four record books, in the system's own database:

- **The register**: the list of questions the system is allowed to ask E-Banker (about sixty).
- **The deliveries book**: one line for every delivery: when it came, how, whether it was accepted, and how far it got (so the next delivery fetches only what is new).
- **The files book**: one line for every file in a delivery: how many records it promised and how many were stored.
- **The records book**: the records themselves, each kept exactly as E-Banker sent it, with every later correction added as a new version beside the old one rather than over it.

Figure 4 follows one real payment through the four books: MUSECO's K200,000,000 repayment of 24 July 2025. These books already exist in the test copies of the system, holding the data received so far, and are created empty when the system is installed for real.

![Figure 4. One payment followed through the four record books of the checkpoint, and what happens if E-Banker later corrects it.](Landing zone - the four tables, one payment followed through - diagram.png)

"""
p = sub(p, "## 5. The login we are asking for", plain + "## 5. The login we are asking for")
p = sub(p, "The technical document gives Barry's team the exact steps and a short test to prove the login really is read-only before it is handed over.",
        "The technical document gives MAIIC ICT the exact steps, a simple test that the connection works from the right computer to the right database, and a short test to prove the login really is read-only before it is handed over.")
p = p.replace("Barry's team", "MAIIC ICT")
PLAIN.write_text(p, encoding="utf-8")

for f in (TECH, PLAIN):
    print(subprocess.run([sys.executable, str(REPO / "docs/build-files/spec_render.py"), str(f)], capture_output=True, text=True).stdout.strip().splitlines()[-1])
q = REPO / "docs/bootstrap/queries"
for name in (TECH.name, PLAIN.name, FIG4):
    shutil.copy(DIR / name, q / name)
print("copied")
