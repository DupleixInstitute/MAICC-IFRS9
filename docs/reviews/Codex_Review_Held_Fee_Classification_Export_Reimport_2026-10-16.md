# Review: Held Fee Classification Export / Re-import

Date: 16 October 2026  
Reviewer: Codex  
Specification: MAIIC_Held_Fee_Classification_Export_Reimport_Specification_v1_2026-10-16.md

**Recommendation: revise before implementation.** Reusing the governed domain services is the right architecture. The proposed contract nevertheless depends on behavior the current code does not provide, and its identity and decision-state rules need changes before this can be a safe round trip.

This review compares the specification with the current controller, GL build, fee intake, candidate-dismissal service, precedence implementation, migrations, and relevant test source. It is a static design/code review; no application code was changed and no tests were run. Findings concern application behavior, not an independent opinion on IFRS accounting policy.

## Findings requiring resolution

### 1. High: held_id is not a durable identity across GL rebuilds

**Spec:** sections 5–6, particularly the claim that a stale file cannot match the wrong row.

`GlFeeBuildService::build()` deletes all of `fee_gl_unassigned` and inserts the current held queue again (`app/Services/Ebanker/GlFeeBuildService.php:126–141`). The table uses an auto-increment id; its unique business identifier is `external_transaction_id`, documented as `GL_04a:CUMVOUCH_DET_ID` (`database/migrations/2026_10_14_200000_eir_tranches_takeon_and_gl_fees.php:48–60`).

A normal rebuild therefore gives the same unresolved posting a new held_id. The old id no longer existing does not establish that someone resolved it. A reset/restore or a file from another database can also make a numeric id refer to a different posting. Merely looking up the id does not bind the uploaded decision to the posting Finance reviewed. An accidentally edited id that points at another existing row has the same problem.

**Required amendment:** identify the posting by its stable source/external transaction identity, retaining held_id as a convenience if useful. Either preserve ids with upserts or resolve the stable identity to the current held row. Define export environment/source scope and a version or server-verifiable fingerprint of the evidence. Reject changed evidence as stale rather than silently applying an old decision. Amount/date can be fingerprint inputs; they must not become fuzzy lookup keys. Candidate identity/evidence changes matter especially for candidate dismissals.

If a posting is absent from the held queue, consult durable resolution history and `contract_fees` by source/external id. Only a proven earlier resolution warrants ALREADY_RESOLVED. Unknown ids, disappeared source rows, and changed evidence need distinct reasons. Never fall back to amount/date matching.

### 2. High: candidate dismissal does not implement NOT_A_LOAN or MEGA_FARM

**Spec:** sections 1.4, 6 rule 1, and the accepted dismissal behavior.

`dismissCandidate()` requires a contract_id and calls `HeldFeeEvidence::dismiss()`. That service verifies that the supplied account equals the current candidate, records a dismissal keyed by external transaction id plus account, and leaves the posting held (`app/Services/Eir/HeldFeeEvidence.php:57–85`). Its meaning is only “this posting is not this candidate loan's fee.” It stops that candidate blocking the loan's EIR; it does not conclude that the posting belongs to no loan or to Mega Farm. The existing readiness test explicitly asserts that the posting stays held (`tests/Feature/Eir/EirFeeReadinessControlTest.php:42–60`).

The proposed non-TIE rows must have blank contract_id, so they cannot directly satisfy that path. Substituting the current candidate internally still cannot dismiss a row with no candidate, and would silently dismiss a new candidate if the export is stale. A dismissed row also still exists in the queue, contradicting the spec's “already dismissed means resolved” assumption.

**Required amendment:** choose and document the intended state machine. Candidate rejection should be an explicit candidate-specific decision, preserve the held posting, and carry the candidate identity being rejected. If NOT_A_LOAN and MEGA_FARM are terminal posting exclusions, they need a governed posting-level disposition, durable storage keyed by source identity, audit evidence, and explicit rebuild/readiness/reconciliation behavior. They cannot truthfully be implemented by reusing candidate dismissal alone. Preserve section 9's exclusion of bulk source-overlap decisions.

### 3. High: the claimed manual batch/amount consistency check does not exist

**Spec:** sections 1.4 and 6, accepted TIE paragraph.

`tieHeld()` validates the request and calls `GlFeeBuildService::assignHeld()`. `assignHeld()` checks a reason of at least 10 characters, the held row's existence, and account existence in either `contract_eir` or `loan_books`, then imports the posting (`app/Services/Ebanker/GlFeeBuildService.php:209–238`). It does not rerun the automated batch/amount assignment rules, require membership in loans_in_batch, or explicitly check that the loan is in scope.

This is a mismatch in the specification's claimed control, not a reason to assume manual ties must satisfy the automated matching rules: these rows are held precisely because automation could not settle them.

**Required amendment:** explicitly define the manual evidence and scope checks the business requires. If a new consistency check is required, implement it in the shared domain operation for both screen and file, with rules that permit legitimate no-loan-in-batch cases. Otherwise correct the spec to describe a governed human override based on recorded evidence. Specify the authoritative in-scope predicate; mere account existence does not implement it. Keep automated assignment rules unchanged unless separately authorized.

### 4. High: an existence check before the write is insufficient under concurrency

**Spec:** section 6 rule 1 and row-isolated results.

`assignHeld()` reads the held row before opening its transaction. `FeeImportService` checks for an existing source/external id and skips it, while the database also has a unique source/external transaction constraint (`database/migrations/2026_08_03_000000_strengthen_eir_fee_classification.php:49`). These are useful protections, but not a complete decision protocol.

Two imports, a screen tie and an import, or a rebuild and an import can interleave. If an existing fee is skipped, assignHeld still deletes the held row and logs a tie to the requested account without verifying that the existing fee belongs to that account. A database uniqueness race can instead raise an exception. Neither outcome should be reported as an ordinary successful tie. This risk concerns false results and audit assertions as well as duplicate prevention.

Precedence is applied after assignHeld's insertion/deletion transaction commits. A failure there can leave a durable tie with incomplete precedence processing while the upload reports a failure; replay then encounters a missing held row.

**Required amendment:** make identity resolution, stale-state validation, decision claim, fee write or disposition, and audit atomic per posting, with locking/conditional updates and rebuild coordination. Inspect any existing source/external-id fee: same completed decision is idempotent; a different target is a conflict, never a successful new tie. Define transaction/recovery handling for precedence and readiness side effects, and report success only when the promised safeguards have completed. Handle expected per-row conflicts without aborting unrelated rows. The unique constraint remains the last defense, not the whole workflow.

### 5. Medium: optional TIE reason contradicts the shared service

**Spec:** sections 5–6 allow reason_note to be blank for TIE.

Both the screen request and assignHeld require a reason of at least 10 characters; the controller caps it at 500 (`app/Http/Controllers/EirFeeClassificationController.php:105–115`). A TIE with only contract_id therefore cannot follow the promised existing path. A generated “imported from file” reason establishes provenance, not why that account owns the fee.

**Required amendment:** require a substantive trimmed reason_note for TIE as well, using the screen's limits, or explicitly approve a changed shared policy. Validate note types and lengths for every decision. Keep file provenance in separate audit metadata.

### 6. Medium: unchanged export acceptance contradicts the validation rules

**Spec:** section 6 rules 2–3 versus section 8 acceptance 1.

An unchanged export has blank decision, contract_id and reason_note. Rule 2 rejects “neither”; treating a blank decision as DEFER instead fails rule 3's required note. The five outcomes do not include an unchanged/no-action state.

**Required amendment:** define an explicit no-action rule before decision validation. Recommended: all editable cells blank means UNCHANGED, with no write and no audit decision. Add that outcome, or explicitly define how a no-action row is represented in the existing result categories without implying that Finance deliberately deferred it. Reject a blank decision accompanied by an entered account or note. Explicitly allowlist the four named decisions, define trimming/case behavior, and reject all other values.

### 7. Medium: DEFER has no persistent round-trip representation

**Spec:** section 6 says the note will appear on the next export, but section 5 exports no prior decision note and initializes reason_note blank.

The current held table is rebuilt from source and has no deferral-note field. Writing the note onto an ephemeral held row, or replacing the automated reason, would lose or confuse evidence on rebuild.

**Required amendment:** store deferral notes in durable decision history keyed by stable posting identity, separately from the automated hold reason. Export the latest note, author and time in clearly named reference columns while keeping new input fields distinct. Specify whether repeated identical DEFER uploads create new history or return a no-change result.

## Recommended re-import contract

Before mutation, validate the file format, required headers, schema version and source/environment scope. Define supported spreadsheet formats, sheet selection, upload/row limits, and duplicate-header behavior. Preserve identifiers as text and treat imported reference cells as untrusted. Uploaded names must remain labels only; they must never select or validate the target loan.

For each physical data row:

1. Resolve and validate stable posting identity and exported evidence version. Require an integer held_id if retained; distinguish unknown, stale, conflicting and proven resolved states.
2. Detect repeated posting identities in the file before execution. Reject conflicting duplicate decisions together so file order cannot decide the winning target. Define whether identical duplicates are rejected or reported as duplicates; never execute them twice.
3. Apply the explicit unchanged-row rule. Validate decision against an allowlist, account presence/absence, and trimmed reason limits for actionable rows.
4. Normalize a TIE account with ContractId::normalise, then check existence and the explicitly defined scope predicate. Preserve account text during spreadsheet handling; normalization cannot recover digits already lost through numeric rounding. Do not normalize held_id as a loan account number.
5. Recheck current state within the shared domain operation and apply one atomic decision. Use server-held posting amount, sign, GL, date and source when writing the fee. Uploaded reference values may detect stale evidence but must never overwrite source facts.
6. Return original row number, posting identity, requested decision, outcome and actionable reason. File-wide structural errors should precede all mutation; expected invalid data in one row should not prevent valid rows succeeding. Define how unexpected infrastructure failures and partial completion are reported and safely retried.

For spreadsheet export, write account identifiers as text and escape untrusted narration/name/note strings as literal text, including formula-leading values. Define a deterministic latest-loan-book selection with at most one name row per candidate, so joins cannot duplicate held postings. Ensure both export and import receive their intended middleware explicitly: the current controller's default settings restriction would otherwise also apply to newly added methods.

## Answers to the three requested questions

**Section 6 validation:** incomplete as written. The missing stable identity protocol, incorrect dismissal semantics, nonexistent promised consistency check, optional TIE reason, blank-row contradiction and concurrency behavior are material. The remaining recommendations make the contract executable and its results auditable.

**Reuse tieHeld/dismissCandidate rather than direct contract_fees writes:** reuse the underlying domain operations, not HTTP controller methods. The TIE architecture is correct: assignHeld preserves the GL source/external id, uses FeeImportService, lands the fee PENDING, audits it and reapplies FeeSourcePrecedence. Same-source uniqueness/idempotency and cross-source precedence address different duplicate risks; both must remain. This does not make the current operation concurrency-safe automatically. Candidate dismissal is reusable only for rejecting a particular candidate, not terminal posting exclusion. If shared services need strengthening, apply it to both entry points.

**held_id rather than amount/date:** rejecting amount/date lookup is correct, but held_id alone is insufficient in this repository because the held queue is rebuilt. Use durable source posting identity with stale-evidence detection and durable resolution history. Retaining held_id as a convenience is safe only with those checks or a changed stable-id lifecycle.

## Acceptance additions before coding

- Unchanged export produces no decisions, no fees and no rejections under the chosen no-action rule.
- Export, rebuild the queue, then import: the same posting is correctly identified or explicitly rejected as stale; it is never mistaken for a resolved or different posting.
- Change an id, use a different environment's file, or change source evidence/candidate after export: no decision reaches the wrong posting/candidate.
- Simultaneous screen/file ties to different loans yield one durable assignment and a truthful conflict/resolved result; repeated uploads do not assert a second tie.
- Conflicting duplicate rows do not become first-row-wins decisions.
- TIE without an adequate reason, unknown/out-of-scope loan, unknown decision and malformed row are individually rejected while valid rows proceed.
- Candidate rejection remains distinct from terminal non-loan/Mega Farm disposition, including rows without candidates; all intended decisions survive rebuilds.
- DEFER note survives rebuild and appears on the next export without replacing source evidence.
- A tie with an existing workbook/LOS counterpart reapplies precedence, retains overlaps/differences for their existing governed queue, and remains PENDING for separate classification/review.
- Inject a precedence/audit failure and verify the chosen atomicity or recovery contract and safe retry behavior.

No code should rely on the current spec's stronger claims about identity, dismissal or manual checks until these points are corrected.
