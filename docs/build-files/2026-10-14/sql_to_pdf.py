"""Render a .sql run sheet to a monospace A4 PDF with a title band and page numbers."""
import sys, textwrap
from reportlab.lib.pagesizes import A4
from reportlab.lib.units import mm
from reportlab.lib import colors
from reportlab.pdfgen import canvas

src, out, title, sub = sys.argv[1], sys.argv[2], sys.argv[3], sys.argv[4]
lines = open(src, encoding="utf-8").read().splitlines()
W, H = A4
left, top, bottom = 16 * mm, H - 22 * mm, 16 * mm
font, size, lead = "Courier", 8.2, 10.2
maxw = 108  # characters per line at this size

def header(c, page):
    c.setFillColor(colors.HexColor("#0b2b1a")); c.rect(0, H - 14 * mm, W, 14 * mm, fill=1, stroke=0)
    c.setFillColor(colors.white); c.setFont("Helvetica-Bold", 10); c.drawString(left, H - 9 * mm, title)
    c.setFont("Helvetica", 7.5); c.drawRightString(W - left, H - 9 * mm, sub)
    c.setFillColor(colors.HexColor("#666666")); c.setFont("Helvetica", 7.5)
    c.drawString(left, 9 * mm, "Read-only queries. Run RUN 0 first. One CSV per query, named as shown.")
    c.drawRightString(W - left, 9 * mm, "Page %d" % page)
    c.setFillColor(colors.black); c.setFont(font, size)

c = canvas.Canvas(out, pagesize=A4); page = 1; header(c, page); y = top
for raw in lines:
    pieces = textwrap.wrap(raw, maxw, subsequent_indent="       ", drop_whitespace=False) or [""]
    for piece in pieces:
        if y < bottom + lead:
            c.showPage(); page += 1; header(c, page); y = top
        if raw.lstrip().startswith("-- RUN") or raw.startswith("--====") or raw.lstrip().startswith("-- "):
            c.setFillColor(colors.HexColor("#1f5f3f") if raw.lstrip().startswith("-- RUN") else colors.HexColor("#555555"))
            if raw.lstrip().startswith("-- RUN"): c.setFont("Courier-Bold", size)
        else:
            c.setFillColor(colors.black)
        c.drawString(left, y, piece); c.setFont(font, size); y -= lead
c.save(); print("written", out, "pages", page)
