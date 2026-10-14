import subprocess, sys, shutil
from pathlib import Path
DIR = Path(r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\3. Project Execution\E-Banker Integration")
REPO = Path(r"C:\xampp\htdocs\MAICC-IFRS9")
TECH = DIR / "Data access request - E-Banker read-only access (route 4) - 14 Oct 2026.md"
PLAIN = DIR / "Plain-language guide - connecting the IFRS 9 system to E-Banker - 14 Oct 2026.md"

TECH_SEC = """### 6.1 How the connection will be configured on the IFRS 9 system, and kept secure

Route 4 is not built yet, so this describes how the build will hold the connection. It follows the way the system already keeps its own database password.

**Where the details live.** The connection details go in the system's environment file on MAIIC's server (`.env` for production, `.env.bootstrap` for the bootstrap database), never in the code and never in the system's database:

| Setting | Example | Held as |
|---|---|---|
| `EBANKER_DB_HOST` | the database server's name or IP | plain text |
| `EBANKER_DB_PORT` | 1521 | plain text |
| `EBANKER_DB_SERVICE` | the Oracle service name (test copy or production) | plain text |
| `EBANKER_DB_USERNAME` | `MAIIC_IFRS9_RO` | plain text |
| `EBANKER_DB_PASSWORD` | the password from section 6, step 8 | secret: typed by MAIIC ICT, never shown on screen or in any log |

The build adds a separate database connection called `ebanker` that reads only these settings. The system's own MySQL connection is left untouched, so the two can never be mixed up.

**Kept out of the code repository.** The environment files are already excluded from the repository (`.gitignore` lists `.env`, `.env.bootstrap`, `.env.demo` and the others). A pull from the repository (section 2.2) therefore never carries a password onto MAIIC's server or off it. Only `.env.example` is in the repository, and it lists the setting names with empty values.

**Who types the password.** MAIIC ICT enters `EBANKER_DB_PASSWORD` directly on the server. Dupleix may watch the AnyDesk session for the other settings but does not see or hold the password, and it is never sent by email or chat.

**Protecting the file on the server.**

- **File permissions.** The environment file is readable only by the account the system runs under and by the server's administrators. On Windows that is an NTFS permission on the file; on Linux, `chmod 600`.
- **Optional encryption at rest.** Laravel 10 can encrypt the environment file (`php artisan env:encrypt`). The file on disk is then unreadable without a key, and that key is held by MAIIC ICT, not stored on the same disk. If MAIIC's policy asks for encryption at rest, this is how it is met.
- **The configuration cache.** If the system's configuration is cached for speed (`php artisan config:cache`), the cached file carries the same values and gets the same permissions.

**Test and production kept apart.** The bootstrap database's file (`.env.bootstrap`) points only at E-Banker's test copy; the production file (`.env`) points only at production or the replica. The connection test of section 6, step 6a, which names the database it reached, is run from each before first use. A wrong pairing shows up there before anything is read.

**What the system itself will do with the connection.**

- **One user.** Only the feed runner uses the `ebanker` connection. No screen in the system shows, edits or tests the credentials, and no report or export can reach them.
- **Read-only session as well as a read-only account.** Each session starts with `SET TRANSACTION READ ONLY`. The account can't write anyway (section 6); this is a second guard in the system's own code.
- **Registered statements only.** The runner executes only statements from the register (section 4.2) whose SQL fingerprint matches the registered one. It cannot run a statement typed in by hand.
- **No secrets in logs.** Each run is written to the audit trail with the queries run, rows read and timings, never with the password or the full connection string. Errors are logged with the Oracle error number and the query identifier only.
- **A connection-test command.** The build adds a command that runs the step 6a query from the system itself and prints who it connected as, which database and from where. MAIIC ICT can check the connection at any time without opening SQL Developer.

**Encryption on the network.** The connection runs inside MAIIC's own network (section 2.1). If MAIIC's policy still requires it, Oracle's network encryption can be switched on from the system's side alone, in the Oracle client's `sqlnet.ora` on the IFRS 9 server (`SQLNET.ENCRYPTION_CLIENT = REQUIRED`). Oracle databases accept encrypted connections by default, so nothing changes on E-Banker.

**Changing or withdrawing the password.** Every 90 days (section 6, step 10), MAIIC ICT changes the password in Oracle. It then updates `EBANKER_DB_PASSWORD` on the server, clears the configuration cache (`php artisan config:clear`, then `config:cache` if it is used) and runs the connection-test command. Locking the account in Oracle stops the feed at once, whatever the file says.

**Backups.** The environment file is not part of the database backups of section 13, so no backup copy carries the password. MAIIC ICT keeps the password, and the encryption key if one is used, in its own password vault, so the server can be rebuilt.

"""

PLAIN_SEC = """### 6.1 How the system will hold the connection details safely

The system needs to know where E-Banker's database is and how to sign in to it. Those details are kept like this:

- **In one settings file on MAIIC's server, not in the code.** The address, the database name, the login name and the password go in the system's private settings file on MAIIC's server. They are never written into the system's code, so fetching new code onto the server (section 2.2) never carries a password with it. That file is already kept out of the shared code.
- **Only MAIIC ICT types the password.** Dupleix may watch the session but never sees or keeps the password, and it is never sent by email or chat.
- **The file is locked down.** Only the system itself and the server's administrators can open it. If MAIIC's policy asks for it, the file can also be encrypted, with the key held by MAIIC ICT.
- **Test and live are kept apart.** The test copy of the system has its own settings file, which points only at E-Banker's test copy. The live system's file points only at the live database. The connection test (step 7 above) names the database each one reached, so a mix-up shows straight away.
- **The system uses the details for one job only.** Only the part of the system that fetches data uses the login. No screen shows the password. The system only asks the questions in the register, and it opens every session as read-only, as a second guard on top of the read-only login.
- **No passwords in records or backups.** The audit trail records what was read and when, never the password. The settings file is not part of the database backups; MAIIC ICT keeps the password in its own password store.
- **Encrypted on the network if MAIIC wants it.** The connection already stays inside MAIIC's network, but encryption can be switched on from the system's side without changing anything in E-Banker.
- **Easy to change or stop.** When the password is changed every 90 days, MAIIC ICT updates the settings file and runs a one-line connection test. Locking the login in E-Banker stops the feed at once.

"""

t = TECH.read_text(encoding="utf-8")
a = "## 7. What we need from Virtual Galaxy"
assert a in t and "### 6.1 How the connection will be configured" not in t
t = t.replace(a, TECH_SEC + a, 1)
TECH.write_text(t, encoding="utf-8")

p = PLAIN.read_text(encoding="utf-8")
a = "## 7. What we need from the vendor"
assert a in p and "### 6.1 How the system will hold" not in p
p = p.replace(a, PLAIN_SEC + a, 1)
PLAIN.write_text(p, encoding="utf-8")
print("security sections added")
