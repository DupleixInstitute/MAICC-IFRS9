import re
D = 'database/seeders/data/help_user_content/'
common = ("<h4>Sample file, column guide and the check</h4>"
          "<p>The <b>How to import</b> panel on the import screen lists every column with its type, whether it is required, its format and allowed values, a description and an example. "
          "<b>Download sample file</b> gives a file in exactly the format the import reads, with one or two illustrative rows (identifiers start with 8 and names with Sample, so they are never mistaken for real data); "
          "<b>Download column guide</b> gives the same as an Excel workbook. {extra}"
          "Before anything is saved the whole file is checked: every required column present, the type of each value, the allowed values and no key repeated. "
          "If any row breaks a rule, nothing is imported and the problems are listed by row and column with how to fix each one.</p>")
notes = {
    ('06-eir-and-revenue-recognition.php', 'Schedule intake: importing Extracts A, B and C and schedules'): "Each import type has its own sample and guide; the panel follows the type chosen. The check runs when the file is analysed and again before it is queued, through the column matching. Dates are judged as the reader resolves them; numbers are checked as written, so a stray value is never cleaned to zero. A repeated key the importer is built to skip is listed as a warning. ",
    ('06-eir-and-revenue-recognition.php', 'Trial balances'): "The trial balance is read by position (the period in B1, GL Title in column B, Debit in C, Credit in D, the Grand Total row), so the sample file is a layout with two illustrative lines that tie. <b>Check file</b> runs every gate of the load without keeping anything; a file loaded with a failing gate is still kept as a refused load for the audit trail. ",
    ('07a-ifrs9-model-setup.php', 'Internal grades'): "For the S&P import the guide lists the two sheets and the columns read; there is no blank template, because the file is the ECL model workbook itself. <b>Preview</b> reads it without saving anything. ",

    ('04-customer-and-loan-data.php', 'Clients'): "On the client import page, <b>Check file</b> tests a chosen file without saving it. ",
    ('04-customer-and-loan-data.php', 'Importing a loan book'): "The standard layout and the E-Banker report each have their own sample and guide; the E-Banker report is read by column position. <b>Check file</b> tests a chosen file without saving it; a matched (custom) file is checked through its matching. ",
    ('05-collateral-management.php', 'Importing collateral'): "<b>Check file</b> tests a chosen file without saving it; collateral_type must be a code held on Collateral Types. ",
    ('07a-ifrs9-model-setup.php', 'SICR groups'): "The panel sits in the import window. ",
    ('07a-ifrs9-model-setup.php', 'SICR alert items'): "The panel sits in the import window; active and evidence_required are yes or no, and stage is 2 or 3. ",
    ('07b-ifrs9-model-setup-forward-looking.php', 'Macro elements'): "In the Import window, choose Your own file, IMF WEO file or Reserve Bank file above the panel to see that source's layout; the check runs when you press Preview. ",
    ('07b-ifrs9-model-setup-forward-looking.php', 'Credit loss data'): "<b>Check file</b> tests a chosen file without saving it; PD and LGD are fractions between 0 and 1. ",
}
BS = chr(92)
for (f, art), extra in notes.items():
    p = D + f
    s = open(p, encoding='utf-8').read()
    key = "        '" + art + "' => ["
    i = s.index(key)
    m = re.compile(r"\n            '(steps|images|routes)' =>").search(s, i)
    end = m.start() if m else s.index("\n        ],", i)
    seg = s[i:end]
    if 'Sample file, column guide and the check' in seg:
        continue
    body = seg.rstrip()
    # the body ends with a quote and a comma that are not an escaped quote
    assert body.endswith("',") and body[-3] != BS, (f, art, body[-20:])
    j = len(body) - 2
    note = common.format(extra=extra).replace("'", BS + "'")
    seg = body[:j] + "'\n                . '" + note + "'," + seg[len(body):]
    s = s[:i] + seg + s[end:]
    open(p, 'w', encoding='utf-8').write(s)
    print('ok', f, art)
