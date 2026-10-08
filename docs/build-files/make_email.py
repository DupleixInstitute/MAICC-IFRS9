"""Build the email to MAIIC as an Outlook-openable .eml (X-Unsent) with the three attachments, and refresh the .md draft."""
import os, re, html
from email.message import EmailMessage
from email.utils import formatdate

Q = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\2. Documents from clients\Raw Query Scripts\Query Requests to MAIIC"
C = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\3. Project Execution\Correspondence - Information Requests"
BASE = "FOLLOW-UP extracts for Barry - 6 Oct 2026"
SUBJECT = "MAIIC EIR: thank you for the dictionary results, and 18 extracts to replace Extracts A, B and C, please by 10:00 tomorrow"

BODY = """Dear Barry, Dr Thom, Tamanda and Dibwa,

Thank you, Barry, for turning the data dictionary queries round so quickly. All twenty-four results arrived this afternoon, every one of them ran, and between them they have unlocked most of what has been open since September. This is the most progress we have made in one day on the data side of the project.

## What the results have settled

- **Interest Policy is found.** It is held on each scheme, and on each account. MAIIC Agricultural and Industrial loans are "Link with PLR", so they follow the Reserve Bank rate; FInES, Term and Investment loans are on a slab rate, which is why they never moved. That matches the loan books exactly and ends the guesswork on repricing.
- **The Fixed/Variable label in Extract A was the wrong way round.** The raw flag is "floating" on every MAIIC scheme and "fixed" on every FInES scheme. The script's translation had the two swapped. No harm done, now that we know.
- **The spread over the prime rate exists** as a field on every loan, and the per-account rate set-up table carries the PLR rate, the spread and the date each rate took effect. That is the rate history we were going to ask the vendor to export from the audit trail.
- **The ledger is signed and self-describing.** Debits are negative, credits positive, and each posting's narration names what it is: "arrangement fee", "legal fees", "IntDR Fm 08-AUG-25 To 31-AUG-25 @ 31%". The debit/credit question and most of the "what does this posting mean" questions answer themselves.
- **The fee table exists:** the disbursement charges table holds each charge by name, amount and income GL. The fee template we sent may now only be needed for anything handled outside the system.
- **The loan book history is stored** for every as-on date, which should fill the months from November 2024 to November 2025 that we do not have.
- **The interest calculation log exists**, with the rate, the period, the days and the amount behind every interest posting. That is the "how" behind Extract C.
- **Restructures and write-offs:** both tables are empty, so the blank restructure date in Extract A is real, and the restructure history lives in Tamanda's register, as we understood.
- **The instalment chart is stored**, with the amount recovered against each instalment, and the chart starts with a row at the loan's start date, which explains why the instalment count was always one more than the tenor.
- **Transaction code 120** on six accounts at 31 December 2025 adds up to the cent to the difference we had found between Extracts B and C.

## What we now ask for: eighteen extracts that replace Extracts A, B and C, by 10:00 tomorrow

Rather than re-run the three extract procedures, we would like to take the data straight from the tables the dictionary has shown us. The attached file holds eighteen read-only queries, numbered in the order to run them and grouped in three priorities. Each one is a plain SELECT; nothing is created, changed or deleted.

**Please could we have all eighteen result files by 10:00 tomorrow, Tuesday 7 October.** The queries are small (the ledger is about 3,700 rows) and should run in minutes, and having them in the morning lets us load and check them the same day. If time runs short, send Priority 1 and 2 by 10:00 and Priority 3 by close of business.

- **Priority 1 (runs 1 to 5)** replaces the three extracts: the full signed ledger with narrations, the account master and loan master with every column, the rate set-up per account, and the interest calculation log. These resolve the reconciliation issues we raised: direction of postings, interest charged against cash received, the rate history, and the fees.
- **Priority 2 (runs 6 to 10):** the PLR history, the disbursement charges, the stored loan book history, the balance history and the current instalment chart.
- **Priority 3 (runs 11 to 18):** the remaining supporting tables.

The same file is attached three ways, so that whichever is easiest to open works: as .sql to run, as .txt to copy from, and as .pdf to read.

Two practical points, both explained in the file:

1. **Run the two session settings first (RUN 0).** They make dates come out as 2026-10-06 rather than 7/15/2025, and numbers with a dot for the decimal point. They change nothing in the database, affect no other user, and last only for that login; when Barry disconnects they are gone and there is nothing to put back.
2. **Please save each result as CSV under the file name shown** (P1_01 to P3_18, so the prefix tells us the priority and the run), and do not open the files in Excel before sending. Excel is what swapped days and months in the earlier extracts and it drops the leading zeros from account numbers.

If any query fails, the error will name the column, and a screenshot of it is all we need to fix it.

## Three things only you can tell us

- What transaction codes **306**, **300** and **343** are. We can see their direction but not their meaning.
- What account status **H** and **F** mean. The status history (run 13) may show the reason, but a one-line answer would be quicker.
- Whether the "(Manual Int)" schemes are ever used. They all have zero accounts today.

## Still open from the September list

- The materiality threshold used in the 2024 and 2025 EIR assessments (Dr Thom).
- Written confirmation of which record is the contractual one: the signed offer letter or the system.
- The LOS application and process reference numbers, and the funded/non-funded flag, which we have not yet found in this schema. They may sit with the origination system.

Thank you again. The results have moved us from asking what E-Banker holds to being able to say exactly which table and column we need, and the eighteen queries are the proof of that.

Kind regards,

Edward Mazibuko CA(SA)
Dupleix Institute
"""

# ---- refresh the markdown draft
md = f"""# Draft email: dictionary results received, and the 18 follow-up extracts

**To:** Barry Makumba; Dr Thomson Kumwenda; Tamanda Sitimawina; Dibwa
**Cc:** Vanessa Magaleta; Kundai Muriwo; Wadzanai Rombe
**Subject:** {SUBJECT}
**Attachments:** {BASE}.sql, {BASE}.txt, {BASE}.pdf

---

{BODY}"""
open(os.path.join(C, "2026-10-06 Thank you for the dictionary results and the 18 follow-up extracts (draft).md"), "w", encoding="utf-8").write(md)


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
    if not s:
        flush(); continue
    if s.startswith("## "):
        flush(); out.append(f"<p><b>{inline(s[3:])}</b></p>"); continue
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
HTML = ('<html><body style="font-family:Calibri,Segoe UI,sans-serif;font-size:11pt;line-height:1.4">'
        + "\n".join(out) + "</body></html>")

# ---- the .eml
msg = EmailMessage()
msg["From"] = "Edward Mazibuko <edward@dupleixinstitute.com>"
msg["To"] = ("Barry Makumba <bmakumba@maiic.mw>, Thomson Kumwenda <tkumwenda@maiic.mw>, "
             "Tamanda Sitimawina <tsitimawina@maiic.mw>")
msg["Cc"] = ("Vanessa Magaleta <vmagaleta@maiic.mw>, Kundai Muriwo <kundai@dupleixinstitute.com>, "
             "Wadzanai Rombe <wadzanai@dupleixinstitute.com>")
msg["Subject"] = SUBJECT
msg["Date"] = formatdate(localtime=True)
msg["X-Unsent"] = "1"
msg.set_content(BODY)
msg.add_alternative(HTML, subtype="html")
for ext, mt in ((".sql", ("application", "sql")), (".txt", ("text", "plain")), (".pdf", ("application", "pdf"))):
    p = os.path.join(Q, BASE + ext)
    with open(p, "rb") as fh:
        msg.add_attachment(fh.read(), maintype=mt[0], subtype=mt[1], filename=BASE + ext)
eml = os.path.join(Q, "EMAIL - 18 follow-up extracts by 10am 7 Oct 2026 (open in Outlook and send).eml")
with open(eml, "wb") as fh:
    fh.write(bytes(msg))
print("written", eml, os.path.getsize(eml))
