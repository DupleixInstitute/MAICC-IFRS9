from pathlib import Path
F = Path(r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\3. Project Execution\E-Banker Integration\Data access request - E-Banker read-only access (route 4) - 14 Oct 2026.md")
t = F.read_text(encoding="utf-8")
def sub(a, b):
    global t
    assert a in t, "anchor missing: " + a[:90]
    t = t.replace(a, b, 1)

sub("- **The system is moving onto MAIIC's server.** The IFRS 9 system is about to be installed on a MAIIC server.",
    "- **The system is moving onto MAIIC's server.** MAIIC ICT has been added as a collaborator on the system's code repository and has already cloned it on a MAIIC server. The IFRS 9 system is now being set up there.")

release = """### 2.2 How each change reaches MAIIC's server

The copy of the system on MAIIC's server is a clone of the code repository, so **nothing Dupleix builds reaches it until MAIIC ICT pulls it**. Every piece of the integration work (the route 2 script, the route 4 connection and query runner, the bootstrap option, any route 3 hardening) follows the same release steps before it is tested on MAIIC's server:

| Step | Who | What |
|---|---|---|
| 1. Build and prove at Dupleix | Dupleix | The change is built, its automated tests pass, and the bootstrap passes all nine golden ties on Dupleix's copy |
| 2. Push | Dupleix | The change is pushed to the repository with a note of what it does and which step of this plan it belongs to; MAIIC ICT is told it is ready |
| 3. Pull on MAIIC's server | MAIIC ICT, with Dupleix watching over AnyDesk | `git pull` on the server's clone; then `php artisan migrate --force` for any new database tables or columns (and `composer install` when the release note says the libraries changed) |
| 4. Re-run the bootstrap on MAIIC's server | MAIIC ICT, with Dupleix | `php artisan eir:bootstrap` on the bootstrap database: all nine golden ties must pass on MAIIC's server before the change is tested there |
| 5. Test | MAIIC ICT and Dupleix | The tests of section 9 for that step |

A change that fails step 4 is not tested and does not go further; Dupleix fixes it and the steps start again. Production is updated by the same pull only after the tests pass, and never by copying files onto the server. The repository's history is the record of exactly what is running on MAIIC's server at any date.

"""
sub("## 3. Background: the five feed routes", release + "## 3. Background: the five feed routes")

# sequence table: note the pull before each test stage
sub("| | Route 4 on test: the Oracle client and connection installed on the system's server;",
    "| | **Release before every test**: each piece of the build is pushed by Dupleix and pulled by MAIIC ICT onto its server, migrated, and the bootstrap re-run there with all nine ties passing (section 2.2) | Dupleix and MAIIC ICT | Before each test below |\n| | Route 4 on test: the Oracle client and connection installed on the system's server;")
# timetable: explicit pull rows
sub("| **B** | Testing on the test copy into the bootstrap database;",
    "| **B** | **Pull onto MAIIC's server**: the route 4 build pushed; MAIIC ICT pulls, migrates and re-runs the bootstrap there (section 2.2) | Dupleix and MAIIC ICT | Fri 13 November |\n| **B** | Testing on the test copy into the bootstrap database;")
sub("| **B** | Route 2 script written by Dupleix and installed on a MAIIC server against the test copy |",
    "| **B** | Route 2 script written by Dupleix, pushed, pulled by MAIIC ICT and installed on a MAIIC server against the test copy |")
F.write_text(t, encoding="utf-8")
print("release steps added")
