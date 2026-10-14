import datetime as dt
import matplotlib
matplotlib.use("Agg")
import matplotlib.pyplot as plt
import matplotlib.dates as md
from matplotlib.patches import FancyBboxPatch

OUT = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\3. Project Execution\E-Banker Integration\Route 4 - proposed build and test timetable - gantt.png"
NAVY, ORANGE, GREY, GREEN, RED, LIGHT = "#1B2A41", "#E07B00", "#5B6470", "#2E7D4F", "#B23B3B", "#F4F6F9"
plt.rcParams["font.family"] = "Segoe UI"
D = dt.date.fromisoformat

rows = [  # (stage, label, start, end, who, colour)
    ("0", "System installed on MAIIC's server; bootstrap run, 9 ties checked", "2026-10-15", "2026-10-23", "Dupleix + MAIIC ICT (AnyDesk)", GREEN),
    ("A", "Dr Thom approves; vendor letter sent", "2026-10-15", "2026-10-23", "MAIIC", NAVY),
    ("A", "Vendor's written reply (decision point 6 Nov)", "2026-10-23", "2026-11-06", "Virtual Galaxy", GREY),
    ("B", "Test account, firewall, connection test (steps 1 to 8)", "2026-10-26", "2026-11-06", "MAIIC ICT", NAVY),
    ("B", "Route 2 script written and installed on test", "2026-10-26", "2026-11-06", "Dupleix + ICT", ORANGE),
    ("B", "Route 4 build (client, connection, query runner, bootstrap option)", "2026-11-02", "2026-11-13", "Dupleix", ORANGE),
    ("B", "Pushed; MAIIC pulls onto its server, migrates, re-runs bootstrap", "2026-11-13", "2026-11-13", "MAIIC ICT + Dupleix", GREEN),
    ("B", "Test on E-Banker's test copy; acceptance tests 1 to 7; sign-off 20 Nov", "2026-11-16", "2026-11-20", "Dupleix + ICT", GREEN),
    ("C", "Production account (step 11), connection test, switch prepared", "2026-11-23", "2026-11-27", "MAIIC ICT", NAVY),
    ("C", "Parallel run 1: November month-end (route 4 beside the hand pack)", "2026-12-01", "2026-12-04", "Dupleix + MAIIC", GREEN),
    ("C", "Parallel run 2: December year-end", "2027-01-04", "2027-01-08", "Dupleix + MAIIC", GREEN),
    ("C", "Switch to route 4 (maker-checker); route 1 kept as fallback", "2027-01-18", "2027-01-22", "MAIIC", NAVY),
]
milestones = [("2026-11-02", "Oct month-end\n(by hand)"), ("2026-12-01", "Nov month-end\n(parallel 1)"), ("2027-01-04", "Dec year-end\n(parallel 2)"), ("2027-02-01", "Jan month-end\n(first by route 4)")]

fig, ax = plt.subplots(figsize=(16, 8.8), dpi=160)
n = len(rows)
for i, (stage, label, s, e, who, col) in enumerate(rows):
    y = n - i
    ax.barh(y, (D(e) - D(s)).days + 1, left=D(s), height=0.56, color=col, alpha=0.9, zorder=3)
    tr = ax.get_yaxis_transform()
    ax.text(-0.015, y, label, ha="right", va="center", fontsize=9, color=NAVY, transform=tr)
    ax.text(-0.60, y, stage, ha="center", va="center", fontsize=9.5, fontweight="bold", color=ORANGE, transform=tr)
    ax.text(D(e) + dt.timedelta(days=1.2), y, who, ha="left", va="center", fontsize=8, color=GREY, style="italic")
for d, lab in milestones:
    ax.axvline(D(d), color=RED, lw=1.1, ls="--", zorder=2)
    ax.text(D(d), n + 0.95, lab, ha="center", va="bottom", fontsize=8, color=RED, linespacing=1.15)
ax.axvspan(D("2026-12-14"), D("2027-01-03"), color=LIGHT, zorder=1)
ax.text(D("2026-12-24"), 0.35, "possible ICT year-end\nchange freeze", ha="center", va="center", fontsize=7.8, color=GREY, style="italic")
ax.axvline(D("2026-10-14"), color=NAVY, lw=1.2, zorder=2)
ax.text(D("2026-10-14"), 0.35, " today\n 14 Oct", fontsize=7.8, color=NAVY, va="center")

ax.set_ylim(0, n + 2.2)
ax.set_xlim(D("2026-10-12"), D("2027-02-08"))
ax.xaxis.set_major_locator(md.WeekdayLocator(byweekday=md.MO, interval=1))
ax.xaxis.set_major_formatter(md.DateFormatter("%d %b"))
plt.setp(ax.get_xticklabels(), rotation=90, fontsize=7.6, color=GREY)
ax.set_yticks([]); ax.grid(axis="x", color="#E3E7EC", lw=0.6, zorder=0)
for s in ("top", "right", "left"):
    ax.spines[s].set_visible(False)
fig.text(0.015, 0.965, "Route 4: proposed build and test timetable, October 2026 to February 2027", fontsize=14, fontweight="bold", color=NAVY, va="top")
fig.text(0.015, 0.915, "Stage 0 install  ·  A approve  ·  B test environment  ·  C production.   Dates assume approval by Friday 23 October 2026.\nEach week of delay moves the whole chain; a missed month-end moves it by a month.",
         fontsize=9, color=GREY, va="top", linespacing=1.4)
fig.subplots_adjust(left=0.36, right=0.93, top=0.78, bottom=0.12)
fig.savefig(OUT, facecolor="white")
print("saved", OUT)
