"""Draft .eml (X-Unsent, opens in Outlook as a draft) with two PDF attachments."""
import os, mimetypes
from email.message import EmailMessage
from email.utils import formatdate, make_msgid

folder = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\2. Documents from clients\Raw Query Scripts\Query Requests to MAIIC"
runsheet = os.path.join(folder, "FOLLOW-UP extracts for Barry - pack 3 - 9 Oct 2026.pdf")
note = os.path.join(folder, "Note to Barry - follow-up extracts pack 3 - 9 Oct 2026.pdf")
sql = os.path.join(folder, "FOLLOW-UP extracts for Barry - pack 3 - 9 Oct 2026.sql")

msg = EmailMessage()
msg["From"] = "Edward Mazibuko <edward@dupleixinstitute.com>"
msg["To"] = "Barry Makumba <bmakumba@maiic.mw>"
msg["Cc"] = ", ".join([
    "Thomson Kumwenda <tkumwenda@maiic.mw>",
    "Tamanda Sitimawina <tsitimawina@maiic.mw>",
    "Vanessa Magaleta <vmagaleta@maiic.mw>",
    "Kundai Muriwo <kundai@dupleixinstitute.com>",
    "Wadzanai Rombe <wadzanai@dupleixinstitute.com>",
])
msg["Subject"] = "MAIIC EIR: extracts pack 3 - the September loan-book run, the P2_13 re-run, the Mega Farm files, and four new queries"
msg["Date"] = formatdate(localtime=True)
msg["Message-ID"] = make_msgid(domain="dupleixinstitute.com")
msg["X-Unsent"] = "1"

text = """Dear Barry,

Thank you for the seven files of 8 October. GL_03 settled the year-end legs, the September ledger and balances landed as the first monthly pack, and the security and guarantor tables are loaded. Every one of them moved the build.

Attached are two PDFs:

1. FOLLOW-UP extracts for Barry - pack 3: the run sheet. Ten read-only queries in run order, each with the file name to save it under. The .sql is attached too, to paste from.
2. Note to Barry - pack 3: what each file is for, with Dr Thom and Tamanda copied.

Three things to do differently this time, in the order they matter:

- Run RUN 0 first, before any query. The two ALTER SESSION lines make dates come out as 2026-09-30 and decimals with a point. They were not run before the 7 and 8 October exports, so every date arrived as m/d/yyyy; the system coped, but it now records on every load whether the dates were ISO, and from this pack on that should say yes. They last for the session only and change nothing in the database.
- P2_13 needs re-running as RUN 2. The 8 October file carried the word "CLOB" where the payload text should be, because SQL Developer does not export a CLOB as text unless told to; RUN 2 renders the first 8,000 characters of each payload as text.
- Export as CSV with a header row, comma delimiter, strings in double quotes, UTF-8, one file per query named as the sheet says.

Group A is what is still open from pack 2 and keeps the build moving this week: M09_03, the Loan Book Report run for 30 September (the one September file we do not hold); the P2_13 re-run; and the eight Mega Farm files, MF_01 to MF_08, which the Mega Farm module is waiting on. The trial balance for 30 September comes from Tamanda in the usual way.

Group B is four short queries this week's build raised: the scheme master (the interest basis on part-drawn facilities, and a check of our reading of the moratorium fields, which the note explains); row counts of the reschedule, write-off and charges tables; and the charges on the term-loan schemes 140 to 145, which the signed offer letters show but P2_07 does not carry. Two corrections to the loan master from the signed letters are in the note as well (Mchinji SACCO 100,000,000; VNC Bricks 200,000,000).

If any table in group B goes by a different name on your side, just tell us the name and we will adjust.

With thanks,

Edward Mazibuko
Director, Data Analytics
Dupleix Institute
"""
msg.set_content(text)

for path in [runsheet, note, sql]:
    ctype, _ = mimetypes.guess_type(path)
    maintype, subtype = (ctype or "application/octet-stream").split("/", 1)
    if path.endswith(".sql"):
        maintype, subtype = "text", "plain"
    with open(path, "rb") as fh:
        msg.add_attachment(fh.read(), maintype=maintype, subtype=subtype, filename=os.path.basename(path))

out = os.path.join(folder, "EMAIL - extracts pack 3 - 9 Oct 2026 (open in Outlook and send).eml")
with open(out, "wb") as fh:
    fh.write(bytes(msg))
print("written", out, os.path.getsize(out), "bytes")
