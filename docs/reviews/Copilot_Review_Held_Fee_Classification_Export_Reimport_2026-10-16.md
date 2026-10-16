# Copilot review: held fee classification export/re-import

Date: 16 October 2026

Reviewed: `docs/MAIIC_Held_Fee_Classification_Export_Reimport_Specification_v1_2026-10-16.md`, especially sections 1.5, 4, 5, 6 and 8. This is a design review only; no application code or data was changed.

## Conclusion

The core design is sound: use the existing governed tie/dismiss actions, identify records by `held_id`, and never use a customer name as a match key. The decision to omit `customer_id` and retain `customer_name` as a human-readable label is reasonable. I would clarify the round-trip behavior and the spreadsheet contract before implementation; as written, the first acceptance criterion conflicts with the import validation rules.

## Findings

### 1. High: unchanged export cannot meet acceptance criterion 1 as specified

Section 5 intentionally exports blank `decision` and `contract_id` cells. Section 6 defines `TIE` and the other decision values, but does not define blank as a valid no-op. Its validation rule says a `TIE` requires an account and any other decision requires it blank, but it does not say what to do when `decision` itself is blank. Section 8.1 nevertheless requires uploading an unchanged export to reject nothing and report each row's current state honestly.

Define blank decision as an explicit no-action outcome (for example `UNCHANGED`/`NO_ACTION`, not a resolution), or change the acceptance criterion to expect a per-row rejection/incomplete-decision result. The former better matches the stated workflow of exporting the whole queue and safely uploading the file after editing only selected rows. Add an acceptance test that uploads an untouched file and verifies every row remains unresolved and no audit/tie/dismiss write occurs.

### 2. High: the spreadsheet/file contract is underspecified for identifiers and amounts

Section 5 gives logical columns, but not the delivered file type or serialization rules. `contract_id` and `loans_in_batch` contain account numbers with leading zeroes; spreadsheet software can silently strip those zeroes or convert long identifiers to scientific notation. Dates, decimal precision, CSV quoting/encoding, and the delimiter/escaping for flattened `loans_in_batch` are also undefined. That can turn a valid decision into an incorrect or rejected one, or make the evidence hard to read.

Specify a versioned CSV or XLSX contract. At minimum define ISO date representation, amount precision and decimal convention, UTF-8/quoting rules if CSV, and identifiers as text on export and import. Define how `loans_in_batch` is serialized, including an unambiguous representation for empty values. Add a round-trip test with leading-zero account numbers, a long identifier, a narration containing commas/quotes/newlines, and decimal amounts.

### 3. Medium: deferral rationale and candidate-name provenance are not visible on later exports

Section 6 says `DEFER` records `reason_note` so the next export explains why the item remains open. Section 5 does not identify a deferred-note column or specify which stored field supplies it; the existing `reason` column is described as the original automated hold reason. Those are distinct facts. Also, `candidate_customer_name` is looked up from the latest `reporting_period`, but that period is not exported, so Finance cannot tell whether the label is current or how it relates to the held posting.

Specify durable storage and re-export of the latest deferral note separately from the original hold `reason`, with clear semantics if there are multiple deferrals. Consider exporting the candidate name's `reporting_period` (or a clear as-of date) and label the name explicitly as informational and non-authoritative. Keep the import from reading either name or period.

### 4. Medium: acceptance coverage omits the non-tie decisions and several key safety cases

Section 8 tests a successful `TIE`, stale manual resolution, basic invalid inputs and the customer-name rule, but does not exercise accepted `NOT_A_LOAN`, `MEGA_FARM` or `DEFER` outcomes. It also does not prove that a rejection in one row leaves other rows processable, that an unknown/nonexistent `held_id` is reported without mutation, or that duplicate/conflicting rows for the same `held_id` in one upload have deterministic handling. These are central to a bulk-import feature.

Add acceptance cases for each decision and its required reason, asserting the resulting held-queue state, persisted rationale/classification, audit attribution and re-export behavior. Add mixed-file coverage (valid tie + invalid row + defer), duplicate `held_id` handling, nonexistent ID, stale tied and stale dismissed rows, and a row whose batch/amount re-check fails. Specify whether duplicate IDs reject all duplicates or process exactly one; do not let file order silently choose the decision.

### 5. Medium: `customer_name` is a useful label, but the design should guard against anchoring

The section 4 decision is well-founded. `contract_id` is the E-Banker key; omitting `customer_id` avoids a redundant identifier, and importing by `held_id` plus the user-entered `contract_id` keeps names out of matching and validation. Retaining the candidate's name can help a reviewer interpret an otherwise opaque account number.

The remaining human-factors risk is the one the spec itself identifies: a plausible name beside an unconfirmed candidate can make a guess feel confirmed. The export should make the column's label-only status conspicuous in its header/instructions, keep it visually distinct from editable decision fields, and ideally show the reporting period used for the lookup. Acceptance criterion 8.5 should test behavior, not just column presence: changing or removing `candidate_customer_name` in an uploaded file must not affect the selected account, validation, or result. The implementation should explicitly whitelist the import fields rather than deserialize a full row into decision data.

## Acceptance additions to consider

- Untouched export is a no-op: no rejection, resolution, audit write, or mutation; each row reports a defined no-action outcome.
- Name and other descriptive/export-only columns may be edited or removed without affecting import decisions; only the declared decision fields are consumed.
- All four decisions (`TIE`, `NOT_A_LOAN`, `MEGA_FARM`, `DEFER`) have successful-path tests, required-field tests and auditable outcomes.
- A mixed upload processes independent rows and reports each result; duplicate IDs, stale IDs and concurrent resolution cannot create a second or conflicting fee tie.
- A subsequent export distinguishes the original hold reason from the most recent deferral note and identifies the as-of period for the candidate label.
- File-format tests preserve account identifiers, dates, narration and amount values across export and re-import.

## Overall recommendation

Proceed after resolving the blank-decision/no-op contradiction and defining the import file contract. Preserve the section 4 choice: drop `customer_id`, retain `customer_name` strictly as a visibly non-authoritative candidate label, and prove in tests that it cannot influence an import decision.