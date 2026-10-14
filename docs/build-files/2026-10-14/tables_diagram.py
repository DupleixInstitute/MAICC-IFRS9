import matplotlib
matplotlib.use("Agg")
import matplotlib.pyplot as plt
from matplotlib.patches import FancyBboxPatch, FancyArrowPatch

OUT = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\3. Project Execution\E-Banker Integration\Landing zone - the four tables, one payment followed through - diagram.png"
NAVY, ORANGE, GREY, GREEN, RED, LIGHT = "#1B2A41", "#E07B00", "#5B6470", "#2E7D4F", "#B23B3B", "#F4F6F9"
plt.rcParams["font.family"] = "Segoe UI"
fig, ax = plt.subplots(figsize=(16, 11.8), dpi=160)
ax.set_xlim(0, 160); ax.set_ylim(-8, 110); ax.axis("off")

ax.text(2, 108.5, "Inside the landing zone: four record books, following one real payment", fontsize=15, fontweight="bold", color=NAVY, va="top")
ax.text(2, 104.4, "MUSECO (account 000104420000005) repaid K200,000,000 on 24 July 2025. This is how that one posting is recorded, from the bootstrap database.",
        fontsize=9.5, color=GREY, va="top")

def book(x, y, w, h, n, title, sub, rows, col, hl=None):
    ax.add_patch(FancyBboxPatch((x, y), w, h, boxstyle="round,pad=0.4,rounding_size=1.2", fc="white", ec=col, lw=1.8, zorder=2))
    ax.add_patch(FancyBboxPatch((x, y + h - 9), w, 9, boxstyle="round,pad=0.4,rounding_size=1.2", fc=col, ec=col, lw=1.8, zorder=2))
    ax.text(x + 2, y + h - 2.4, f"{n}  {title}", fontsize=11.5, fontweight="bold", color="white", va="top", zorder=3)
    ax.text(x + 2, y + h - 6.4, sub, fontsize=8.2, color="white", va="top", zorder=3, style="italic")
    yy = y + h - 12
    for k, v in rows:
        bold = hl is not None and k in hl
        ax.text(x + 2, yy, k, fontsize=8.3, color=GREY, va="top", family="Consolas", zorder=3)
        ax.text(x + w * 0.42, yy, v, fontsize=8.6, color=NAVY if bold else "#222222", va="top", fontweight="bold" if bold else "normal", zorder=3)
        yy -= 3.6

book(2, 56, 48, 44, "1", "The register", "ebanker_queries  ·  what the system asks E-Banker for", [
    ("query_id", "P1_01"), ("title", "Ledger, every posting"), ("source_table", "CUMVOUCH (E-Banker's ledger)"),
    ("key_column", "CUMVOUCH_DET_ID"), ("account_column", "NEW_AC_NUMBER"), ("date_column", "TRANSACTION_DATE"),
    ("version", "1"), ("incremental", "yes: read only what is new")], NAVY, hl={"query_id", "key_column"})

book(56, 56, 48, 44, "2", "The deliveries book", "ebanker_loads  ·  one line per pack received", [
    ("id", "load 1"), ("pack_name", "MAIIC E-Banker bootstrap inputs"), ("route", "ROUTE_1_MANUAL (by hand)"),
    ("pack_hash", "3ac032d7… (fingerprint)"), ("status", "LANDED"), ("loaded_at", "9 Oct 2026 08:24"),
    ("watermarks", "P1_01 up to key 425,764")], ORANGE, hl={"status", "watermarks"})

book(110, 56, 48, 44, "3", "The files book", "ebanker_pack_files  ·  one line per file in a pack", [
    ("load_id", "1"), ("file", "P1_01_ledger_all.csv"), ("query_id", "P1_01, version 1"),
    ("sha256", "8412efbf… (fingerprint)"), ("rows_declared", "3,640"), ("rows_loaded", "3,640"),
    ("rows_new / versioned", "3,640 / 0"), ("rows_unchanged", "0")], ORANGE, hl={"rows_declared", "rows_loaded"})

book(2, -6, 74, 56, "4", "The records book", "ebanker_raw_rows  ·  one line per version of each E-Banker record", [
    ("id", "20"), ("load_id", "1  (came in delivery 1)"), ("query_id", "P1_01"),
    ("source_key", "194427  (E-Banker's own CUMVOUCH_DET_ID)"), ("account", "000104420000005  (MUSECO)"),
    ("row_date", "2025-07-24"), ("version", "1"), ("superseded_at", "(empty: this is the current version)"),
    ("row_hash", "7fad3443… (fingerprint of the record)"),
    ("payload", "all 83 columns exactly as E-Banker sent them, e.g."),
    ("", "TRANSAMT 200000000.00 · TRANTYPE 305"), ("", "PARTICULARS \"By Trf Loan repayment\" · AC_GLCODE 1050101")], GREEN,
     hl={"source_key", "version", "superseded_at", "payload"})

# what-if panel
ax.add_patch(FancyBboxPatch((82, -6), 76, 56, boxstyle="round,pad=0.4,rounding_size=1.2", fc=LIGHT, ec=GREY, lw=1.0, ls="--", zorder=1))
ax.text(84, 48, "What if E-Banker later corrects this posting?", fontsize=11, fontweight="bold", color=NAVY, va="top")
ax.text(84, 43.5, "(illustration: no record has needed a second version so far)", fontsize=8.2, color=GREY, va="top", style="italic")
def mini(y, ttl, lines, col):
    ax.add_patch(FancyBboxPatch((85, y), 70, 13, boxstyle="round,pad=0.3,rounding_size=0.8", fc="white", ec=col, lw=1.3, zorder=2))
    ax.text(87, y + 11.3, ttl, fontsize=9, fontweight="bold", color=col, va="top", zorder=3)
    ax.text(87, y + 7.4, lines, fontsize=8.2, color="#222222", va="top", family="Consolas", zorder=3, linespacing=1.4)
mini(24, "Row 20 stays, and is marked as replaced", "source_key 194427 · version 1 · TRANSAMT 200000000.00\nsuperseded_at  2026-11-03 06:31   <- set, never deleted", RED)
mini(5, "A new row is added beside it", "source_key 194427 · version 2 · TRANSAMT 195000000.00\nsuperseded_at  (empty: now the current version)", GREEN)
ax.add_patch(FancyArrowPatch((120, 23.4), (120, 18.6), arrowstyle="-|>", mutation_scale=13, color=NAVY, lw=1.5, zorder=3))
ax.text(86, 2.4, "Reports for October use version 1 (as it stood then); reports from November use version 2.\nIf the same posting arrives again unchanged, its fingerprint matches and nothing is added.",
        fontsize=8.2, color=GREY, va="top", linespacing=1.4)

# links between books
def link(x1, y1, x2, y2, label):
    ax.add_patch(FancyArrowPatch((x1, y1), (x2, y2), arrowstyle="-|>", mutation_scale=12, color=GREY, lw=1.2, zorder=1))
    ax.text((x1 + x2) / 2, (y1 + y2) / 2 + 1, label, ha="center", fontsize=7.8, color=GREY, style="italic")
link(50.6, 78, 55.4, 78, "")
link(104.6, 78, 109.4, 78, "")
ax.text(53, 80.5, "defines", ha="center", fontsize=7.8, color=GREY, style="italic")
ax.text(107, 80.5, "contains", ha="center", fontsize=7.8, color=GREY, style="italic")
link(80, 55.6, 50, 50.6, "")
ax.text(70, 53.6, "each record points to its delivery", fontsize=7.8, color=GREY, style="italic")

fig.savefig(OUT, bbox_inches="tight", facecolor="white")
print("saved", OUT)
