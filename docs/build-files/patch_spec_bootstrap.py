"""Spec v4 section 6: the bootstrap importer stays as a build method beside the ledger derivation; both are governed options."""
p = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\3. Project Execution\specs\MAIIC_EIR_Engine_Specification_v4_2026-10-07.md"
s = open(p, encoding="utf-8").read()
def rep(old, new):
    global s
    assert old in s, old[:70]
    s = s.replace(old, new)

# 6.1 plain language
rep("The design has three parts. A **landing zone**: raw tables that mirror E-Banker's, loaded exactly as received and never edited. A **derivation**: one command that builds the monthly loan book from the landing zone, re-runnable whenever a rule or a source row changes. And a **feed**: the one door through which every pack of extracts enters, whichever road it came by, with gates that refuse a pack that does not tie.",
    "The design has three parts. A **landing zone**: raw tables that mirror E-Banker's, loaded exactly as received and never edited. A **build** of the monthly loan book from the landing zone by one of two methods, both kept and both available at any time: the **bootstrap**, which takes the stored Loan Book Report's latest run for each month as it is, and the **derivation**, which builds the row from the ledger; one command, re-runnable whenever a rule or a source row changes. And a **feed**: the one door through which every pack of extracts enters, whichever road it came by, with gates that refuse a pack that does not tie.")

# 6.2 heading and intro: two methods
rep("### 6.2 What is derived from what\n\nFor every account and every month-end from the take-on at 31 July 2024 to the latest month, the loan book row is built as follows.",
    "### 6.2 The two build methods\n\n**Method A, the bootstrap.** The loan book row is the stored Loan Book Report's latest run for that account and month-end (`ebanker_loan_book_runs`, highest row id per account-month), column for column: E-Banker's own carrying amount, principal, interest to date, repayments, arrears, status and rate, mapped once to `loan_books` through a saved template. It is the simplest method, it is what MAIIC's own report says, and it is already tied to the ledger on 2,264 of 2,264 account-months. It covers December 2024 onward, which is where the stored report begins; the months from the take-on at 31 July 2024 to November 2024 are built from the balance history and the ledger and marked as derived.\n\n**Method B, the derivation.** For every account and every month-end from the take-on to the latest month, the row is built from the primary records as follows.")
rep("Beside every derived carrying amount the stored loan book's figure is kept, and a difference is a flagged row, never a silent choice. The months before December 2024, which the stored report does not hold, are built from the same sources without exception; they are no longer \"derived and marked\", they are the same as every other month.",
    "Under method B every month from July 2024 is built the same way, and beside every derived carrying amount the stored loan book's figure is kept; a difference is a flagged row, never a silent choice.\n\n**Choosing between them.** The method in force is a Governance Centre setting (`loan_book_build_method`, section 4.2) with the two methods as its options, changed under maker-checker with an effective date; a month is built by the method in force on its period end, and the method used is recorded on every row. Both methods read the same landing zone and pass the same gates, so switching changes no import, no route and no screen. Dupleix's recommendation is method B, because it covers every month from the take-on on one basis and traces every figure to a posting; method A is the right choice while the derivation is being proven, for a quick reload of a single month, or whenever MAIIC prefers the report's own figures to stand. Whichever is in force, the other remains available on the feed screen for a named month.")

# 6.6 derivation -> building
rep("### 6.6 The derivation\n\n`eir:derive-loan-books {from} {to}` builds `loan_books` for the months asked, from the landing zone, as 6.2 states. It is idempotent",
    "### 6.6 Building the loan book\n\n`eir:build-loan-books {from} {to} {--method=bootstrap|derive}` builds `loan_books` for the months asked, from the landing zone, by the method in force unless one is named (6.2). It is idempotent")
rep("one person asks for the derivation, a second approves it, and the audit log records the pack hashes it read. Re-deriving a month already run prints every difference first;",
    "one person asks for the build, a second approves it, and the audit log records the method and the pack hashes it read. Rebuilding a month already run, by either method, prints every difference first;")
rep("The ECL columns on `loan_books` (stage, LGD, forward-looking adjustments) are not touched by the derivation.",
    "The ECL columns on `loan_books` (stage, LGD, forward-looking adjustments) are not touched by either method.")

# 6.7 history
rep("3. **Derive on a copy of the production database** for every month from July 2024 to August 2026. December 2025 to August 2026 are already loaded from the Excel reports; the derivation prints every difference before it overwrites.",
    "3. **Build on a copy of the production database** for every month from July 2024 to August 2026, by method A first (the stored report is the quickest proof) and then by method B, and compare the two: the carrying amounts must agree on every account-month except the flagged rows of section 3.5. December 2025 to August 2026 are already loaded from the Excel reports; the build prints every difference before it overwrites.")
# 6.8 feed screen
rep("the quarantine; and the Derive action with its approval.", "the quarantine; and the Build action (method A or B, a month or a range) with its approval.")
# 6.3 'read by the derivation only'
rep("**The zone is read by the derivation only**: no screen edits it.", "**The zone is read by the build only**: no screen edits it.")
# 4.2 settings: add a row after the feed route
rep("| How E-Banker data arrives: the feed route (`ebanker_feed_route`) | Route 1, manual pack | Decided D22: all five routes of section 6.5 are built; MAIIC switches the route in force at any time; routes 4 and 5 need Dr Thom and ICT | O9 |",
    "| How E-Banker data arrives: the feed route (`ebanker_feed_route`) | Route 1, manual pack | Decided D22: all five routes of section 6.5 are built; MAIIC switches the route in force at any time; routes 4 and 5 need Dr Thom and ICT | O9 |\n| How the loan book is built (`loan_book_build_method`) | Method B, derived from the ledger | Decided D22: both methods of section 6.2 are built; MAIIC switches at any time; the method used is recorded on every row | O8 |")
rep("| Loan books before December 2025 | Stored loan book history, every month-end | Decided D22 | O8 |",
    "| Loan books before December 2025 | Stored loan book history, every month-end | Decided D22; superseded by the build-method setting below, which covers every month | O8 |")
# D22
rep("with gates that refuse a pack that does not tie, and one re-runnable derivation. The Excel report importer becomes a fallback. | Dupleix, 7 Oct 2026; amended the same day from a bootstrap of the stored report |",
    "with gates that refuse a pack that does not tie, and one re-runnable build by either of two methods, the bootstrap of the stored report or the derivation from the ledger, chosen at any time. The Excel report importer becomes a fallback. | Dupleix, 7 Oct 2026; the landing zone and the second method added the same day at Edward's direction, the bootstrap kept as an option |")
# 11.3 feed screen label
rep("| | E-Banker Feed (queries, loads, watermarks, derive) |", "| | E-Banker Feed (queries, loads, watermarks, build) |")
# P4b
rep("route 1 and the feed screen, the derivation;", "route 1 and the feed screen, the build by both methods;")
# baseline row
rep("| Derived loan book against the stored loan book's carrying amount, every month-end from December 2024 | 2,264 of 2,264 agree; every difference is a flagged row with a named cause |",
    "| Loan book built by method B against the same month built by method A, every month-end from December 2024 | 2,264 of 2,264 carrying amounts agree; every difference is a flagged row with a named cause |")
# glossary
rep("- **Landing zone**:", "- **Bootstrap (method A)**: building a month's loan book from the stored Loan Book Report's latest run, as E-Banker printed it.\n- **Derivation (method B)**: building it from the ledger and the masters, with E-Banker's own arrears fields.\n- **Landing zone**:")
open(p, "w", encoding="utf-8").write(s); print("bootstrap kept as method A; build-method setting added")
