"""Email to Dr Thom (cc Tamanda, Barry, Vanessa, Kundai, Wadzanai): the three ECL questions, the Mega Farm loan books, our understanding of the arrangement, the S&P method, and the Wednesday build date. Outlook-openable .eml plus a .md draft."""
import os, re, html
from email.message import EmailMessage
from email.utils import formatdate

Q = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\2. Documents from clients\Raw Query Scripts\Query Requests to MAIIC"
S = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\3. Project Execution\specs"
C = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\3. Project Execution\Correspondence - Information Requests"
SUBJECT = "MAIIC EIR and ECL: three questions on the December 2025 provisions, the Mega Farm loan books, and the system build date of Wednesday 14 October"

BODY = """Dear Dr Thom,

Thank you for the week. Barry's amended queries of 7 October gave us the whole E-Banker ledger and every table behind it, and with them we have been able to answer, from the data itself, almost everything we had been asking for since August. The reconciliations tie to the kwacha, the specification for the engine is written, and we are on track to have the system built by Wednesday 14 October. Because of that, we would like to close the data requests by tomorrow, Friday 9 October: anything that arrives after that goes into the system through its normal monthly feed rather than into the build.

This note has three parts: our understanding of the Mega Farm arrangement and how the system will treat it; three questions on the December 2025 provisions; and the loan books we need for the Mega Farm schemes.

## 1. The Mega Farm loans: our understanding, and the treatment we propose

From the 2025 financial statements (notes 8, 9a, 9c, 11(c), 15a and 19), we read the arrangement as follows, and would be grateful for your correction where we have it wrong:

- The programme is Government money: K20 billion set aside by the Ministry of Agriculture, which MAIIC manages and administers on the Government's behalf. MAIIC approves the loans, pays them out (in 2025 largely as farm-input vouchers), keeps the accounts and collects, with much of the repayment arriving as maize through ADMARC, NFRA and ACE.
- The farmers pay 15 percent a year, of which 5 percent is MAIIC's and 10 percent is credited to the fund. MAIIC also earns a 5 percent management fee on the fund and a commission.
- When a farmer does not repay, the loss is charged to the fund, not to MAIIC: the fund's roll-forward in note 11(c) carries K37.4 billion of expected credit losses and the K1.2 billion maize write-down. MAIIC's own exposure is its 5 percent interest share, which the audit moved to other receivables (K2.8 billion) and provided for at K2.1 billion (note 19).
- At 31 December 2025 the programme stood at K51.5 billion gross, of which K48.7 billion in Stage 3, with a provision of K39.8 billion. The seed loans are K7.6 billion of that.

On that reading, our design treats the programme as follows, and we have written it up as decision D30 against open choice O22 in the specification (section 16), and in a two-page recommendation attached:

- The Mega Farm loans stay outside the effective-interest-rate engine. There are no arrangement or legal fees to spread, the rate is fixed by agreement and split by contract, and MAIIC's return is 5 percent whatever the loan does; recalculating at an effective rate would change nothing the statements report.
- MAIIC's 5 percent share is computed by the system on the amount expected to be recovered from each loan, as the rules require for a loan in default, with the fund's 10 percent computed alongside.
- The loans go fully into the ECL module: staging, probability of default, loss given default and the provision for every scheme, with the provision charged to the fund and the write-down of MAIIC's own share charged to MAIIC. The Mega Farms table of note 8, the fund roll-forward of note 11(c), the receivable of note 9a and the maize of note 9c will come out of the system as one report.
- The revenue figure you asked for, the shift between years when interest is recalculated at the effective rate, is then a figure about MAIIC's own lending (K1.4 billion of loan interest in 2024 and K5.6 billion in 2025), which makes it smaller, cleaner and easier to defend to Deloitte.

If you are content with that, a one-line confirmation is all we need; it is seeded in the Governance Centre and waits for you.

## 2. Three questions on the December 2025 provisions

We hold MAIIC's monthly ECL model workbooks from January 2024 to November 2025, and the signed statements, but not the December 2025 working. Three questions, for Tamanda or yourself:

1. Which file produced the December 2025 expected credit loss on the MAIIC book (K475.7 million in note 8)? The monthly model series we hold stops at November 2025.
2. Which file, and which method, produced the K39.76 billion Mega Farm provision? It is not in the monthly model, which has no Mega Farm content at all, and the audit bridge shows a K3.46 billion "Mega Farm ECL movements plus fair value" adjustment, so we read it as a separate year-end working, possibly adjusted with Deloitte. We would like to see the staging rule, the probability and loss assumptions, and how the maize was valued.
3. Did the same working treat the maize receivables (ADMARC, NFRA, ACE) and MAIIC's 5 percent interest share, or were those adjusted separately at the audit?

The purpose is simple: the system must reproduce both December figures before it is trusted with anything else. They become the first two golden numbers it is tested against on every build.

## 3. The Mega Farm loan books, and the pack 2 queries

The Mega Farm schemes were outside the 18 schemes of the 7 October extracts, so we hold no postings, balances or loan-book runs for them yet. Two things would close that:

- Barry to run the second query pack sent this morning (FOLLOW-UP extracts for Barry - pack 2 - 8 Oct 2026, attached), which includes the Mega Farm schemes 96 to 103 (MF_01 to MF_08: the masters, the ledger, the balance history, the stored loan-book runs, the status history, the rates and the charges), the September month-end feed, and the one remaining question on the 31 December 2025 interest batches.
- The monthly Mega Farm loan books Finance uses, December 2024 to September 2026, in whatever form they are kept, so that the model's view of the programme matches Finance's month by month. If the stored runs Barry's query returns are the same thing, the query alone is enough; if Finance keeps a separate book, we would like both.

## 4. The S&P methodology, alongside the transition matrices

Your monthly model carries the S&P default probabilities mapped to MAIIC's internal grades beside the transition-matrix approach, and we have designed the system to accommodate both rather than replace one with the other. The probability of default for each book is a governed choice: the transition matrices from MAIIC's own history, the external-rating mapping (S&P) as a second method, and for the Mega Farm schemes, which have two seasons of history and seven thousand accounts, a seasonal cohort default rate with a benchmark prior until three seasons exist. Every method has a card in the system that explains it, states the data it needs and shows a worked example on a real loan, and the method used is recorded against every loan, so the two approaches can be run side by side and reconciled rather than argued about.

## 5. Where we are, and the date

The system is on track to be built by Wednesday 14 October, with the data of 7 October as its committed foundation. The data requests close tomorrow, Friday 9 October. After that, each month-end comes in through the feed Barry now has the queries for, and nothing further is needed from anyone to run a month.

Thank you again, to you, to Tamanda and to Barry, for the speed and care of this week.

Kind regards,

Edward Mazibuko
Dupleix Institute

Attachments: O22 recommendation - the Mega Farm loans and the EIR engine - 8 Oct 2026.pdf; FOLLOW-UP extracts for Barry - pack 2 - 8 Oct 2026.pdf and .sql; Note to Barry - follow-up extracts pack 2, what each group is for - 8 Oct 2026.pdf
"""

# ---- .md draft
md = f"""**To:** Dr Thomson Kumwenda
**Cc:** Tamanda Sitimawina; Barry Makumba; Vanessa Magaleta; Kundai Muriwo; Wadzanai Rombe
**Subject:** {SUBJECT}

{BODY}"""
open(os.path.join(C, "2026-10-08 To Dr Thom - three ECL questions, Mega Farm loan books, build date (draft).md"), "w", encoding="utf-8").write(md)

# ---- html body
def inline(t):
    t = html.escape(t, quote=False)
    return re.sub(r"\*\*(.+?)\*\*", r"<b>\1</b>", t)
out, buf, kind = [], [], None
def flush():
    global buf, kind
    if buf and kind == "ul": out.append("<ul>" + "".join(f"<li>{x}</li>" for x in buf) + "</ul>")
    if buf and kind == "ol": out.append("<ol>" + "".join(f"<li>{x}</li>" for x in buf) + "</ol>")
    buf, kind = [], None
for line in BODY.split("\n"):
    s = line.strip()
    if not s: flush(); continue
    if s.startswith("## "): flush(); out.append(f"<p><b>{inline(s[3:])}</b></p>"); continue
    m = re.match(r"^- (.*)", s)
    if m:
        if kind != "ul": flush(); kind = "ul"
        buf.append(inline(m.group(1))); continue
    m = re.match(r"^\d+\. (.*)", s)
    if m:
        if kind != "ol": flush(); kind = "ol"
        buf.append(inline(m.group(1))); continue
    flush(); out.append(f"<p>{inline(s)}</p>")
flush()
HTML = '<html><body style="font-family:Calibri,Segoe UI,sans-serif;font-size:11pt;line-height:1.4">' + "\n".join(out) + "</body></html>"

msg = EmailMessage()
msg["From"] = "Edward Mazibuko <edward@dupleixinstitute.com>"
msg["To"] = "Thomson Kumwenda <tkumwenda@maiic.mw>"
msg["Cc"] = ("Tamanda Sitimawina <tsitimawina@maiic.mw>, Barry Makumba <bmakumba@maiic.mw>, Vanessa Magaleta <vmagaleta@maiic.mw>, "
             "Kundai Muriwo <kundai@dupleixinstitute.com>, Wadzanai Rombe <wadzanai@dupleixinstitute.com>")
msg["Subject"] = SUBJECT
msg["Date"] = formatdate(localtime=True)
msg["X-Unsent"] = "1"
msg.set_content(BODY)
msg.add_alternative(HTML, subtype="html")
for path, mt in ((os.path.join(S, "O22 recommendation - the Mega Farm loans and the EIR engine - 8 Oct 2026.pdf"), ("application", "pdf")),
                 (os.path.join(Q, "FOLLOW-UP extracts for Barry - pack 2 - 8 Oct 2026.pdf"), ("application", "pdf")),
                 (os.path.join(Q, "FOLLOW-UP extracts for Barry - pack 2 - 8 Oct 2026.sql"), ("application", "sql")),
                 (os.path.join(Q, "Note to Barry - follow-up extracts pack 2, what each group is for - 8 Oct 2026.pdf"), ("application", "pdf"))):
    with open(path, "rb") as fh:
        msg.add_attachment(fh.read(), maintype=mt[0], subtype=mt[1], filename=os.path.basename(path))
eml = os.path.join(C, "EMAIL - To Dr Thom - three ECL questions, Mega Farm loan books, build date 8 Oct 2026 (open in Outlook and send).eml")
with open(eml, "wb") as fh: fh.write(bytes(msg))
print("written", eml, os.path.getsize(eml))
