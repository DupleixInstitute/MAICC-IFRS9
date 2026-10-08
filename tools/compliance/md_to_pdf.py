"""Render a plain-language spec (Markdown subset) to PDF with reportlab.
Handles: # / ## / ### headings, paragraphs, bullet and numbered lists, pipe
tables, fenced code blocks, > quotes, **bold**, `code`, *italic*, --- rules."""
import re, sys, io
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.units import mm
from reportlab.lib import colors
from reportlab.platypus import (SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle,
                                Preformatted, HRFlowable, KeepTogether)

src, out = sys.argv[1], sys.argv[2]
text = io.open(src, encoding="utf-8").read()

ss = getSampleStyleSheet()
base = ParagraphStyle("base", parent=ss["Normal"], fontName="Helvetica", fontSize=9.5, leading=13.5, spaceAfter=5)
h1 = ParagraphStyle("h1", parent=base, fontName="Helvetica-Bold", fontSize=16, leading=20, spaceBefore=4, spaceAfter=8)
h2 = ParagraphStyle("h2", parent=base, fontName="Helvetica-Bold", fontSize=12.5, leading=16, spaceBefore=12, spaceAfter=5, textColor=colors.HexColor("#7A5A00"))
h3 = ParagraphStyle("h3", parent=base, fontName="Helvetica-Bold", fontSize=10.5, leading=14, spaceBefore=8, spaceAfter=3)
bul = ParagraphStyle("bul", parent=base, leftIndent=14, bulletIndent=3, spaceAfter=3)
quote = ParagraphStyle("quote", parent=base, leftIndent=12, textColor=colors.HexColor("#333333"), backColor=colors.HexColor("#F5F1E6"), borderPadding=(4, 6, 4, 6), spaceBefore=4, spaceAfter=8)
code = ParagraphStyle("code", parent=base, fontName="Courier", fontSize=8.2, leading=10.5, backColor=colors.HexColor("#F2F2F2"), borderPadding=(5, 6, 5, 6), leftIndent=4, spaceBefore=3, spaceAfter=8)
cell = ParagraphStyle("cell", parent=base, fontSize=8.3, leading=11, spaceAfter=0)
cellh = ParagraphStyle("cellh", parent=cell, fontName="Helvetica-Bold")
meta = ParagraphStyle("meta", parent=base, fontSize=8.8, leading=12, textColor=colors.HexColor("#444444"), spaceAfter=2)


def esc(s):
    return s.replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;")


def inline(s):
    s = esc(s)
    s = re.sub(r"`([^`]+)`", r'<font face="Courier" size="8.5">\1</font>', s)
    s = re.sub(r"\*\*([^*]+)\*\*", r"<b>\1</b>", s)
    s = re.sub(r"(?<![\w*])\*([^*]+)\*(?![\w*])", r"<i>\1</i>", s)
    s = s.replace("→", "&rarr;").replace("—", "&mdash;").replace("–", "&ndash;").replace("×", "&times;").replace("✓", "yes").replace("…", "...")
    return s


story = []
lines = text.split("\n")
i = 0
para = []


def flush():
    global para
    if para:
        story.append(Paragraph(inline(" ".join(para)), base))
        para = []


while i < len(lines):
    l = lines[i]
    st = l.strip()
    if st.startswith("```"):
        flush()
        buf = []
        i += 1
        while i < len(lines) and not lines[i].strip().startswith("```"):
            buf.append(lines[i]); i += 1
        story.append(Preformatted("\n".join(buf), code))
        i += 1; continue
    if st == "---":
        flush(); story.append(HRFlowable(width="100%", thickness=0.6, color=colors.HexColor("#BBBBBB"), spaceBefore=6, spaceAfter=8)); i += 1; continue
    if st.startswith("# "):
        flush(); story.append(Paragraph(inline(st[2:]), h1)); i += 1; continue
    if st.startswith("## "):
        flush(); story.append(Paragraph(inline(st[3:]), h2)); i += 1; continue
    if st.startswith("### "):
        flush(); story.append(Paragraph(inline(st[4:]), h3)); i += 1; continue
    if st.startswith("|"):
        flush()
        rows = []
        while i < len(lines) and lines[i].strip().startswith("|"):
            r = lines[i].strip().strip("|")
            if not re.fullmatch(r"[\s|:-]+", r):
                rows.append([c.strip() for c in r.split("|")])
            i += 1
        if rows:
            ncol = max(len(r) for r in rows)
            data = [[Paragraph(inline(c), cellh if ri == 0 else cell) for c in (r + [""] * (ncol - len(r)))] for ri, r in enumerate(rows)]
            avail = A4[0] - 36 * mm
            widths = [avail / ncol] * ncol
            if ncol == 2: widths = [avail * 0.28, avail * 0.72]
            if ncol == 3: widths = [avail * 0.14, avail * 0.60, avail * 0.26]
            if ncol == 4: widths = [avail * 0.26, avail * 0.12, avail * 0.40, avail * 0.22]
            t = Table(data, colWidths=widths, repeatRows=1)
            t.setStyle(TableStyle([
                ("BACKGROUND", (0, 0), (-1, 0), colors.HexColor("#EFE7CF")),
                ("GRID", (0, 0), (-1, -1), 0.4, colors.HexColor("#BBBBBB")),
                ("VALIGN", (0, 0), (-1, -1), "TOP"),
                ("LEFTPADDING", (0, 0), (-1, -1), 4), ("RIGHTPADDING", (0, 0), (-1, -1), 4),
                ("TOPPADDING", (0, 0), (-1, -1), 3), ("BOTTOMPADDING", (0, 0), (-1, -1), 3),
            ]))
            story.append(t); story.append(Spacer(1, 6))
        continue
    if st.startswith(">"):
        flush()
        buf = []
        while i < len(lines) and lines[i].strip().startswith(">"):
            buf.append(lines[i].strip()[1:].strip()); i += 1
        story.append(Paragraph(inline(" ".join(buf)), quote)); continue
    m = re.match(r"^(\s*)([-*]|\d+\.)\s+(.*)$", l)
    if m and not st.startswith("**"):
        flush()
        indent = len(m.group(1))
        marker = m.group(2)
        body = [m.group(3)]
        i += 1
        while i < len(lines) and lines[i].strip() and not re.match(r"^\s*([-*]|\d+\.)\s+", lines[i]) and not lines[i].strip().startswith(("#", "|", "```", ">")):
            body.append(lines[i].strip()); i += 1
        bullet = "•" if marker in "-*" else marker
        style = ParagraphStyle("b%d" % indent, parent=bul, leftIndent=14 + indent * 4, bulletIndent=3 + indent * 4)
        story.append(Paragraph(inline(" ".join(body)), style, bulletText=bullet))
        continue
    if st.startswith("**") and st.endswith("**") and st.count("**") == 2 and len(st) < 90:
        flush(); story.append(Paragraph(inline(st), h3)); i += 1; continue
    if st == "":
        flush(); i += 1; continue
    if re.match(r"^\*\*[A-Za-z ]+:\*\*", st) and len(para) == 0 and i < 8:
        story.append(Paragraph(inline(st), meta)); i += 1; continue
    para.append(st); i += 1
flush()

doc = SimpleDocTemplate(out, pagesize=A4, leftMargin=18 * mm, rightMargin=18 * mm, topMargin=16 * mm, bottomMargin=16 * mm,
                        title=re.sub(r"^# ", "", lines[0]), author="Dupleix Institute")


def footer(canvas, d):
    canvas.saveState(); canvas.setFont("Helvetica", 7.5); canvas.setFillColor(colors.HexColor("#666666"))
    canvas.drawString(18 * mm, 9 * mm, "MAIIC IFRS 9 - " + re.sub(r"^# ", "", lines[0])[:90])
    canvas.drawRightString(A4[0] - 18 * mm, 9 * mm, "Page %d" % d.page); canvas.restoreState()


doc.build(story, onFirstPage=footer, onLaterPages=footer)
print("wrote", out)
