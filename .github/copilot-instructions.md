# Copilot instructions: MAIIC IFRS 9 / EIR engine

## What this repository is

A Laravel 12 / Vue 3 (Inertia) application for MAIIC (Malawi Agricultural and Industrial Investment Corporation): an IFRS 9 effective-interest-rate (EIR) engine with an ECL module, built by Dupleix Institute. MAIIC's loans live in the E-Banker core banking system (Oracle); its statutory accounts are kept in QuickBooks. E-Banker data arrives as read-only SQL extracts ("packs") landed through `app/Services/Ebanker/`.

## Where the truth is

- The specification: `docs/MAIIC_EIR_Engine_Specification_v4_2026-10-07.md` (branch `eir_revenue_recognition`). Section 1 is the change log, section 4.2 lists every Governance Centre setting, section 9 the acceptance baselines.
- Review brief (what to check and how): `docs/REVIEW_BRIEF_Spec_v4_for_Codex_2026-10-12.md`.
- The extracts as received: `docs/bootstrap/`. The scripts behind every figure: `docs/build-files/` (see its README). Session transcripts: `docs/sessions/`.
- Governed settings: `app/Services/Eir/GovernanceService.php` (`catalogue()`), seeded by `database/seeders/GovernanceSettingsSeeder.php`. The E-Banker query register: `database/seeders/EbankerQuerySeeder.php`.

## Conventions

- **ASCII hyphens only.** Never use em dashes or en dashes in code, comments, docs or commit messages; a pre-commit check refuses them.
- **No hardcoded or fallback figures.** A missing input fails closed and is named; never substitute a default amount, rate or ratio. Correctness over matching an expected number.
- **Choices are governed.** A new option or threshold goes into the Governance Centre catalogue with a default, an options list (each option at most 60 characters) and a description that names the spec section; it changes only through maker-checker.
- **Plain language first** in documentation: each spec section opens with a plain-language paragraph; the words "Agreed", "Decided and seeded" and "Proven" are used as section 0 defines them.
- **Amounts** in kwacha with thousands separators; two decimals where the source has them.
- **Tests** for services use an in-memory SQLite connection with the tables created in `setUp()` (see `tests/Feature/Eir/TrialBalanceMovementServiceTest.php`); run `php artisan test tests/Feature/Eir tests/Feature/Ebanker` before proposing a change.
- **E-Banker facts to respect**: debits are negative in the ledger; type 301 is a drawdown, 303 interest, 305 a repayment (but see spec section 3.8: a type-305 credit can be a write-off); fees are credited to GL 4871 and 4873 and often never touch the loan account (spec 3.7 and 7.8); status H means written off (spec 3.8).

## When asked to review

Do not edit the specification. Write findings to `docs/REVIEW_Spec_v4_<reviewer>_<date>.md` as a table: section, the text quoted, the issue, the evidence (a file and row, or a figure that can be reproduced), and the suggested correction. Say plainly where a claim cannot be checked from the repository (some evidence is held in MAIIC's OneDrive, not here).

## When asked to change code

Keep the change inside the files the task names; other uncommitted work in the tree may belong to someone else. Ship a screen change with the backend change it depends on, and update the in-app help (`database/seeders/data/help_user_content/`) and the spec section in the same change.
