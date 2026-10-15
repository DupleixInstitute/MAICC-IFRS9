"""Unsent follow-up email to Barry after the pack 10 results (nothing is sent)."""
import os
from email.message import EmailMessage
from email.utils import formatdate, make_msgid

BASE = r"C:\Users\DELL\OneDrive\2026\Projects\MAIIC\2. Documents from clients\Raw Query Scripts\Query Requests to MAIIC"
REVIEW = os.path.join(BASE, "Received 12 October 2026", "Pack 4", "Pack 10", "Export Results", "Results review", "Pack 10 results review.md")

BODY = """Dear Barry,

Thank you for pack 10. All eleven files came through complete, and with pack 9 we now hold the whole overdue run, from December 2024 to 12 October 2026. The batch legs also showed us two refinancings we couldn't see before.

There are a few things we'd like your help with:

1. The stored trial balance is still 88,000 out of balance on every day from 15 May 2026 (posting 368352 on GL 6730 is still missing from TRIAL_BALANCE_HISTORY). Could you ask the vendor when the rebuild will be done?

2. The Zaithwa Farms balance rows (account 000104450000015) are the same as in pack 1, so they still don't follow the ledger from May 2025. Could the vendor rebuild those rows too?

3. The login the extracts run under is VGCBS, the schema owner. It can change and delete every table, so a slip in an extract script could alter the live database. Could ICT set up the read-only account described in our route 4 data-access request, and run the next packs under it?

4. VGCBS holds three stored procedures we didn't know about: PROC_BUILD_MAIIC_CASHFLOW_REGISTER, PROC_BUILD_MAIIC_FACILITY_REGISTER and PROC_BUILD_MAIIC_GL_INTEREST_RECON. Do you know who wrote them, when, what they produce and whether they're run on a schedule? If the vendor built them for MAIIC, we'd like to see what they return before anything relies on them.

5. Could Finance or Credit confirm two refinancings we found in the postings: The Food Empire's new loan 000106050000003 paid off its FInES loan 000104450000068 on 24 November 2025, and Malawi Police SACCO's 000106050000006 took over 000104430000072 in March 2026 (the March repayments were moved across on 31 March). And how were JVD Agro's two loans refinanced? We found no transfer between loan accounts for those.

The eight questions in the pack 10 email still stand. The review of pack 10 is in Pack 10\\Export Results\\Results review in the Pack 4 folder.

There's no new query this time.

With thanks,

Edward Mazibuko
Director, Data Analytics
Dupleix Institute
"""

msg = EmailMessage()
msg["From"] = "Edward Mazibuko <edward@dupleixinstitute.com>"
msg["To"] = "Barry Makumba <bmakumba@maiic.mw>"
msg["Cc"] = ("Thomson Kumwenda <tkumwenda@maiic.mw>, Tamanda Sitimawina <tsitimawina@maiic.mw>, "
             "Vanessa Magaleta <vmagaleta@maiic.mw>, Kundai Muriwo <kundai@dupleixinstitute.com>, "
             "Wadzanai Rombe <wadzanai@dupleixinstitute.com>")
msg["Subject"] = "MAIIC EIR: pack 10 results - two vendor rebuilds, the extract login and three procedures"
msg["Date"] = formatdate(localtime=True)
msg["Message-ID"] = make_msgid(domain="dupleixinstitute.com")
msg["X-Unsent"] = "1"
msg.set_content(BODY)
if os.path.isfile(REVIEW):
    msg.add_attachment(open(REVIEW, "rb").read(), maintype="text", subtype="markdown", filename="Pack 10 results review.md")
out = os.path.join(BASE, "EMAIL - pack 10 results follow-up - 14 Oct 2026 (open in Outlook and send).eml")
open(out, "wb").write(bytes(msg))
print(out)
