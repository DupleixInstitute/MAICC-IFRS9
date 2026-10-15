base = r'C:\xampp\htdocs\MAICC-IFRS9\docs\manuals\technical\\'

p = base + '03-data-model.md'
s = open(p, encoding='utf-8', newline='').read()
old = "| `rate_reset_events` | The rate-reset register (columns added 14 October 2026): each move of a floating loan's rate with the old and new rate and spread, the evidence, the suggested and confirmed classification (IN_CONTRACT or NEGOTIATED) and maker-checker (`status` DETECTED, PROPOSED, APPROVED). |"
new = ("| `rate_reset_events` | One row per rate event of a loan, shared by two owners (reconciled 14 October 2026). The reset pipeline (`ResetDetectionService`, `ResetScheduleService`, migration `2026_10_01_000000`) writes each prime-rate reset of a floating loan (`event_type` PLR_RESET, `reset_date` under `plr_mid_period`, `plr_effective_date`, the locked `spread_over_prime`, old and new contractual rate) and owns `status` (DETECTED, SCHEDULED, SOLVED, BLOCKED) and `new_schedule_version`. "
       "The register (`RateResetService`, migration `2026_10_14_230000`) adds the loan book's evidence (old and new rate charged and spread, `evidence`, `account`, `reporting_period`), writes each spread change as its own SPREAD_CHANGE row, and owns the maker-checker state `classification_status` (DETECTED, PROPOSED, APPROVED) with the suggested and confirmed classification (IN_CONTRACT or NEGOTIATED). "
       "Unique on contract, reset date and event type: a PLR reset and a spread change on one date are two events. |\n"
       "| `eir_rate_periods` | The reset pipeline's rate periods: one row per loan per stretch at one rate (prime, locked spread, contract rate) and the schedule version built for it. Not a source of the EIR: a floating loan's EIR is decision D38's monthly re-estimation (`eir_amortisation.eir_annual`). |")
if old not in s:
    raise SystemExit('03 missing')
s = s.replace(old, new, 1)
open(p, 'w', encoding='utf-8', newline='').write(s)

p = base + '06-eir-engine.md'
s = open(p, encoding='utf-8', newline='').read()
old = "`app/Services/Eir/RateResetService.php` writes `rate_reset_events`: every month a contract's loan-book rate moves is recorded with the PLR at both month-ends. A floating loan whose spread held is a PLR_RESET (suggested in-contract, B5.4.5); one whose spread moved is a SPREAD_CHANGE for a person to classify; a fixed-rate loan whose rate moved is written to the restructure register as a candidate. One person proposes IN_CONTRACT or NEGOTIATED with a reason, a different person approves, and a move in a locked period is refused; a NEGOTIATED move becomes a restructure candidate. The governed `rate_change_classification` can close NEGOTIATED altogether."
new = ("Rate resets are detected once. `app/Services/Eir/ResetDetectionService.php` is the one detector of prime-rate resets: for each loan whose contract master rate type is FLOATING (the same test D38 applies), every change of the PLR series since drawdown on the spread locked at drawdown (`margin_basis`), dated under `plr_mid_period` (by default the 1st of the month the change falls in, the whole month at the new rate, section 3.1), written as a PLR_RESET row of `rate_reset_events` with the loan's `eir_rate_periods`; a loan with no rate type or an unknown timing rule is held. "
       "`app/Services/Eir/RateResetService.php` runs it first and then sets each reset against the loan book's month-end rate: the pipeline's row carries the rates charged and the spreads as evidence; a spread that moved is a SPREAD_CHANGE row of its own, even on the date of a PLR reset; a move with the PLR that the pipeline has no reset for (the loan is held there, or the engine treats it as fixed-rate) is recorded as a PLR_RESET held from the pipeline (status BLOCKED, key MOVE|) with the reason; a fixed-rate loan's other moves go to the restructure register as candidates. "
       "`php artisan eir:restructure-candidates` (bootstrap and month-end) and `php artisan eir:detect-resets` both run this one detection. The register's maker-checker state is `classification_status`: one person proposes IN_CONTRACT or NEGOTIATED with a reason, a different person approves, and a move in a locked period is refused; a NEGOTIATED move becomes a restructure candidate. The governed `rate_change_classification` can close NEGOTIATED altogether. "
       "`php artisan eir:build-reset-schedules --user=` (`ResetScheduleService`, not run by the bootstrap or the month-end) re-amortises the remaining instalments at each PLR reset of a floating loan as a RESET schedule version and sets the pipeline's `status` to SCHEDULED; it skips spread changes, held moves and resets approved as negotiated, and holds a reset after a confirmed restructure. A Reset version is the re-estimated contractual flows only (it is what EIR as at a date shows as the remaining flows); neither it nor `eir_rate_periods` sets an EIR, so the rate change is applied once, by D38.")
if old not in s:
    raise SystemExit('06 missing')
s = s.replace(old, new, 1)
open(p, 'w', encoding='utf-8', newline='').write(s)
print('ok')
