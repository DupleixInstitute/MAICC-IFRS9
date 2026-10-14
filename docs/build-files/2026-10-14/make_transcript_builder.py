"""Make docs/build-files/build_transcript_integration.py from the audit-closure builder, with this session's range and header."""
import re
from pathlib import Path
SRC = Path(r"C:\xampp\htdocs\MAICC-IFRS9\docs\build-files\build_transcript_audit.py")
DST = Path(r"C:\xampp\htdocs\MAICC-IFRS9\docs\build-files\build_transcript_integration.py")
s = SRC.read_text(encoding="utf-8")

def sub(a, b):
    global s
    assert a in s, a[:70]
    s = s.replace(a, b)

sub(s.splitlines()[0], '"""Export the Claude Code session of 9 to 14 October 2026 (packs 3 to 10, the December ECL models against the directive, and the E-Banker integration pack) to PDF and Markdown."""')
sub('NAME = "Claude_Session_Transcript_Audit_Closure_2026-10-09"', 'NAME = "Claude_Session_Transcript_Feeds_and_Integration_2026-10-14"')
sub('START_MARK = "what else is left unbuilt?"', 'START_MARK = "result set exceeds the maximum size"')
s = s.replace("Claude session transcript: MAIIC raw query scripts (complete)", "Claude session transcript: MAIIC feeds and E-Banker integration (complete)")
s = s.replace("Claude session transcript: MAIIC raw query scripts", "Claude session transcript: MAIIC feeds and E-Banker integration")

i = s.index('HEADER = f"""'); j = s.index('"""', i + 12) + 3
header = '''HEADER = f"""This is the complete record of the part of a Claude Code session (9 to 14 October 2026) that followed the audit closure of 9 October. It is saved so that another member of staff, with or without an AI tool, can follow what was done, in what order, and why. The earlier part of the same session is in Claude_Session_Transcript_Audit_Closure_2026-10-09.

It starts at the question about SQL Developer's "result set exceeds the maximum size (100 MB)" message and runs to the saving of this transcript. The work falls into five stages, in time order:
1. Pack 3 and the 9 October returns: the SPOOL note for large exports, the RUN 0 date-format check, the Mega Farm runs, method A against method B, write-offs, the feed route setting and the arrears data.
2. Packs 7 to 9: the pack 7 results review, OVERDUE_DETAILS added as RUN 8 with its goal stated, the 100 MB failure and the split into 8a, 8b and 8c, and the pack 9 results review (CV_10 is the migration worksheet; CV_14 ties the old ledger to the GL).
3. The December 2025 ECL models against the RBM DFI Directive 2018: how the directive was applied in the models compared with the system.
4. The E-Banker integration pack: the five feed routes, the landing zone and its four tables, the endpoint (route 3), the bootstrap as the test harness, the read-only account set-up with a connection test, configuration and security, refresh, duplicates, governance, storage and backup, a dated timetable, pack 10 in the register, the system's move onto MAIIC's server, and the plain-language guide.
5. The documents humanised by two editor agents, checked against snapshots, rendered with a logo, justified text and a table of contents, and saved.

What is included: every user message, every reply shown to the user, every tool call with its full input (commands, scripts and file contents), and every tool result in full. Where a long result was stored by the tooling in a side file, that file is reproduced here.

What is left out: the assistant's internal reasoning, automatic system reminders, and image outputs (page renders used for visual checks).

How to follow it: the integration documents and figures are in 3. Project Execution/E-Banker Integration; the scripts that made them are in Build files/2026-10-14 next to this transcript, with a README, and in docs/build-files in the repository.

Times are UTC. Source: the session log {os.path.basename(SESSION)}, entries {start} to {end - 1}. Events: {len(events)}."""'''
s = s[:i] + header + s[j:]
DST.write_text(s, encoding="utf-8")
print("builder written:", DST)
