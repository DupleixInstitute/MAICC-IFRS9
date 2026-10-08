"""Render a Dupleix specification written in Markdown to .docx and .pdf from one source.

Usage:  python spec_render.py "<path to .md>"
Writes the .docx and .pdf beside the .md with the same name.

Markdown understood: a front-matter block (--- ... ---) with kicker, title, subtitle, date,
prepared, people, status; '## ' and '### ' headings; paragraphs; '- ' bullets; '1. ' numbered
items; pipe tables with a header row; inline **bold**, *italic* and `code`.
"""
import re, sys, html
from pathlib import Path

# ----------------------------------------------------------------------------- parse
def parse(md: str):
    meta = {}
    body = md
    if md.startswith("---"):
        end = md.index("\n---", 3)
        for line in md[3:end].strip().splitlines():
            if ":" in line:
                k, v = line.split(":", 1); meta[k.strip()] = v.strip()
        body = md[end + 4:]
    blocks = []; para = []; table = []; lst = None
    def flush_para():
        nonlocal para
        if para: blocks.append(("p", " ".join(para))); para = []
    def flush_table():
        nonlocal table
        if table:
            rows = [[c.strip() for c in r.strip().strip("|").split("|")] for r in table if not re.match(r"^\s*\|?\s*:?-{2,}", r)]
            blocks.append(("table", rows)); table = []
    def flush_list():
        nonlocal lst
        if lst: blocks.append(lst); lst = None
    for raw in body.splitlines():
        line = raw.rstrip()
        if line.startswith("|"):
            flush_para(); flush_list(); table.append(line); continue
        else:
            flush_table()
        if not line.strip():
            flush_para(); flush_list(); continue
        m = re.match(r"^(#{2,3}) (.*)", line)
        if m:
            flush_para(); flush_list(); blocks.append(("h2" if len(m.group(1)) == 2 else "h3", m.group(2))); continue
        m = re.match(r"^- (.*)", line)
        if m:
            flush_para()
            if lst is None or lst[0] != "ul": flush_list(); lst = ("ul", [])
            lst[1].append(m.group(1)); continue
        m = re.match(r"^\d+\. (.*)", line)
        if m:
            flush_para()
            if lst is None or lst[0] != "ol": flush_list(); lst = ("ol", [])
            lst[1].append(m.group(1)); continue
        if lst is not None and line.startswith("  "):
            lst[1][-1] += " " + line.strip(); continue
        flush_list(); para.append(line.strip())
    flush_para(); flush_table(); flush_list()
    return meta, blocks

INLINE = re.compile(r"(\*\*.+?\*\*|`.+?`|\*.+?\*)")
def runs(text):
    """Split inline markdown into (text, bold, italic, code) runs."""
    out = []
    for part in INLINE.split(text):
        if not part: continue
        if part.startswith("**"): out.append((part[2:-2], True, False, False))
        elif part.startswith("`"): out.append((part[1:-1], False, False, True))
        elif part.startswith("*"): out.append((part[1:-1], False, True, False))
        else: out.append((part, False, False, False))
    return out

# ----------------------------------------------------------------------------- docx
def render_docx(meta, blocks, out: Path):
    from docx import Document
    from docx.shared import Pt, RGBColor, Cm
    from docx.enum.text import WD_ALIGN_PARAGRAPH
    from docx.oxml.ns import qn
    from docx.oxml import OxmlElement
    NAVY = RGBColor(0x1B, 0x2A, 0x41); ORANGE = RGBColor(0xE0, 0x7B, 0x00); GREY = RGBColor(0x5B, 0x64, 0x70)
    doc = Document()
    for s in doc.sections: s.left_margin = s.right_margin = Cm(2); s.top_margin = s.bottom_margin = Cm(2)
    st = doc.styles["Normal"]; st.font.name = "Segoe UI"; st.font.size = Pt(10); st.element.rPr.rFonts.set(qn("w:eastAsia"), "Segoe UI")
    for name, size, colour in (("Heading 1", 18, NAVY), ("Heading 2", 13, ORANGE), ("Heading 3", 11, NAVY)):
        h = doc.styles[name]; h.font.name = "Segoe UI"; h.font.size = Pt(size); h.font.bold = True; h.font.color.rgb = colour
        h.element.rPr.rFonts.set(qn("w:eastAsia"), "Segoe UI")
    def add_runs(p, text):
        for t, b, i, c in runs(text):
            r = p.add_run(t); r.bold = b; r.italic = i
            if c: r.font.name = "Consolas"; r.font.size = Pt(9)
    # cover
    k = doc.add_paragraph(); r = k.add_run(meta.get("kicker", "")); r.font.size = Pt(9); r.font.color.rgb = GREY; r.bold = True
    t = doc.add_paragraph(style="Heading 1"); t.add_run(meta.get("title", out.stem))
    if meta.get("subtitle"): p = doc.add_paragraph(); r = p.add_run(meta["subtitle"]); r.font.size = Pt(12); r.font.color.rgb = NAVY
    for key in ("date", "prepared", "people", "status"):
        if meta.get(key): p = doc.add_paragraph(); r = p.add_run(("" if key != "date" else "") + meta[key]); r.font.size = Pt(9); r.font.color.rgb = GREY
    for kind, content in blocks:
        if kind == "h2": doc.add_paragraph(content, style="Heading 2")
        elif kind == "h3": doc.add_paragraph(content, style="Heading 3")
        elif kind == "p": p = doc.add_paragraph(); add_runs(p, content); p.paragraph_format.space_after = Pt(6)
        elif kind in ("ul", "ol"):
            for item in content:
                p = doc.add_paragraph(style="List Bullet" if kind == "ul" else "List Number"); add_runs(p, item)
        elif kind == "table":
            rows = content; ncol = max(len(r) for r in rows)
            tbl = doc.add_table(rows=len(rows), cols=ncol); tbl.style = "Table Grid"
            for i, row in enumerate(rows):
                for j in range(ncol):
                    cell = tbl.cell(i, j); cell.text = ""
                    p = cell.paragraphs[0]; add_runs(p, row[j] if j < len(row) else "")
                    for r in p.runs: r.font.size = Pt(8.5); r.bold = r.bold or i == 0
                    if i == 0:
                        shd = OxmlElement("w:shd"); shd.set(qn("w:val"), "clear"); shd.set(qn("w:color"), "auto"); shd.set(qn("w:fill"), "1B2A41"); cell._tc.get_or_add_tcPr().append(shd)
                        for r in p.runs: r.font.color.rgb = RGBColor(0xFF, 0xFF, 0xFF)
            doc.add_paragraph()
    doc.core_properties.title = meta.get("title", out.stem); doc.core_properties.author = "Dupleix Institute"
    doc.save(out)

# ----------------------------------------------------------------------------- pdf
def render_pdf(meta, blocks, out: Path):
    from reportlab.lib.pagesizes import A4
    from reportlab.lib.units import mm
    from reportlab.lib import colors
    from reportlab.lib.styles import ParagraphStyle
    from reportlab.pdfbase import pdfmetrics
    from reportlab.pdfbase.ttfonts import TTFont
    from reportlab.platypus import BaseDocTemplate, PageTemplate, Frame, Paragraph, Spacer, LongTable, TableStyle, ListFlowable, ListItem, PageBreak
    F = r"C:\Windows\Fonts"
    for n, f in (("Seg", "segoeui.ttf"), ("Seg-B", "segoeuib.ttf"), ("Seg-I", "segoeuii.ttf"), ("Seg-BI", "segoeuiz.ttf")):
        pdfmetrics.registerFont(TTFont(n, F + "\\" + f))
    pdfmetrics.registerFontFamily("Seg", normal="Seg", bold="Seg-B", italic="Seg-I", boldItalic="Seg-BI")
    try: pdfmetrics.registerFont(TTFont("Mono", F + r"\consola.ttf")); MONO = "Mono"
    except Exception: MONO = "Courier"
    NAVY = colors.HexColor("#1B2A41"); ORANGE = colors.HexColor("#E07B00"); GREY = colors.HexColor("#5B6470"); LIGHT = colors.HexColor("#F4F6F9"); LINE = colors.HexColor("#C9D1DB")
    S = {"kicker": ParagraphStyle("k", fontName="Seg-B", fontSize=9, textColor=GREY, spaceAfter=6),
         "title": ParagraphStyle("t", fontName="Seg-B", fontSize=24, leading=30, textColor=NAVY, spaceAfter=6),
         "sub": ParagraphStyle("s", fontName="Seg", fontSize=12.5, leading=17, textColor=NAVY, spaceAfter=10),
         "meta": ParagraphStyle("m", fontName="Seg", fontSize=9.5, leading=13.5, textColor=GREY, spaceAfter=4),
         "h2": ParagraphStyle("h2", fontName="Seg-B", fontSize=14, leading=18, textColor=ORANGE, spaceBefore=14, spaceAfter=6, keepWithNext=1),
         "h3": ParagraphStyle("h3", fontName="Seg-B", fontSize=11.5, leading=15, textColor=NAVY, spaceBefore=9, spaceAfter=4, keepWithNext=1),
         "p": ParagraphStyle("p", fontName="Seg", fontSize=10, leading=14.5, spaceAfter=7),
         "li": ParagraphStyle("li", fontName="Seg", fontSize=10, leading=14.5),
         "cell": ParagraphStyle("c", fontName="Seg", fontSize=8.4, leading=11),
         "head": ParagraphStyle("hd", fontName="Seg-B", fontSize=8.4, leading=11, textColor=colors.white)}
    def inline(text):
        t = html.escape(text, quote=False)
        t = re.sub(r"\*\*(.+?)\*\*", r"<b>\1</b>", t); t = re.sub(r"`(.+?)`", rf'<font face="{MONO}" size="8.6">\1</font>', t); t = re.sub(r"(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])", r"<i>\1</i>", t)
        return t
    W = A4[0] - 40 * mm; s = []
    s.append(Paragraph(meta.get("kicker", ""), S["kicker"])); s.append(Paragraph(meta.get("title", out.stem), S["title"]))
    if meta.get("subtitle"): s.append(Paragraph(meta["subtitle"], S["sub"]))
    for key in ("date", "prepared", "people", "status"):
        if meta.get(key): s.append(Paragraph(inline(meta[key]), S["meta"]))
    s.append(Spacer(1, 8))
    for kind, content in blocks:
        if kind == "h2": s.append(Paragraph(inline(content), S["h2"]))
        elif kind == "h3": s.append(Paragraph(inline(content), S["h3"]))
        elif kind == "p": s.append(Paragraph(inline(content), S["p"]))
        elif kind in ("ul", "ol"):
            s.append(ListFlowable([ListItem(Paragraph(inline(i), S["li"]), leftIndent=14, spaceAfter=3) for i in content],
                                  bulletType="1" if kind == "ol" else "bullet", start=None if kind == "ol" else "•", leftIndent=14,
                                  bulletColor=NAVY if kind == "ol" else ORANGE, bulletFontSize=9, bulletFontName="Seg")); s.append(Spacer(1, 5))
        elif kind == "table":
            rows = content; ncol = max(len(r) for r in rows)
            # column widths in proportion to the longest cell text per column, bounded
            lens = [max(len(r[j]) if j < len(r) else 0 for r in rows) for j in range(ncol)]
            # a column is never narrower than its longest header word, so a header never breaks mid-word
            heads = [max((len(w) for w in rows[0][j].split()), default=0) + 2 if j < len(rows[0]) else 0 for j in range(ncol)]
            lens = [min(max(l, 14, h), 70) for l, h in zip(lens, heads)]; tot = sum(lens); widths = [W * l / tot for l in lens]
            data = [[Paragraph(inline(row[j]) if j < len(row) else "", S["head"] if i == 0 else S["cell"]) for j in range(ncol)] for i, row in enumerate(rows)]
            t = LongTable(data, colWidths=widths, repeatRows=1)
            sty = [("BACKGROUND", (0, 0), (-1, 0), NAVY), ("VALIGN", (0, 0), (-1, -1), "TOP"), ("LEFTPADDING", (0, 0), (-1, -1), 4), ("RIGHTPADDING", (0, 0), (-1, -1), 4),
                   ("TOPPADDING", (0, 0), (-1, -1), 3), ("BOTTOMPADDING", (0, 0), (-1, -1), 4), ("LINEBELOW", (0, 0), (-1, -1), 0.4, LINE), ("BOX", (0, 0), (-1, -1), 0.6, LINE)]
            for i in range(2, len(data), 2): sty.append(("BACKGROUND", (0, i), (-1, i), LIGHT))
            t.setStyle(TableStyle(sty)); s.append(t); s.append(Spacer(1, 8))
    title = meta.get("title", out.stem); date = meta.get("date", "")
    def footer(c, d):
        c.saveState(); c.setStrokeColor(LINE); c.setLineWidth(0.5); c.line(20 * mm, 14 * mm, A4[0] - 20 * mm, 14 * mm)
        c.setFont("Seg", 8); c.setFillColor(GREY); c.drawString(20 * mm, 9.5 * mm, f"{meta.get('kicker', '')}  ·  {title}  ·  {date}")
        c.drawRightString(A4[0] - 20 * mm, 9.5 * mm, f"Page {d.page}"); c.restoreState()
    doc = BaseDocTemplate(str(out), pagesize=A4, leftMargin=20 * mm, rightMargin=20 * mm, topMargin=16 * mm, bottomMargin=20 * mm, title=title, author="Dupleix Institute")
    doc.addPageTemplates([PageTemplate(id="p", frames=[Frame(doc.leftMargin, doc.bottomMargin, doc.width, doc.height, id="f", leftPadding=0, rightPadding=0, topPadding=0, bottomPadding=0)], onPage=footer)])
    doc.build(s)

if __name__ == "__main__":
    src = Path(sys.argv[1]); meta, blocks = parse(src.read_text(encoding="utf-8"))
    render_docx(meta, blocks, src.with_suffix(".docx")); render_pdf(meta, blocks, src.with_suffix(".pdf"))
    print("written", src.with_suffix(".docx")); print("written", src.with_suffix(".pdf"))
