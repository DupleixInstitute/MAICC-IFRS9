import re, shutil, subprocess, sys
from pathlib import Path
DIR = Path(r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\3. Project Execution\E-Banker Integration")
NAME = "Data access request - E-Banker read-only access (route 4) - 14 Oct 2026"
F = DIR / f"{NAME}.md"
t = F.read_text(encoding="utf-8")

# ---- renumber: old 6 -> 7, old 7..12 -> 9..14 (new 6 = account setup, new 8 = bootstrap)
def newnum(n):
    if n <= 5: return n
    if n == 6: return 7
    return n + 2
heads = list(re.finditer(r"\n## (\d+)\. ", t))
for m in reversed(heads):
    n = int(m.group(1)); t = t[:m.start()] + f"\n## {newnum(n)}. " + t[m.end():]
t = re.sub(r"\bsection (\d+)\b(?!\.\d)", lambda m: f"section {newnum(int(m.group(1)))}", t)

# ---- annex table names, for the grant script
annex = t[t.index("## Annex"):]
tables = []
for line in annex.splitlines():
    if not line.startswith("| ") or line.startswith("| Table") or "Data dictionary" in line:
        continue
    cell = line.split("|")[1].strip()
    for name in [c.strip() for c in cell.split(",")]:
        if re.fullmatch(r"[A-Z0-9_.]+", name):
            tables.append(name if "." in name else f"VGCBS.{name}")
grants = "\n".join(f"- `GRANT SELECT ON {tb} TO ifrs9_feed_read;`" for tb in tables)
print(len(tables), "tables in the grant list")

setup = f"""## 6. Setting up the accounts: step by step for MAIIC ICT

The same steps are done twice: first on **E-Banker's test environment** (stage B of section 9), then on **production or the replica** (stage C), only after the tests on the test environment have passed. Both use the same account name, with different passwords. Every statement below is standard Oracle and is run by a database administrator (a user with the DBA role or equivalent). None of it changes E-Banker's own tables, code or users. If the vendor wants to run the steps themselves, this section is what to send them.

**Step 1. Agree the names and the server.** Account: `MAIIC_IFRS9_RO`. Role: `IFRS9_FEED_READ`. Profile: `IFRS9_FEED_PROFILE`. Dupleix provides the IP address of the system's test server now and of the production server at stage C.

**Step 2. Create a profile that limits the account.** The profile caps sessions, locks the account after failed logins and forces a password change every 90 days.

- `CREATE PROFILE ifrs9_feed_profile LIMIT SESSIONS_PER_USER 2 FAILED_LOGIN_ATTEMPTS 5 PASSWORD_LOCK_TIME 1 PASSWORD_LIFE_TIME 90 PASSWORD_GRACE_TIME 7 IDLE_TIME 30;`

(`SESSIONS_PER_USER` and `IDLE_TIME` take effect only when the database parameter `RESOURCE_LIMIT` is TRUE, which is the default from Oracle 12c. If it is FALSE, leave it as it is and tell us; the account works the same.)

**Step 3. Create the user.** It can log in and own nothing; it has no quota, so it cannot create anything.

- `CREATE USER maiic_ifrs9_ro IDENTIFIED BY "<a strong password>" PROFILE ifrs9_feed_profile DEFAULT TABLESPACE users TEMPORARY TABLESPACE temp QUOTA 0 ON users;`
- `GRANT CREATE SESSION TO maiic_ifrs9_ro;`

**Step 4. Create a read-only role and grant SELECT on the tables in the annex.** Granting through a role means the whole access can be removed with one statement.

- `CREATE ROLE ifrs9_feed_read;`
{grants}
- `GRANT ifrs9_feed_read TO maiic_ifrs9_ro;`

If a grant fails with ORA-00942 (table or view does not exist), that table sits in a different schema on MAIIC's installation: skip it and send us the error, and we will correct the schema. Nothing else is granted: no INSERT, UPDATE, DELETE, EXECUTE, CREATE TABLE, or any ANY privilege. The data dictionary views in the annex need no grant; they show only the tables the account can already see.

**Step 5. Switch on auditing for the account.** So that MAIIC can see every session and every statement it runs. On Oracle 12c and later with unified auditing:

- `CREATE AUDIT POLICY ifrs9_feed_audit ACTIONS LOGON, LOGOFF, SELECT;`
- `AUDIT POLICY ifrs9_feed_audit BY maiic_ifrs9_ro;`

On an older database using traditional auditing: `AUDIT SESSION BY maiic_ifrs9_ro;` and `AUDIT SELECT TABLE BY maiic_ifrs9_ro BY ACCESS;`. The records appear in `UNIFIED_AUDIT_TRAIL` (or `DBA_AUDIT_TRAIL`).

**Step 6. Open the network path.** On the firewall, allow the system's server (one IP, over the existing VPN) to reach the database listener on its port (1521 unless E-Banker uses another), and nothing else. No change is needed to the listener or to `sqlnet.ora`. Restricting by IP on the firewall rather than in the database keeps the change outside E-Banker.

**Step 7. Prove the account is read-only, before handing it over.** Log in as `MAIIC_IFRS9_RO` in SQL Developer and run:

1. `SELECT * FROM session_privs;` must return only `CREATE SESSION`.
2. `SELECT owner, table_name FROM all_tables WHERE owner IN ('VGCBS','MAIIC_ORA_LIVE') ORDER BY 1, 2;` must list only the tables in the annex.
3. `SELECT COUNT(*) FROM vgcbs.acmaster;` must return a count.
4. `DELETE FROM vgcbs.acmaster WHERE 1 = 0;` must fail with ORA-01031 (insufficient privileges). It deletes nothing even if the test went wrong, because `WHERE 1 = 0` matches no row.
5. `CREATE TABLE ifrs9_probe (x NUMBER);` must fail with ORA-01031 or ORA-01950.

Save the output of these five as a text file in the E-Banker Integration folder: it is the evidence that the account is read-only, and it is acceptance test 1 in section 9.

**Step 8. Hand over the connection details, and the password separately.** Send Dupleix the host, port and service name (by email is fine). Do not email the password. Under route 4, Barry types it into the system server's configuration file himself, or reads it out on a call. Under route 2, it is stored in an **Oracle Wallet** on the MAIIC server (`mkstore`, or the SQLcl secure credential store), so it never appears in the script.

**Step 9. For route 2 only: the server, the folder and the task.** On a MAIIC Windows server that can reach the database: install Oracle SQLcl (a single folder, needs Java 11 or later); create a shared folder (for example `\\\\<server>\\ifrs9_feed`) that the scheduled task can write to and the IFRS 9 system's server can read; create a Windows scheduled task, run by a service account, that runs Dupleix's export script on the first working day after month-end at 05:30. Dupleix supplies the script and joins the first run.

**Step 10. Look after the account.** Every 90 days: `ALTER USER maiic_ifrs9_ro IDENTIFIED BY "<new password>";` then update the system's configuration (or the wallet). To pause the feed at once: `ALTER USER maiic_ifrs9_ro ACCOUNT LOCK;` (`ACCOUNT UNLOCK` to resume). To end it: `DROP USER maiic_ifrs9_ro;` and `DROP ROLE ifrs9_feed_read;`. When the system needs a new table, Dupleix sends one line, `GRANT SELECT ON <table> TO ifrs9_feed_read;`, for MAIIC to approve and run.

**Step 11. Repeat on production at stage C.** Steps 2 to 8 again on production (or the replica the vendor names), with a new password and the production server's IP. Run step 7 again and save its output beside the test one.

"""

bootstrap = """## 8. The bootstrap: how the integration is tested before production

**What the bootstrap is.** One command in the IFRS 9 system, `eir:bootstrap`, that rebuilds the whole system from scratch on a separate, throwaway database (`maiic_ifrs9_bootstrap`) and proves the result. It takes about two minutes and never touches the production database (`maiic_ifrs9`). It has been run after every change since 8 October; it is how the figures in every review and audit to date were produced.

**What it does, in order.**

1. **Wipe.** Empties the test database and creates it again from the system's own definitions.
2. **Land.** Takes every pack MAIIC has sent so far (kept, with their hashes, in the project repository) and the client inputs (Finance's fee template, the take-on workbook, the trial balances), and lands them through the same landing zone and the same gates as production (section 4).
3. **Build.** Builds the monthly loan books, the contract inputs, the fees and the EIR schedules from the landed rows.
4. **Run the engines.** Staging, PD and LGD, ECL, EIR and revenue, the forward-looking chain, sensitivity and stress, the reports, the compliance register and the GL reconciliation, month by month, in the same order as production.
5. **Verify.** Checks nine **golden ties**: figures that must agree exactly with an independent source. They are the interest posted in the ledger; the carrying amount against E-Banker's stored loan book report, for every account and month; the year-end interest contra entries on GL 4215 and 4216; and the loan book against the trial balance on the five loan GLs (1050101, 1050102, 1050201, 1050202, 1050401). Today all nine pass.

Anything approved inside the test database is labelled "System Bootstrap (automated data-readiness, not a MAIIC approval)". Only MAIIC's own approvals, in production, count.

![Figure 2. The bootstrap rebuilds the system on the test database and checks the nine golden ties; only when they hold does a change reach production.](Bootstrap - test harness and reference copy - diagram.png)

**Where it fits in this integration.**

| Role | What it means for the integration |
|---|---|
| **History load** | The packs received by hand since 7 October are the history up to go-live. The bootstrap is how that history is built, reproducibly; route 4 then only reads forward from the watermarks the history leaves. |
| **The test system** | In stage B (section 9), route 2 and route 4 land into the bootstrap database, not production. The test account on E-Banker's test environment is paired with this test database, so nothing live is read or written on either side. |
| **The baseline** | The bootstrap already holds each month as it arrived by hand. A month read by route 4 must land against it as "all unchanged": no new rows, no new versions. Any difference is named, row by row, and is either a query to correct or a genuine change in E-Banker to explain. |
| **The regression gate** | Before any change reaches production (a new query version, an engine change, a change of route), the bootstrap is re-run and the nine golden ties must still pass. |
| **Disaster-recovery rehearsal** | The quarterly restore test of section 13 uses the same mechanism: rebuild, run the ties, record the result. |

For stage B the bootstrap gains one option, to take a fresh read from E-Banker's test environment by route 4 in addition to the committed packs, so the whole test runs under one command. This is part of the route 4 build in section 9, not separate work.

"""

a5 = "## 7. What we need from Virtual Galaxy"
assert a5 in t; t = t.replace(a5, setup + a5, 1)
a8 = "## 9. Proposed sequence: test environment first, then production"
assert a8 in t; t = t.replace(a8, bootstrap + a8, 1)

# ---- sequence wording: name the bootstrap database and the setup steps
reps = [
    ("so the test stage pairs E-Banker's test environment with the system's test database: no live data on either side is read or written until stage C.",
     "so the test stage pairs E-Banker's test environment with the system's test database, the bootstrap database of section 8: no live data on either side is read or written until stage C."),
    ("| **B. Test environment** | Read-only account created on E-Banker's test environment (UAT, or a restored recent backup), with the grants in the annex; firewall rule from the system's test server | MAIIC ICT | On authorisation |",
     "| **B. Test environment** | Read-only account created on E-Banker's test environment (UAT, or a restored recent backup) by steps 1 to 8 of section 6; firewall rule from the system's test server | MAIIC ICT | On authorisation |"),
    ("the pack written to a test folder, landed into the system's test database", "the pack written to a test folder, landed into the bootstrap database"),
    ("the system runs the queries itself against the test database |", "the system runs the queries itself against E-Banker's test environment and lands into the bootstrap database |"),
    ("(1) every registered query runs under the account and nothing outside the annex can be read;",
     "(1) every registered query runs under the account and nothing outside the annex can be read (the saved output of section 6, step 7);"),
    ("(2) a month read by route 4 lands with the same rows, hashes and check results as the same month landed by hand (route 1);",
     "(2) a month read by route 4 lands against the bootstrap's copy of the same month (received by hand) as all unchanged: the same rows and hashes, no new versions;"),
    ("(6) a backup is restored and re-read from its watermarks |",
     "(6) a backup is restored and re-read from its watermarks; (7) the bootstrap re-run with the route 4 data passes all nine golden ties |"),
    ("| **C. Production** | The same account created on production (or the replica, per the vendor);",
     "| **C. Production** | The same account created on production (or the replica, per the vendor) by section 6, step 11;"),
]
for a, b in reps:
    assert a in t, a[:60]; t = t.replace(a, b, 1)

F.write_text(t, encoding="utf-8")
print([l for l in t.splitlines() if l.startswith("## ")])
print(sorted(set(re.findall(r"section \d+", t))))
REPO = Path(r"C:\xampp\htdocs\MAICC-IFRS9")
print(subprocess.run([sys.executable, str(REPO / "docs/build-files/spec_render.py"), str(F)], capture_output=True, text=True).stdout)
q = REPO / "docs/bootstrap/queries"
shutil.copy(F, q / F.name)
shutil.copy(DIR / "Bootstrap - test harness and reference copy - diagram.png", q / "Bootstrap - test harness and reference copy - diagram.png")
