import sys
from pypdf import PdfReader
r = PdfReader(sys.argv[1])
out = []
for i, p in enumerate(r.pages):
    out.append("=== PAGE %d ===\n%s" % (i+1, p.extract_text() or ""))
open(sys.argv[2], "w", encoding="utf-8").write("\n".join(out))
print(len(r.pages))
