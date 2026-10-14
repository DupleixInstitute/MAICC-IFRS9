import matplotlib
matplotlib.use("Agg")
import matplotlib.pyplot as plt
from matplotlib.patches import FancyBboxPatch, FancyArrowPatch

OUT = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\3. Project Execution\E-Banker Integration\Bootstrap - test harness and reference copy - diagram.png"
NAVY, ORANGE, GREY, GREEN, LIGHT, PALE = "#1B2A41", "#E07B00", "#5B6470", "#2E7D4F", "#F4F6F9", "#FFF4E5"
plt.rcParams["font.family"] = "Segoe UI"
fig, ax = plt.subplots(figsize=(16, 8.6), dpi=160)
ax.set_xlim(0, 160); ax.set_ylim(0, 86); ax.axis("off")

def box(x, y, w, h, title, body="", fc="white", ec=NAVY, tc=NAVY, lw=1.3, ts=10.2, bs=8.2):
    ax.add_patch(FancyBboxPatch((x, y), w, h, boxstyle="round,pad=0.4,rounding_size=1.1", fc=fc, ec=ec, lw=lw))
    ax.text(x + w / 2, y + h - 2.2, title, ha="center", va="top", fontsize=ts, color=tc, fontweight="bold")
    if body:
        ax.text(x + w / 2, y + h - 5.6, body, ha="center", va="top", fontsize=bs, color=GREY, linespacing=1.35)

def arrow(x1, y1, x2, y2, color=NAVY, lw=1.5, ls="-"):
    ax.add_patch(FancyArrowPatch((x1, y1), (x2, y2), arrowstyle="-|>", mutation_scale=13, color=color, lw=lw, linestyle=ls))

ax.text(2, 84.5, "The bootstrap: one command that rebuilds the whole system from scratch on a throwaway database",
        fontsize=14.5, fontweight="bold", color=NAVY, va="top")
ax.text(2, 80.6, "eir:bootstrap --fresh --with-client-inputs --build --run-engines --verify   (about 2 minutes; never touches the production database maiic_ifrs9)",
        fontsize=9.2, color=GREY, va="top", family="Consolas")

# lane band labels
ax.add_patch(FancyBboxPatch((1, 41), 158, 35, boxstyle="round,pad=0.3,rounding_size=1.2", fc=LIGHT, ec="none"))
ax.add_patch(FancyBboxPatch((1, 2), 158, 35, boxstyle="round,pad=0.3,rounding_size=1.2", fc=PALE, ec="none"))
ax.text(3, 74.5, "TEST  ·  maiic_ifrs9_bootstrap", fontsize=10, fontweight="bold", color=NAVY, va="top")
ax.text(3, 35.5, "PRODUCTION  ·  maiic_ifrs9", fontsize=10, fontweight="bold", color=ORANGE, va="top")

# test lane: inputs
box(3, 44, 25, 26, "Inputs", "Packs already received\n(committed in docs/bootstrap)\n\nClient inputs: fee template,\ntake-on workbook,\ntrial balances\n\nStage B: + a route 4 read\nfrom E-Banker's TEST copy", ts=10)
box(33, 52, 20, 12, "1  Wipe", "a fresh, empty\ntest database")
box(57, 52, 20, 12, "2  Land", "every pack through\nthe same landing zone\nand gates")
box(81, 52, 20, 12, "3  Build", "loan books, contract\ninputs, fees, EIR\nschedules")
box(105, 52, 24, 12, "4  Run the engines", "staging · PD/LGD · ECL ·\nEIR & revenue · FLI · stress\n· reports · GL recon")
box(133, 44, 24, 28, "5  Verify: 9 golden ties", "interest posted (ledger)\ncarrying amount = stored\nreport, every account-month\nyear-end contra on 4215\nyear-end contra on 4216\nloan book = trial balance on\n1050101 · 1050102 · 1050201\n1050202 · 1050401", ec=GREEN, tc=GREEN, ts=9.8, bs=7.9)
for x1, x2 in [(28.6, 32.6), (53.6, 56.6), (77.6, 80.6), (101.6, 104.6), (129.6, 132.6)]:
    arrow(x1, 58, x2, 58)
ax.text(66, 48.5, "Approvals in the test database are labelled \"System Bootstrap (automated data-readiness, not a MAIIC approval)\"",
        ha="center", fontsize=8, color=GREY, style="italic")

# production lane
box(3, 6, 25, 22, "E-Banker (production)", "read by the route in\nforce: route 1 today,\nroute 4 after go-live", ec=ORANGE, ts=10)
box(33, 9, 44, 16, "Landing zone → builds → engines", "the same code, the same gates, the same order;\nMAIIC's own approvals (maker-checker)", ec=ORANGE)
box(81, 9, 48, 16, "Reported figures", "ECL, EIR revenue, staging, regulatory return,\nIFRS 9 disclosures for the month", ec=ORANGE)
arrow(28.6, 17, 32.6, 17, color=ORANGE); arrow(77.6, 17, 80.6, 17, color=ORANGE)

# the four roles, linking lanes
box(133, 4, 24, 30, "What the bootstrap is for", "", ec=NAVY, ts=9.8)
ax.text(135.2, 28.6, "1  History load up to go-live\n2  Test system for route 4\n     (stage B lands here)\n3  Baseline: route 4 must\n     reproduce the manual\n     month row for row\n4  Regression gate before\n     any change goes live\n5  Disaster-recovery\n     rehearsal each quarter",
        ha="left", va="top", fontsize=7.9, color=GREY, linespacing=1.4)
arrow(145, 43.4, 145, 34.6, color=GREEN, lw=1.8)
ax.text(146.2, 39, "only when the ties\nhold does a change\nreach production", fontsize=7.6, color=GREEN, va="center")

fig.savefig(OUT, bbox_inches="tight", facecolor="white")
print("saved", OUT)
