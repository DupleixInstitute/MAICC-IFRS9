p = 'database/seeders/data/help_user_content/06-eir-and-revenue-recognition.php'
raw = open(p, 'rb').read().decode('utf-8')
crlf = '\r\n' in raw
s = raw.replace('\r\n', '\n')


def ins_before(line_marker, anchor, text):
    """Insert text before anchor, inside the body whose line contains line_marker."""
    global s
    lines = s.split('\n')
    hits = [i for i, l in enumerate(lines) if line_marker in l and anchor in l]
    assert len(hits) == 1, (line_marker, anchor, hits)
    i = hits[0]
    assert lines[i].count(anchor) >= 1
    lines[i] = lines[i].replace(anchor, text.replace(chr(39), chr(92) + chr(39)) + anchor, 1)
    s = '\n'.join(lines)


# EIR data: schedule generation for tranche facilities and the take-on workbook schedule
ins_before("'body' => '<p>EIR Data is the hub", "<h4>To view a schedule</h4>",
    "<h4>A facility drawn in tranches, and a take-on loan</h4>"
    "<p>Where the tranche register on Drawdowns shows the money went out on more than one date, or after the date interest starts, the draft is built on the drawdowns, not on the whole facility from the signing date: each tranche is added on its own date and earns interest from that day, a period with a tranche in it is charged on its actual days, and the level instalment is sized again over the instalments left whenever a tranche arrives, so the schedule repays exactly what was lent. A due date that falls before anything is drawn has nothing due and is left out. While a facility was only partly drawn, interest could have been charged on the approved amount or on the drawn balance; where neither the contract nor its scheme says which, the platform reads it from the interest E-Banker posted in those months (the narration carries the rate, for example \"IntDR Fm 02-MAY-25 To 31-MAY-25 @ 29%\") and records <code>interest_basis=OBSERVED_EBANKER_INTEREST</code> with both figures in the audit log; with no postings to read, the draft is refused with <b>INTEREST_BASIS_MISSING</b> rather than assumed. The Governance Centre setting <b>Interest basis on a partly drawn facility</b> can instead take the drawn balance everywhere. A facility still partly drawn today with no flags is refused as before (open choice O6).</p>"
    "<p>A take-on loan keeps the schedule from the take-on workbook (source TAKEON_WORKBOOK); <b>Generate</b> never writes over it. It arrives on Schedule Review as PENDING_REVIEW and is approved like a generated draft.</p>")

# Fee Classification: the GL fee route, the precedence and the take-on fees
ins_before("'body' => '<p>Fee Classification is the work queue", "<h4>What happens next</h4>",
    "<h4>Where the fee lines come from, and which one counts</h4>"
    "<p>Besides EIR Data Intake, fee lines arrive from three places. <b>E-Banker GL fee posting</b>: the bootstrap and <code>php artisan eir:build-gl-fees</code> read the postings to the fee GLs 4871, 4873, 4874 and 4875 (GL_04a) and every leg of the same batches (GL_04b), and tie each fee to its loan through the batch: the only loan in the batch, or, with several, the loan whose own debit is the fee's amount, then the loan whose account name the narration carries. A fee credited by transfer with no loan in the batch (most 2025 and 2026 transfers) is not guessed: it is held with the reason and, where its narration carries a loan's account name and that loan drew money that day, with that loan as a candidate, for a person to tie with a reason. <b>E-Banker LOS charges</b> (P2_07). <b>The take-on workbook</b>: the fees Finance entered for the loans taken on in July 2024, one line per fee column, with the cell it came from.</p>"
    "<p>One fee, one source: the Governance Centre setting <b>Which source a contract's fees come from</b> (GL posting first by default) decides, contract by contract, which source holds its fees; a line from a lower source is marked superseded, kept and shown, and never enters the EIR or the fee GL check, so a fee in GL_04 and in the charges table counts once. Where the two disagree the difference is reported for Finance. The take-on workbook's fees never compete with the others. Every line still arrives PENDING: no fee affects an EIR until it is classified and reviewed here.</p>")

# EIR Calculations: what the solve does for tranches and take-on loans, and the new codes
ins_before("'body' => '<p>EIR Calculations solves", "<h4>To approve and lock one contract</h4>",
    "<h4>Facilities drawn in tranches and take-on loans</h4>"
    "<p>A facility signed before any money went out is solved from its first drawdown, with each later tranche paid out on its own date; a fee debited to the loan account in the drawdown batch is part of that drawdown, not a tranche of its own. With no fee the EIR of such a facility is its contractual rate (Mphunzitsi SACCO read 41.3 percent against 29.0 before 14 October 2026, and reads 29.1 now). A take-on loan is solved on the cash flows the take-on build read from the workbook (every line's principal and interest, every rise in the balance as money advanced on its date, every fall outside the instalments as money received) from the date the workbook starts, with the take-on workbook's fees once reviewed. The take-on build first checks that the workbook with no fee solves to the rates its own lines charge (setting <b>Take-on EIR: the check before it is used</b>); a block that fails is refused and the contract shows <b>TAKEON_NOT_RECOMPUTED</b> with the reason, for example that the schedule does not retire the loan. A floating take-on loan's EIR then moves each month from the rate its workbook schedule carries to the rate E-Banker charged. A loan migrated at the take-on of 31 July 2024 is rolled forward from August 2024, from the balance at the take-on.</p>")

# Drawdowns: the register rebuilt from the ledger
ins_before("'body' => '<p>Drawdowns is where the money actually paid out", "<h4>What happens next</h4>",
    "<h4>The register built from the ledger</h4>"
    "<p>The bootstrap also builds the register from E-Banker's ledger: one row per drawdown date, from the type-301 postings. A posting that is a fee capitalised into the loan (a debit in a batch that only credits a fee GL, or narrated as an arrangement or legal fee) is added to the drawdown of its date, and the row records how much of it is fee; it is never a tranche of its own. The opening balance posted at the take-on of 31 July 2024 is the migration, not money lent that day, and is not a drawdown. Each rebuild replaces the rows an earlier build wrote (references starting CUMVOUCH:); rows loaded from a file are left alone.</p>")

out = s.replace('\n', '\r\n') if crlf else s
open(p, 'wb').write(out.encode('utf-8'))
print('ok', crlf)
