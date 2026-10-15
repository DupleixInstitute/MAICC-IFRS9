import subprocess, sys
M = r"C:\xampp\mysql\bin\mysql.exe"
def q(db, sql):
    return subprocess.run([M, "-uroot", "-N", "-B", db], input=sql, capture_output=True, text=True, encoding="utf-8", errors="replace").stdout
def counts(db):
    ts = [t for t in q(db, "SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE()").split() if t]
    out = q(db, ";\n".join(f"SELECT '{t}', COUNT(*) FROM `{t}`" for t in ts) + ";")
    return dict((l.split("\t")[0], int(l.split("\t")[1])) for l in out.strip().split("\n"))
a, b = sys.argv[1], sys.argv[2]
A, B = counts(a), counts(b)
for t in sorted(set(A) | set(B)):
    x, y = A.get(t), B.get(t)
    if x != y: print(f"{t:55s} {str(x):>10s} {str(y):>10s}")
