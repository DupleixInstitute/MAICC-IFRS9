from pathlib import Path
import shutil, subprocess, sys
F = Path(r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\3. Project Execution\E-Banker Integration\Data access request - E-Banker read-only access (route 4) - 14 Oct 2026.md")
t = F.read_text(encoding="utf-8")
start = t.index("**Please plan this now.**"); end = t.index("4. If E-Banker runs a standby")
para = t[start:end]; t = t[:start] + t[end:]
anchor = "## 2. Why"
t = t.replace(anchor, para + anchor, 1)
F.write_text(t, encoding="utf-8")
print(subprocess.run([sys.executable, r"C:\xampp\htdocs\MAICC-IFRS9\docs\build-files\spec_render.py", str(F)], capture_output=True, text=True).stdout.strip().splitlines()[-1])
shutil.copy(F, Path(r"C:\xampp\htdocs\MAICC-IFRS9\docs\bootstrap\queries") / F.name)
import pypdfium2 as p
p.PdfDocument(str(F.with_suffix(".pdf")))[0].render(scale=1.1).to_pil().save("tech_p1.png")
