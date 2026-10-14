"""Export the Claude Code session of 9 to 14 October 2026 (packs 3 to 10, the December ECL models against the directive, and the E-Banker integration pack) to PDF and Markdown."""
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
NAME = "Claude_Session_Transcript_Feeds_and_Integration_2026-10-14"
START_MARK = "result set exceeds the maximum size"
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

HEADER = f"""This is the complete record of the part of a Claude Code session (9 to 14 October 2026) that followed the audit closure of 9 October. It is saved so that another member of staff, with or without an AI tool, can follow what was done, in what order, and why. The earlier part of the same session is in Claude_Session_Transcript_Audit_Closure_2026-10-09.

It starts at the question about SQL Developer's "result set exceeds the maximum size (100 MB)" message and runs to the saving of this transcript. The work falls into five stages, in time order:
1. Pack 3 and the 9 October returns: the SPOOL note for large exports, the RUN 0 date-format check, the Mega Farm runs, method A against method B, write-offs, the feed route setting and the arrears data.
2. Packs 7 to 9: the pack 7 results review, OVERDUE_DETAILS added as RUN 8 with its goal stated, the 100 MB failure and the split into 8a, 8b and 8c, and the pack 9 results review (CV_10 is the migration worksheet; CV_14 ties the old ledger to the GL).
3. The December 2025 ECL models against the RBM DFI Directive 2018: how the directive was applied in the models compared with the system.
4. The E-Banker integration pack: the five feed routes, the landing zone and its four tables, the endpoint (route 3), the bootstrap as the test harness, the read-only account set-up with a connection test, configuration and security, refresh, duplicates, governance, storage and backup, a dated timetable, pack 10 in the register, the system's move onto MAIIC's server, and the plain-language guide.
5. The documents humanised by two editor agents, checked against snapshots, rendered with a logo, justified text and a table of contents, and saved.

What is included: every user message, every reply shown to the user, every tool call with its full input (commands, scripts and file contents), and every tool result in full. Where a long result was stored by the tooling in a side file, that file is reproduced here.

What is left out: the assistant's internal reasoning, automatic system reminders, and image outputs (page renders used for visual checks).

How to follow it: the integration documents and figures are in 3. Project Execution/E-Banker Integration; the scripts that made them are in Build files/2026-10-14 next to this transcript, with a README, and in docs/build-files in the repository.

Times are UTC. Source: the session log {os.path.basename(SESSION)}, entries {start} to {end - 1}. Events: {len(events)}."""

# ---------------------------------------------------------------- markdown
md = [f"# Claude session transcript: MAIIC feeds and E-Banker integration\n", HEADER, "\n---\n"]
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


story = [Paragraph("Claude session transcript: MAIIC feeds and E-Banker integration", st_title)]
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
    canvas.drawString(14 * mm, 8 * mm, "Claude session transcript: MAIIC feeds and E-Banker integration  ·  5 October 2026  ·  for review")
    canvas.drawRightString(A4[0] - 14 * mm, 8 * mm, f"Page {doc.page}"); canvas.restoreState()


out = os.path.join(OUTDIR, NAME + ".pdf")
doc = BaseDocTemplate(out, pagesize=A4, leftMargin=14 * mm, rightMargin=14 * mm, topMargin=13 * mm, bottomMargin=17 * mm,
                      title="Claude session transcript: MAIIC feeds and E-Banker integration (complete)", author="Dupleix Institute (Claude Code session)")
doc.addPageTemplates([PageTemplate(id="p", frames=[Frame(doc.leftMargin, doc.bottomMargin, doc.width, doc.height, id="f",
                                                         leftPadding=0, rightPadding=0, topPadding=0, bottomPadding=0)], onPage=footer)])
doc.build(story)
kinds = {}
for k, _, _ in events:
    kinds[k] = kinds.get(k, 0) + 1
print("events", kinds, "| chars", sum(len(b) for _, _, b in events))
print("first:", events[0][1], "| last:", events[-1][1], "|", events[-1][2][:80].replace("\n", " "))
print("written", out)
