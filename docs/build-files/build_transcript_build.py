"""Export the MAIIC specification-v4 part (6 to 8 October 2026) of the Claude Code session to PDF and Markdown, for review by other tools."""
import json, re, os, html
from reportlab.lib.pagesizes import A4
from reportlab.lib.units import mm
from reportlab.lib import colors
from reportlab.lib.styles import ParagraphStyle
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.platypus import BaseDocTemplate, PageTemplate, Frame, Paragraph, Spacer, Preformatted, Table, TableStyle

SESSION = r"C:\Users\wadza\.claude\projects\c--xampp-htdocs-Stress-Testing-App\ee984144-7b28-4df1-9a6e-560f112869e9.jsonl"
OUTDIR = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\2. Documents from clients\Raw Query Scripts\Query Requests to MAIIC"
NAME = "Claude_Session_Transcript_Build_2026-10-08"
START_MARK = "please build the code and do everything"
END_MARK = "__no_end_marker__"

rows = [json.loads(l) for l in open(SESSION, encoding="utf-8") if l.strip()]


def user_text(r):
    c = r.get("message", {}).get("content")
    if isinstance(c, str):
        return c
    return "\n".join(b.get("text", "") for b in c if isinstance(b, dict) and b.get("type") == "text")


start = next(i for i, r in enumerate(rows) if r.get("type") == "user" and START_MARK in user_text(r))
end = next((i for i, r in enumerate(rows) if i > start and r.get("type") == "user" and END_MARK in user_text(r)), len(rows))


def clean_user(t):
    t = re.sub(r"<system-reminder>.*?</system-reminder>", "", t, flags=re.S)
    t = re.sub(r"<ide_opened_file>.*?</ide_opened_file>", "", t, flags=re.S)
    t = re.sub(r"<ide_selection>.*?</ide_selection>", "", t, flags=re.S)
    return t.strip()


def result_text(content):
    if isinstance(content, str):
        return content
    out = []
    for b in content or []:
        if b.get("type") == "text":
            out.append(b.get("text", ""))
        elif b.get("type") == "image":
            out.append("[image output: not reproduced in this transcript]")
    return "\n".join(out)


def expand_persisted(t):
    """Where the harness stored a long output in a side file, inline the whole file."""
    m = re.search(r"Full output saved to: (\S.*?\.txt)", t)
    if m and os.path.exists(m.group(1)):
        return "[long output, reproduced in full from the saved file]\n" + open(m.group(1), encoding="utf-8", errors="replace").read()
    return t


# ---------------------------------------------------------------- collect events
events = []            # (kind, title, body)   kind: user | assistant | tool | result | note
tool_names = {}
for r in rows[start:end]:
    typ = r.get("type")
    if typ not in ("user", "assistant") or r.get("isMeta"):
        continue
    ts = (r.get("timestamp") or "")[:19].replace("T", " ")
    c = r.get("message", {}).get("content")
    blocks = [{"type": "text", "text": c}] if isinstance(c, str) else (c or [])
    for b in blocks:
        bt = b.get("type")
        if typ == "user" and bt == "text":
            raw = b.get("text", "")
            if "<task-notification>" in raw or "SYSTEM NOTIFICATION" in raw:
                events.append(("note", f"System notification  ({ts} UTC)", re.sub(r"<[^>]+>", " ", raw).strip()))
                continue
            t = clean_user(raw)
            if t:
                events.append(("user", f"USER  ({ts} UTC)", t))
        elif typ == "user" and bt == "tool_result":
            name = tool_names.get(b.get("tool_use_id"), "tool")
            body = expand_persisted(result_text(b.get("content")))
            body = re.sub(r"<system-reminder>.*?</system-reminder>", "", body, flags=re.S).rstrip()
            events.append(("result", f"Result of {name}" + ("  [error]" if b.get("is_error") else ""), body or "(no output)"))
        elif typ == "assistant" and bt == "text":
            if b.get("text", "").strip():
                events.append(("assistant", f"CLAUDE  ({ts} UTC)", b["text"].strip()))
        elif typ == "assistant" and bt == "tool_use":
            tool_names[b.get("id")] = b.get("name")
            inp = b.get("input", {})
            parts = []
            for k in ("description", "file_path", "path", "pattern", "skill", "offset", "limit"):
                if k in inp:
                    parts.append(f"{k}: {inp[k]}")
            for k in ("command", "content", "old_string", "new_string"):
                if k in inp:
                    parts.append(f"--- {k} ---\n{inp[k]}")
            other = {k: v for k, v in inp.items() if k not in ("description", "file_path", "path", "pattern", "skill", "offset", "limit", "command", "content", "old_string", "new_string")}
            if other:
                parts.append(json.dumps(other, indent=1)[:4000])
            events.append(("tool", f"Tool call: {b.get('name')}", "\n".join(parts)))

HEADER = f"""This is the complete record of the part of a Claude Code session (5 and 6 October 2026) that dealt with the three raw query scripts MAIIC sent for Extracts A, B and C. It is saved so that another member of staff, with or without an AI tool, can follow what was done, in what order, and why.

It starts at the request to analyse the scripts and runs to the audit of the eighteen follow-up extracts MAIIC returned on 7 October 2026 and the notes written from it. Earlier, unrelated parts of the same session are left out.

The work falls into six stages, in time order:
1. Analysis of the three scripts and tests against the delivered extract files (5 October).
2. The data dictionary query pack and its run order.
3. The plain-language PDF report, version 1, and the session transcript and build files saved for review.
4. The independent review by Codex, the response to it, and version 2 of the report and query pack, including suggested changes to MAIIC's scripts and to the Dupleix engine code.
5. The data dictionary results returned by MAIIC on 6 October, what they showed, and the follow-up extract queries written from them.
6. The audit of the eighteen follow-up extracts received on 7 October: date formats, reconciliations to the balance history, loan book, Extract C and trial balance, the interest rebuild, the repayment-profile tests, and the notes to Barry and Credit that followed.

What is included: every user message, every reply shown to the user, every tool call with its full input (commands, scripts and file contents), and every tool result in full. Where a long result was stored by the tooling in a side file, that file is reproduced here.

What is left out: the assistant's internal reasoning, automatic system reminders (they carry unrelated workspace context), the text of the PDF skill, and image outputs (page renders used for visual checks).

How to follow it: the files produced along the way are in the same folder as this transcript. The Build files subfolder holds the Python scripts, with a README. The data dictionary results themselves are in the Query Request Responses 6 Oct 2026 folder next to this one.

Corrections made during the session, left in place where they were made: (a) the first analysis said Extract A shows one disbursement tranche per loan, which a later test showed was wrong; (b) version 1 of the report overstated several points that the Codex review identified, and version 2 corrects them. Both are explained in the report's Appendix E.

Times are UTC. Source: the session log {os.path.basename(SESSION)}, entries {start} to {end - 1}. Events: {len(events)}."""

# ---------------------------------------------------------------- markdown
md = [f"# Claude session transcript: MAIIC raw query scripts\n", HEADER, "\n---\n"]
for kind, title, body in events:
    if kind in ("user", "assistant"):
        md.append(f"\n## {title}\n\n{body}\n")
    else:
        fence = "````"
        md.append(f"\n**{title}**\n\n{fence}\n{body}\n{fence}\n")
open(os.path.join(OUTDIR, NAME + ".md"), "w", encoding="utf-8").write("\n".join(md))

# ---------------------------------------------------------------- pdf
F = r"C:\Windows\Fonts"
pdfmetrics.registerFont(TTFont("Seg", F + r"\segoeui.ttf")); pdfmetrics.registerFont(TTFont("Seg-B", F + r"\segoeuib.ttf"))
pdfmetrics.registerFont(TTFont("Seg-I", F + r"\segoeuii.ttf")); pdfmetrics.registerFont(TTFont("Seg-BI", F + r"\segoeuiz.ttf"))
pdfmetrics.registerFontFamily("Seg", normal="Seg", bold="Seg-B", italic="Seg-I", boldItalic="Seg-BI")
pdfmetrics.registerFont(TTFont("Mono", F + r"\consola.ttf"))
NAVY = colors.HexColor("#1B2A41"); ORANGE = colors.HexColor("#E07B00"); GREY = colors.HexColor("#5B6470"); LINE = colors.HexColor("#C9D1DB")
st_title = ParagraphStyle("t", fontName="Seg-B", fontSize=20, leading=25, textColor=NAVY, spaceAfter=8)
st_p = ParagraphStyle("p", fontName="Seg", fontSize=9.5, leading=13.5, spaceAfter=5)
st_user = ParagraphStyle("u", fontName="Seg-B", fontSize=10.5, leading=14, textColor=colors.white, backColor=NAVY, borderPadding=(3, 5, 3, 5), spaceBefore=12, spaceAfter=7, keepWithNext=1)
st_asst = ParagraphStyle("a", fontName="Seg-B", fontSize=10.5, leading=14, textColor=colors.white, backColor=ORANGE, borderPadding=(3, 5, 3, 5), spaceBefore=12, spaceAfter=7, keepWithNext=1)
st_tool = ParagraphStyle("tl", fontName="Seg-B", fontSize=8.5, leading=11, textColor=GREY, spaceBefore=7, spaceAfter=2, keepWithNext=1)
st_mono = ParagraphStyle("m", fontName="Mono", fontSize=6.6, leading=8.3, textColor=colors.HexColor("#202020"))
st_h = ParagraphStyle("h", fontName="Seg-B", fontSize=10.5, leading=14, textColor=NAVY, spaceBefore=6, spaceAfter=3)
BAD = re.compile(r"[\x00-\x08\x0b\x0c\x0e-\x1f\ufffe\uffff]")


def esc(t):
    return html.escape(BAD.sub("", t), quote=False)


def inline(t):
    t = esc(t)
    t = re.sub(r"\*\*(.+?)\*\*", r"<b>\1</b>", t)
    t = re.sub(r"`([^`]+)`", r'<font name="Mono" size="8.5">\1</font>', t)
    return t


def mono(story, text, cols=128):
    lines = BAD.sub("", text.replace("\t", "    ").replace("\r", "")).split("\n")
    for i in range(0, len(lines), 70):
        story.append(Preformatted("\n".join(lines[i:i + 70]), st_mono, maxLineLength=cols, splitChars=" ,;|/\\", newLineChars=""))


def prose(story, text):
    buf = []                                   # consecutive table / code lines go out as mono

    def flush():
        if buf:
            mono(story, "\n".join(buf)); story.append(Spacer(1, 4)); buf.clear()
    in_code = False
    for line in text.split("\n"):
        s = line.rstrip()
        if s.strip().startswith("```"):
            in_code = not in_code; continue
        if in_code or s.lstrip().startswith("|"):
            buf.append(s); continue
        flush()
        if not s.strip():
            continue
        m = re.match(r"^(#{1,4})\s+(.*)", s)
        if m:
            story.append(Paragraph(inline(m.group(2)), st_h)); continue
        m = re.match(r"^(\s*)([-*]|\d+\.)\s+(.*)", s)
        if m:
            ind = 10 + 10 * (len(m.group(1)) // 2)
            mark = "•" if m.group(2) in "-*" else m.group(2)
            story.append(Paragraph(f"{mark} {inline(m.group(3))}", ParagraphStyle("b", parent=st_p, leftIndent=ind, firstLineIndent=-9, spaceAfter=2)))
            continue
        story.append(Paragraph(inline(s), st_p))
    flush()


story = [Paragraph("Claude session transcript: MAIIC raw query scripts", st_title)]
for para in HEADER.split("\n\n"):
    for i, ln in enumerate(para.split("\n")):
        story.append(Paragraph(inline(ln), st_p))
story.append(Spacer(1, 6))
for kind, title, body in events:
    if kind == "user":
        story.append(Paragraph(esc(title), st_user)); prose(story, body)
    elif kind == "assistant":
        story.append(Paragraph(esc(title), st_asst)); prose(story, body)
    else:
        story.append(Paragraph(esc(title), st_tool)); mono(story, body); story.append(Spacer(1, 3))


def footer(canvas, doc):
    canvas.saveState(); canvas.setStrokeColor(LINE); canvas.setLineWidth(0.5)
    canvas.line(14 * mm, 12 * mm, A4[0] - 14 * mm, 12 * mm)
    canvas.setFont("Seg", 7.5); canvas.setFillColor(GREY)
    canvas.drawString(14 * mm, 8 * mm, "Claude session transcript: MAIIC raw query scripts  ·  5 October 2026  ·  for review")
    canvas.drawRightString(A4[0] - 14 * mm, 8 * mm, f"Page {doc.page}"); canvas.restoreState()


out = os.path.join(OUTDIR, NAME + ".pdf")
doc = BaseDocTemplate(out, pagesize=A4, leftMargin=14 * mm, rightMargin=14 * mm, topMargin=13 * mm, bottomMargin=17 * mm,
                      title="Claude session transcript: MAIIC raw query scripts (complete)", author="Dupleix Institute (Claude Code session)")
doc.addPageTemplates([PageTemplate(id="p", frames=[Frame(doc.leftMargin, doc.bottomMargin, doc.width, doc.height, id="f",
                                                         leftPadding=0, rightPadding=0, topPadding=0, bottomPadding=0)], onPage=footer)])
doc.build(story)
kinds = {}
for k, _, _ in events:
    kinds[k] = kinds.get(k, 0) + 1
print("events", kinds, "| chars", sum(len(b) for _, _, b in events))
print("first:", events[0][1], "| last:", events[-1][1], "|", events[-1][2][:80].replace("\n", " "))
print("written", out)
