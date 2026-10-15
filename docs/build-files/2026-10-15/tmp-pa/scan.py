import subprocess, sys
db = sys.argv[1]
M = r"C:\xampp\mysql\bin\mysql.exe"
def q(sql, viaStdin=False):
    return subprocess.run([M, "-uroot", "-N", "-B", db], input=sql, capture_output=True, text=True, encoding="utf-8", errors="replace")
cols = q("SELECT table_name, column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND data_type IN ('varchar','text','mediumtext','longtext','char','json')").stdout.split("\n")
pats = ["%xampp%", "%Users\\DELL%", "%Users/DELL%", "%OneDrive%", "%scratchpad%", "%AppData%", "%localhost%", "%127.0.0.1%", "%C:\\%", "%C:/%", "%htdocs%"]
parts = []
for line in cols:
    if not line.strip(): continue
    t, c = line.split("\t")
    if t == "ebanker_raw_rows": continue
    cond = " OR ".join(f"`{c}` LIKE '{p}'" for p in pats)
    parts.append(f"SELECT '{t}.{c}', COUNT(*), MIN(LEFT(`{c}`,160)) FROM `{t}` WHERE {cond} HAVING COUNT(*)>0")
for i in range(0, len(parts), 60):
    r = q(";\n".join(parts[i:i+60]) + ";")
    if r.stdout.strip(): print(r.stdout.strip())
    if r.stderr.strip(): print("ERR", r.stderr.strip()[:300])
