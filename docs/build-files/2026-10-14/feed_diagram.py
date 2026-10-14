import matplotlib
matplotlib.use("Agg")
import matplotlib.pyplot as plt
from matplotlib.patches import FancyBboxPatch, FancyArrowPatch

OUT = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\3. Project Execution\E-Banker Integration\Feed routes and landing zone - diagram.png"
NAVY, ORANGE, GREY, GREEN, RED, LIGHT = "#1B2A41", "#E07B00", "#5B6470", "#2E7D4F", "#B23B3B", "#F4F6F9"
plt.rcParams["font.family"] = "Segoe UI"
fig, ax = plt.subplots(figsize=(16, 9.6), dpi=160)
ax.set_xlim(0, 160); ax.set_ylim(0, 96); ax.axis("off")

def box(x, y, w, h, title, body="", fc="white", ec=NAVY, tc=NAVY, lw=1.4, ts=11, bs=8.6, bold=True):
    ax.add_patch(FancyBboxPatch((x, y), w, h, boxstyle="round,pad=0.4,rounding_size=1.2", fc=fc, ec=ec, lw=lw))
    ax.text(x + w / 2, y + h - 2.6, title, ha="center", va="top", fontsize=ts, color=tc, fontweight="bold" if bold else "normal")
    if body:
        ax.text(x + w / 2, y + h - 6.4, body, ha="center", va="top", fontsize=bs, color=GREY, linespacing=1.35)

def arrow(x1, y1, x2, y2, color=NAVY, style="-|>", lw=1.5, ls="-", rad=0.0):
    ax.add_patch(FancyArrowPatch((x1, y1), (x2, y2), arrowstyle=style, mutation_scale=13, color=color, lw=lw, linestyle=ls,
                                 connectionstyle=f"arc3,rad={rad}"))

# title
ax.text(2, 94, "How E-Banker data reaches the IFRS 9 system: five routes, one landing zone", fontsize=15, fontweight="bold", color=NAVY, va="top")
ax.text(2, 90.2, "Every route produces the same pack (one CSV per registered query + manifest of row counts and SHA-256 hashes). The landing zone does not know which road a pack came by.",
        fontsize=9.3, color=GREY, va="top")

# source
box(2, 34, 22, 40, "E-Banker\n(Oracle)", "", fc=LIGHT)
ax.text(13, 63, "VGCBS and\nMAIIC_ORA_LIVE\nschemas\n\nTest environment\nfirst (UAT copy),\nthen production\nor the replica", ha="center", va="top", fontsize=8.6, color=GREY, linespacing=1.35)
ax.text(13, 32, "read-only account\nSELECT only", ha="center", va="top", fontsize=8.2, color=ORANGE, fontweight="bold")

# routes
routes = [
    ("1  Manual pack (supervised)", "MAIIC runs the queries on its own machine, Dupleix\nwatches over AnyDesk; CSVs to OneDrive  ·  a person", "In use", GREEN),
    ("2  Scheduled export", "Script on a MAIIC server writes the pack to a shared\nfolder; system polls at 06:30  ·  PUSH to a folder", "Built; awaits account", ORANGE),
    ("3  Push by API (endpoint)", "Script POSTs the zip to /api/ebanker-feed/pack with a\ntoken over HTTPS; 202, landed at next poll  ·  PUSH", "Built; awaits token", ORANGE),
    ("4  Direct read (this request)", "System on MAIIC's server reads E-Banker, runs the\nregistered queries, builds the pack  ·  PULL by the system", "To build: ~1 week", NAVY),
    ("5  Vendor view / API", "Virtual Galaxy exposes views or a vendor endpoint;\nroute 4 reads them  ·  PULL from the vendor's interface", "Only if 4 refused", GREY),
]
ys = [76, 62, 48, 34, 20]
for (t, b, st, col), y in zip(routes, ys):
    hl = t.startswith("4")
    box(32, y, 46, 11.5, t, b, fc="#FFF4E5" if hl else "white", ec=ORANGE if hl else NAVY, lw=2.2 if hl else 1.2, ts=10.2, bs=8.1)
    ax.text(77.4, y + 11.2, st, ha="right", va="top", fontsize=7.4, color=col, fontweight="bold")
    arrow(24.6, 54, 31.4, y + 5.75, color=GREY, lw=1.0, rad=0.0)
    arrow(78.6, y + 5.75, 86.4, 50, color=NAVY, lw=1.1)

# pack
box(86.5, 41, 15, 18, "Pack", "CSV per query\n+ manifest.json\n(rows, SHA-256,\nquery version,\ndate format)", fc=LIGHT, ts=10.5, bs=8)

# landing zone frame
ax.add_patch(FancyBboxPatch((105, 1.2), 53, 80.8, boxstyle="round,pad=0.4,rounding_size=1.5", fc="none", ec=ORANGE, lw=2.0, ls="--"))
ax.text(131.5, 81, "LANDING ZONE  (the one door)", ha="center", va="top", fontsize=11.5, fontweight="bold", color=ORANGE)
arrow(102, 50, 107.6, 66, color=NAVY, lw=1.6)

box(108, 60, 47, 16, "1  Gates", "pack hash already landed?  ·  file hash & row count  ·  key unique\nquery version known  ·  dates & amounts parse  ·  accounts in master\nbalance history ties ledger  ·  month-end rows  ·  fee GL ties TB",
    ts=10.5, bs=7.9)
box(108, 41, 22, 14, "LANDED", "rows written,\nwatermark per\nquery recorded", ec=GREEN, tc=GREEN, ts=10.5, bs=8)
box(133, 41, 22, 14, "QUARANTINED", "rows kept for\ninspection; nobody\nreads them", ec=RED, tc=RED, ts=10.5, bs=8)
arrow(120, 59.6, 119, 55.6, color=GREEN, lw=1.6); arrow(143, 59.6, 144, 55.6, color=RED, lw=1.6)
ax.text(116.4, 57.6, "pass", fontsize=8, color=GREEN, ha="right"); ax.text(146, 57.6, "ERROR / FAIL", fontsize=8, color=RED)

box(108, 23, 47, 14, "2  Raw rows, versioned (never overwritten)", "keyed by the source table's own id  ·  content hash per row\nsame key + same hash → unchanged, skipped\nsame key + new hash → version 2, old row superseded (kept)",
    ts=10, bs=7.9)
arrow(119, 40.6, 125, 37.6, color=GREEN, lw=1.6)
box(108, 9.5, 47, 9.5, "3  Builds read LANDED loads only", "loan books (method A / B)  ·  contract inputs  ·  fees  ·  TB lines", ts=9.8, bs=7.9)
arrow(131.5, 22.6, 131.5, 19.5, color=NAVY, lw=1.6)

# downstream
ax.text(131.5, 7.0, "then the engines: staging · PD / LGD · ECL · EIR & revenue\nGL reconciliation · regulatory return · reports", ha="center", va="top", fontsize=8.4, color=NAVY, fontweight="bold", linespacing=1.4)

# governance band
ax.add_patch(FancyBboxPatch((2, 1.2), 100, 12, boxstyle="round,pad=0.4,rounding_size=1.2", fc=LIGHT, ec=GREY, lw=0.8))
ax.text(4, 12, "Governance around every route", fontsize=9.6, fontweight="bold", color=NAVY, va="top")
ax.text(4, 8.6, "Route in force = Governance Centre setting (maker-checker, effective date); route 1 always available as fallback  ·  Query changes only via the\n"
                "repository (version + SQL hash; old versions refused)  ·  Audit entry per landing / quarantine  ·  Period lock respected  ·  Watermarks make re-reads safe",
        fontsize=7.9, color=GREY, va="top", linespacing=1.4)

fig.savefig(OUT, bbox_inches="tight", facecolor="white")
print("saved", OUT)
