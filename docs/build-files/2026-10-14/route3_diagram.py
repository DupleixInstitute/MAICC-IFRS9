import matplotlib
matplotlib.use("Agg")
import matplotlib.pyplot as plt
from matplotlib.patches import FancyBboxPatch, FancyArrowPatch

OUT = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\3. Project Execution\E-Banker Integration\Route 3 - push by API endpoint - sequence diagram.png"
NAVY, ORANGE, GREY, GREEN, RED, LIGHT = "#1B2A41", "#E07B00", "#5B6470", "#2E7D4F", "#B23B3B", "#F4F6F9"
plt.rcParams["font.family"] = "Segoe UI"
fig, ax = plt.subplots(figsize=(16, 10.4), dpi=160)
ax.set_xlim(0, 160); ax.set_ylim(0, 104); ax.axis("off")

ax.text(2, 102.5, "Route 3, push by API: what happens when the MAIIC script sends a pack to the system's endpoint", fontsize=14.5, fontweight="bold", color=NAVY, va="top")
ax.text(2, 98.6, "POST https://<system>/api/ebanker-feed/pack   ·   header  Authorization: Bearer <token>   ·   body  pack=<zip with manifest.json and one CSV per query>",
        fontsize=9, color=GREY, va="top", family="Consolas")

lanes = [(16, "E-Banker\n(Oracle)", GREY), (50, "MAIIC server\n(scheduled script)", NAVY), (92, "IFRS 9 system\nAPI endpoint", ORANGE), (134, "IFRS 9 system\nlanding zone", GREEN)]
for x, name, col in lanes:
    ax.add_patch(FancyBboxPatch((x - 13, 85), 26, 9, boxstyle="round,pad=0.4,rounding_size=1.1", fc="white", ec=col, lw=1.8))
    ax.text(x, 89.5, name, ha="center", va="center", fontsize=10, fontweight="bold", color=col, linespacing=1.25)
    ax.plot([x, x], [64 if x == 16 else 4, 84.5], color=col, lw=1.0, ls=(0, (4, 3)), alpha=0.7, zorder=0)

def msg(y, x1, x2, n, text, col=NAVY, back=False):
    ax.add_patch(FancyArrowPatch((x1, y), (x2, y), arrowstyle="-|>", mutation_scale=13, color=col, lw=1.5, linestyle="--" if back else "-"))
    xm = (x1 + x2) / 2
    ax.text(xm, y + 1.0, f"{n}  {text}", ha="center", va="bottom", fontsize=8.4, color=col, linespacing=1.3)

def act(x, y, h, text, col=NAVY, w=24):
    ax.add_patch(FancyBboxPatch((x - w / 2, y - h), w, h, boxstyle="round,pad=0.3,rounding_size=0.8", fc=LIGHT, ec=col, lw=1.0, zorder=2))
    ax.text(x, y - h / 2, text, ha="center", va="center", fontsize=7.9, color=GREY, linespacing=1.3, zorder=3)

# 1-2 script reads E-Banker
msg(79, 50, 16, "1", "06:00 first working day: run the registered\nqueries (read-only account, wallet)")
msg(72, 16, 50, "2", "rows returned", col=GREY, back=True)
act(50, 69, 8, "3  write one CSV per query\n+ manifest.json (rows, SHA-256,\nquery version); zip it", w=26)
msg(56, 50, 92, "4", "POST the zip over HTTPS\nwith the bearer token")
act(92, 52.5, 15, "5  checks on arrival\n· token matches (else 401)\n· a readable zip (else 422)\n· no entry escapes the inbox\n  (else the whole zip 422)\n· manifest.json present (else 422)", col=ORANGE, w=27)
act(92, 35.5, 7, "6  unzip into the inbox\n+ audit entry with the sender's IP", col=ORANGE, w=27)
msg(24, 92, 50, "7", "202 Accepted + inbox name\n(received, not yet landed)", col=ORANGE, back=True)
act(50, 19, 6, "8  script logs the reply;\nretries on any non-202", w=26)
msg(21, 92, 134, "9", "06:30 poller picks up the inbox\n(eir:poll-feed-folder --also-route-3)", col=GREEN)
act(134, 18, 14, "10  the same gates as every\nroute: hashes, row counts,\nversions, ties\n→ LANDED (ROUTE_3_API) or\nQUARANTINED; watermarks\nrecorded; builds read LANDED", col=GREEN, w=27)

# legend
ax.add_patch(FancyBboxPatch((2, 0.4), 32, 10, boxstyle="round,pad=0.3,rounding_size=0.8", fc="white", ec=GREY, lw=0.6))
ax.text(4, 9.6, "Direction of connection", fontsize=8.6, fontweight="bold", color=NAVY, va="top")
ax.text(4, 6.6, "MAIIC opens both connections (to E-Banker,\nthen to the system). The system never\nconnects into MAIIC's network.", fontsize=7.8, color=GREY, va="top", linespacing=1.35)

fig.savefig(OUT, bbox_inches="tight", facecolor="white")
print("saved", OUT)
