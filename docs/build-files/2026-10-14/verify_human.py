"""Independent check that the humanised documents kept every fact-bearing element."""
import re
from pathlib import Path
S = Path(__file__).parent
DIR = Path(r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\3. Project Execution\E-Banker Integration")
pairs = [("before_tech.md", DIR / "Data access request - E-Banker read-only access (route 4) - 14 Oct 2026.md"),
         ("before_plain.md", DIR / "Plain-language guide - connecting the IFRS 9 system to E-Banker - 14 Oct 2026.md")]

def parts(t):
    fm = t[:t.index("\n---", 4) + 4]
    heads = re.findall(r"^#{2,3} .*$", t, re.M)
    figs = re.findall(r"^!\[.*?\]\((.+?)\)", t, re.M)
    ticks = re.findall(r"`[^`]+`", t)
    nums = re.findall(r"\b\d[\d,]*(?:\.\d+)?\b", t)
    tables = []
    cur = []
    for line in t.splitlines():
        if line.startswith("|"):
            cur.append(line)
        elif cur:
            tables.append(cur); cur = []
    if cur: tables.append(cur)
    firstcols = [[r.split("|")[1].strip() for r in tb if not r.startswith("|---")] for tb in tables]
    return fm, heads, figs, ticks, nums, tables, firstcols

for before, after in pairs:
    b = (S / before).read_text(encoding="utf-8"); a = after.read_text(encoding="utf-8")
    fb, hb, gb, tb, nb, tab_b, fc_b = parts(b)
    fa, ha, ga, ta, na, tab_a, fc_a = parts(a)
    print("==", after.name)
    print("  front matter identical:", fb == fa)
    print("  headings identical:", hb == ha, len(hb), len(ha))
    print("  figures identical:", gb == ga)
    missing_ticks = sorted(set(tb) - set(ta)); added_ticks = sorted(set(ta) - set(tb))
    print("  backtick spans missing:", missing_ticks[:10], "added:", added_ticks[:10])
    print("  tables:", len(tab_b), "->", len(tab_a), "| rows per table identical:", [len(x) for x in tab_b] == [len(x) for x in tab_a])
    print("  first columns identical:", fc_b == fc_a)
    from collections import Counter
    cb, ca = Counter(nb), Counter(na)
    lost = {k: cb[k] - ca[k] for k in cb if ca[k] == 0}
    new = {k: ca[k] for k in ca if cb[k] == 0}
    print("  numbers that disappeared entirely:", dict(list(lost.items())[:25]))
    print("  numbers that are new:", dict(list(new.items())[:25]))
    print("  Barry mentions:", a.count("Barry"), "| 'will' count", b.count(" will "), "->", a.count(" will "))
    print("  length:", len(b), "->", len(a), f"({(len(a) - len(b)) / len(b):+.1%})")
