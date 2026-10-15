import json, re
P = 'C:/xampp/htdocs/MAICC-IFRS9/docs/bootstrap/manifest.json'
s = open(P, encoding='utf-8').read()
old_file = 'takeon/Take-on schedules with mapping - with fee columns - for Tamanda to confirm - 7 Oct 2026.xlsx'
new_file = 'takeon/Take-on schedules with mapping - fees completed by MAIIC - received 13 Oct 2026.xlsx'
a = s.index('"file": "' + old_file + '"')
start = s.rfind('{', 0, a)
end = s.index('}', a) + 1
entry = (
    '{\n'
    '      "file": "' + new_file + '",\n'
    '      "query_id": "TAKEON_MAPPING",\n'
    '      "kind": "take-on amortisation schedules mapped to E-Banker accounts, with the fee columns AO:AU completed by MAIIC Finance",\n'
    '      "rows": null,\n'
    '      "sha256": "9b54a193f7baf6351376c37b298b85cc5049b7c2dc363ce427679f52937bc874",\n'
    '      "bytes": 908638,\n'
    '      "note": "Returned 13 Oct 2026 (last saved by Themba Mazibuko): fees for 101 facilities, arrangement 173,983,551.26 and legal 188,323,699.97, total 362,307,251.23, each deducted from the disbursement, source the offer letter and QuickBooks. The account confirmations (Mapping columns X and Y) are still blank. Supersedes the 7 Oct copy, kept under takeon/superseded/."\n'
    '    }'
)
s = s[:start] + entry + s[end:]
json.loads(s)  # still valid
open(P, 'w', encoding='utf-8').write(s)
print('ok')
