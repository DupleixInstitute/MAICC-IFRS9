"""Make PDF and TXT copies of the follow-up SQL file for the email."""
import shutil
from reportlab.lib.pagesizes import A4
from reportlab.lib.units import mm
from reportlab.lib import colors
from reportlab.lib.styles import ParagraphStyle
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.platypus import BaseDocTemplate, PageTemplate, Frame, Preformatted, Paragraph, Spacer

D = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\2. Documents from clients\Raw Query Scripts\Query Requests to MAIIC"
BASE = D + r"\FOLLOW-UP extracts for Barry - pack 2 - 8 Oct 2026"
text = open(BASE + ".sql", encoding="utf-8").read().replace("\r\n", "\n")
shutil.copyfile(BASE + ".sql", BASE + ".txt")

F = r"C:\Windows\Fonts"
pdfmetrics.registerFont(TTFont("Mono", F + r"\consola.ttf"))
pdfmetrics.registerFont(TTFont("Seg", F + r"\segoeui.ttf"))
pdfmetrics.registerFont(TTFont("Seg-B", F + r"\segoeuib.ttf"))
GREY = colors.HexColor("#5B6470"); LINE = colors.HexColor("#C9D1DB"); NAVY = colors.HexColor("#1B2A41")
st_title = ParagraphStyle("t", fontName="Seg-B", fontSize=16, leading=20, textColor=NAVY, spaceAfter=4)
st_p = ParagraphStyle("p", fontName="Seg", fontSize=9.5, leading=13, textColor=GREY, spaceAfter=8)
st_mono = ParagraphStyle("m", fontName="Mono", fontSize=8, leading=10.2)

story = [Paragraph("MAIIC E-Banker: follow-up extracts, pack 2, 8 October 2026", st_title),
         Paragraph("PDF copy of the file FOLLOW-UP extracts for Barry - pack 2 - 8 Oct 2026.sql, for reading. Please run the queries "
                   "from the .sql or .txt attachment, which carry the same text. Eleven read-only queries in four groups: the September month-end pack, the interest arithmetic and the collateral, the year-end adjustments, and the Mega Farm schemes.", st_p),
         Spacer(1, 4)]
lines = text.split("\n")
for i in range(0, len(lines), 60):
    story.append(Preformatted("\n".join(lines[i:i + 60]), st_mono, maxLineLength=104, splitChars=" ,", newLineChars=""))


def footer(canvas, doc):
    canvas.saveState(); canvas.setStrokeColor(LINE); canvas.setLineWidth(0.5)
    canvas.line(16 * mm, 12 * mm, A4[0] - 16 * mm, 12 * mm)
    canvas.setFont("Seg", 8); canvas.setFillColor(GREY)
    canvas.drawString(16 * mm, 8 * mm, "FOLLOW-UP extracts for Barry, 6 October 2026  ·  Dupleix Institute  ·  read-only queries")
    canvas.drawRightString(A4[0] - 16 * mm, 8 * mm, f"Page {doc.page}"); canvas.restoreState()


doc = BaseDocTemplate(BASE + ".pdf", pagesize=A4, leftMargin=16 * mm, rightMargin=16 * mm, topMargin=14 * mm, bottomMargin=18 * mm,
                      title="MAIIC E-Banker: follow-up extracts, pack 2, 8 October 2026", author="Dupleix Institute")
doc.addPageTemplates([PageTemplate(id="p", frames=[Frame(doc.leftMargin, doc.bottomMargin, doc.width, doc.height, id="f",
                                                         leftPadding=0, rightPadding=0, topPadding=0, bottomPadding=0)], onPage=footer)])
doc.build(story)
print("written .pdf and .txt")
