import re, json
s = open(r"C:\xampp\htdocs\MAICC-IFRS9\database\seeders\EbankerQuerySeeder.php", encoding="utf-8").read()
pat = re.compile(r"\[\s*'([A-Z]{1,3}\d*(?:_\d+[a-z]?)?|[A-Z]{2,3}_\d+[a-z]?)'\s*,\s*'((?:[^'\\]|\\.)*)'\s*,\s*(null|'([^']*)')")
ents = pat.findall(s)
by = {}
for qid, title, _raw, tbl in ents:
    by.setdefault(tbl or "(none)", []).append(qid)
print(len(ents), "entries")
for k in sorted(by):
    print(f"{k:32s} {', '.join(by[k])}")
json.dump(by, open(r"C:\Users\wadza\AppData\Local\Temp\claude\c--xampp-htdocs-Stress-Testing-App\ee984144-7b28-4df1-9a6e-560f112869e9\scratchpad\register.json", "w"))
