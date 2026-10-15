P = 'C:/xampp/htdocs/MAICC-IFRS9/docs/MAIIC_EIR_Engine_Specification_v4_2026-10-07.md'
s = open(P, encoding='utf-8').read()
a = "**What is asked for.** `GL_05` (pack 4, RUN 7):"
b = """**Built (13 October 2026).** The rule above is one class, `WriteOffClassifier`, asked by the loan book build (method B), the contract inputs and the EIR's ledger cash, so all three agree. It reads the write-off batches from `GL_05` (received in pack 4 and landed: three batches debit GL 1064, TRF 95 of 31 August 2024, TRF 26 of 31 January 2026 and TRF 3 of 29 June 2026) and the status from the account master. On the demo database it finds 22 write-off postings, 60,778,466.86: the two type-300 write-offs of 2024 (22,286,434.32) and the 20 ACCOUNT CLOSURE credits of TRF 3 (38,492,032.54), which the ledger cash had loaded as repayments and now reclassifies as write-offs. A write-off that is not on the loan account goes in the **write-off register** (EIR & Revenue Recognition, EIR Data, Write-offs; table `loan_write_offs`): one person proposes with the reason and the evidence, a different person approves, and only an approved entry reaches the engines, as a Write-off on the loan's actual transactions (source `WRITE_OFF_REGISTER`), which the EIR treats as derecognition, not cash; a rejected or withdrawn entry never reaches them, and reversing an approved one removes its row. The January 2026 entry is MUSECO's: its carrying amount was exactly 138,135,680.80 from November 2025 to January 2026, the amount of the 1064 / 1876 pair. It is proposed in the register for 31 December 2025 and waits for a second person and Finance's confirmation.

**What is asked for.** `GL_05` (pack 4, RUN 7):"""
assert a in s
s = s.replace(a, b, 1)
open(P, 'w', encoding='utf-8').write(s)

H = 'C:/xampp/htdocs/MAICC-IFRS9/database/seeders/data/help_user_content/06-eir-and-revenue-recognition.php'
h = open(H, encoding='utf-8').read()
a = "        'Fee classification' => ["
b = """        'Write-offs' => [
            'body' => '<p>Write-offs shows every write-off the system knows of. To reach it, open <b>EIR &amp; Revenue Recognition</b>, then <b>EIR Data</b>, and choose the <b>Write-offs</b> tab.</p>'
                . '<p><b>Write-offs in the ledger</b> lists the credits to loan accounts that are write-offs, not collections: a posting of the write-off type (300); a credit in a batch whose other leg debits GL 1064, Term Loans Write Offs (as on 29 June 2026, when twenty closed loans were cleared as account closures); or an ACCOUNT CLOSURE in a transfer batch on an account whose status is H. These need no approval, because the posting in E-Banker is the evidence. The loan book, the EIR and the reports treat them as written off, not as cash received.</p>'
                . '<p><b>Write-off register</b> is for a write-off that is not on the loan account, for example one made only in the audit adjustments or at GL level. One person proposes it with the reason and the evidence; a different person approves it. Only an approved write-off reaches the calculations. A proposal can be withdrawn by the person who made it or rejected by a reviewer; an approved write-off can be reversed with a reason. Every step is in the audit trail.</p>',
            'steps' => [
                'Open EIR & Revenue Recognition, EIR Data, Write-offs.',
                'Check whether the write-off is already listed under Write-offs in the ledger; if it is, nothing more is needed.',
                'If it is not, press Propose write-off at the top right, choose the loan, give the write-off date and amount, the reason and the evidence (the board minute, the audit adjustment or the GL batch), and press Propose.',
                'Ask a colleague with approval rights to open the same screen and press the tick beside the proposal to approve it, or the cross to reject it with a reason.',
                'After approval, re-run the EIR revenue from the month of the write-off so the amortised cost reflects it.',
            ],
            'routes' => ['eir-write-offs.index', 'eir-write-offs.store', 'eir-write-offs.approve', 'eir-write-offs.close'],
        ],

        'Fee classification' => ["""
assert a in h
h = h.replace(a, b, 1)
open(H, 'w', encoding='utf-8').write(h)
print('ok')
