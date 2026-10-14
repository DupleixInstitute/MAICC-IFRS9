from pathlib import Path
F = Path(r"C:\xampp\htdocs\MAICC-IFRS9\docs\build-files\spec_render.py")
s = F.read_text(encoding="utf-8")

def sub(a, b):
    global s
    assert a in s, a[:70]
    s = s.replace(a, b, 1)

# docstring: document the two new front-matter keys
sub("prepared, people, status;", "prepared, people, status, and optionally logo (an image beside the .md, placed top right of page 1)\nand justify (yes: paragraphs and list items fully justified);")

# ---------------- docx
sub("""    k = doc.add_paragraph(); r = k.add_run(meta.get("kicker", "")); r.font.size = Pt(9); r.font.color.rgb = GREY; r.bold = True""",
    """    JUST = meta.get("justify", "").lower() in ("yes", "true", "1")
    if meta.get("logo"):
        lp = doc.add_paragraph(); lp.alignment = WD_ALIGN_PARAGRAPH.RIGHT; lp.add_run().add_picture(str(out.parent / meta["logo"]), width=Cm(4.2))
    k = doc.add_paragraph(); r = k.add_run(meta.get("kicker", "")); r.font.size = Pt(9); r.font.color.rgb = GREY; r.bold = True""")
sub("""        elif kind == "p": p = doc.add_paragraph(); add_runs(p, content); p.paragraph_format.space_after = Pt(6)
        elif kind == "img":""",
    """        elif kind == "p":
            p = doc.add_paragraph(); add_runs(p, content); p.paragraph_format.space_after = Pt(6)
            if JUST: p.alignment = WD_ALIGN_PARAGRAPH.JUSTIFY
        elif kind == "img":""")
sub("""                p = doc.add_paragraph(style="List Bullet" if kind == "ul" else "List Number"); add_runs(p, item)""",
    """                p = doc.add_paragraph(style="List Bullet" if kind == "ul" else "List Number"); add_runs(p, item)
                if JUST: p.alignment = WD_ALIGN_PARAGRAPH.JUSTIFY""")
sub("""                    if i == 0:
                        shd = OxmlElement("w:shd");""",
    """                    if j == 0:
                        # a row never breaks across two pages; the header row repeats on every page a table runs onto
                        trPr = tbl.rows[i]._tr.get_or_add_trPr(); cs = OxmlElement("w:cantSplit"); trPr.append(cs)
                        if i == 0: th = OxmlElement("w:tblHeader"); trPr.append(th)
                    if i == 0:
                        shd = OxmlElement("w:shd");""")

# ---------------- pdf
sub("""    from reportlab.platypus import BaseDocTemplate, PageTemplate, Frame, Paragraph, Spacer, LongTable, TableStyle, ListFlowable, ListItem, PageBreak""",
    """    from reportlab.platypus import BaseDocTemplate, PageTemplate, Frame, Paragraph, Spacer, LongTable, TableStyle, ListFlowable, ListItem, PageBreak, KeepTogether
    from reportlab.lib.enums import TA_JUSTIFY
    from reportlab.lib.utils import ImageReader""")
sub("""    def inline(text):""",
    """    if meta.get("justify", "").lower() in ("yes", "true", "1"):
        S["p"].alignment = TA_JUSTIFY; S["li"].alignment = TA_JUSTIFY
    def inline(text):""")
sub("""    W = A4[0] - 40 * mm; s = []
    s.append(Paragraph(meta.get("kicker", ""), S["kicker"]))""",
    """    W = A4[0] - 40 * mm; s = []
    LOGO = None
    if meta.get("logo"):
        LOGO = str(out.parent / meta["logo"]); lw0, lh0 = ImageReader(LOGO).getSize(); LOGO_W = 42 * mm; LOGO_H = LOGO_W * lh0 / lw0
        s.append(Spacer(1, LOGO_H - 4 * mm))
    s.append(Paragraph(meta.get("kicker", ""), S["kicker"]))""")
# images with their caption stay together
sub("""            s.append(RLImage(f, width=W, height=W * ih / iw))
            if cap: s.append(Paragraph(f"<i>{html.escape(cap)}</i>", ParagraphStyle("cap", parent=S["p"], fontSize=8.5, textColor=GREY, spaceBefore=3)))""",
    """            fig = [RLImage(f, width=W, height=W * ih / iw)]
            if cap: fig.append(Paragraph(f"<i>{html.escape(cap)}</i>", ParagraphStyle("cap", parent=S["p"], fontSize=8.5, textColor=GREY, spaceBefore=3, alignment=0)))
            s.append(KeepTogether(fig))""")
# tables: a short table never splits; a long one repeats its header; no row is ever split
sub("""            t.setStyle(TableStyle(sty)); s.append(t); s.append(Spacer(1, 8))""",
    """            t.setStyle(TableStyle(sty)); t.splitInRow = 0
            s.append(KeepTogether([t]) if len(data) <= 16 else t); s.append(Spacer(1, 8))""")
# logo drawn on page 1
sub("""    def footer(c, d):
        c.saveState();""",
    """    def footer(c, d):
        if LOGO and d.page == 1:
            c.drawImage(LOGO, A4[0] - 20 * mm - LOGO_W, A4[1] - 12 * mm - LOGO_H, width=LOGO_W, height=LOGO_H, mask="auto")
        c.saveState();""")
F.write_text(s, encoding="utf-8")
print("renderer patched")
