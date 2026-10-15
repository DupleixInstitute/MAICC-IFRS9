"""Export the Claude Code session of 9 to 15 October 2026 (the UI rework, packs 8 to 10, the go-live build,
the production database and Finance's fees) to PDF and Markdown.

    python build_transcript_golive.py <output folder>

Same layout as build_transcript_integration.py (2026-10-14), with two differences for a log seven times
larger (114 MB): credentials are redacted (redact_session_log.py), and a tool input or result longer than
the cap keeps its beginning and end with the cut marked. The whole log, redacted, is kept beside the
transcript on OneDrive.
"""
import json, re, os, html, sys
from reportlab.lib.pagesizes import A4
from reportlab.lib.units import mm
from reportlab.lib import colors
from reportlab.lib.styles import ParagraphStyle
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.platypus import BaseDocTemplate, PageTemplate, Frame, Paragraph, Spacer, Preformatted

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from redact_session_log import redact  # noqa: E402

SESSION = os.path.join(os.path.expanduser("~"), ".claude", "projects", "c--xampp-htdocs-Stress-Testing-App", "c9a0f915-2064-4f18-9b98-77857ef71421.jsonl")
OUTDIR = sys.argv[1] if len(sys.argv) > 1 else "."
NAME = "Claude_Session_Transcript_UI_GoLive_and_Fees_2026-10-15"
TITLE = "Claude session transcript: MAIIC UI, go-live, production database and fees"
START_MARK = "pull all maic changes"
CAP_RESULT, CAP_INPUT = 12_000, 24_000
found = {}

rows = [json.loads(l) for l in open(SESSION, encoding="utf-8") if l.strip()]


def user_text(r):
    c = r.get("message", {}).get("content")
    if isinstance(c, str):
        return c
    return "\n".join(b.get("text", "") for b in c if isinstance(b, dict) and b.get("type") == "text")


start = next(i for i, r in enumerate(rows) if r.get("type") == "user" and START_MARK in user_text(r).lower())
end = len(rows)


def clean_user(t):
    for tag in ("system-reminder", "ide_opened_file", "ide_selection"):
        t = re.sub(rf"<{tag}>.*?</{tag}>", "", t, flags=re.S)
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


def cap(t, n):
    if len(t) <= n:
        return t
    head, tail = int(n * 0.75), int(n * 0.2)
    return t[:head] + f"\n\n[... {len(t) - head - tail:,} characters cut here; the full text is in the session log kept with this transcript ...]\n\n" + t[-tail:]


class _C(dict):
    def __missing__(self, k):
        return 0


counter = _C()


def safe(t):
    from collections import Counter
    c = Counter()
    t = redact(t, c)
    for k, v in c.items():
        counter[k] += v
    return t


# ---------------------------------------------------------------- collect events
events = []
tool_names = {}
for r in rows[start:end]:
    typ = r.get("type")
    ts = (r.get("timestamp") or "")[:19].replace("T", " ")
    att = r.get("attachment") or {}
    if typ == "attachment" and att.get("type") == "queued_command" and (att.get("origin") or {}).get("kind") == "human":
        # a message the user sent while a turn was running, delivered into that turn
        prompt = att.get("prompt", "")
        if isinstance(prompt, list):  # text with pasted screenshots
            prompt = "\n".join(b.get("text", "") if b.get("type") == "text" else "[screenshot pasted by the user: not reproduced in this transcript]"
                               for b in prompt if isinstance(b, dict))
        events.append(("user", f"USER, sent while Claude was working  ({ts} UTC)", safe(clean_user(str(prompt)))))
        continue
    if typ not in ("user", "assistant") or r.get("isMeta"):
        continue
    if r.get("isCompactSummary"):
        events.append(("note", f"Context summary written when the conversation was compacted  ({ts} UTC)", safe(cap(user_text(r), CAP_RESULT))))
        continue
    c = r.get("message", {}).get("content")
    blocks = [{"type": "text", "text": c}] if isinstance(c, str) else (c or [])
    for b in blocks:
        bt = b.get("type")
        if typ == "user" and bt == "text":
            raw = b.get("text", "")
            if "<task-notification>" in raw or "SYSTEM NOTIFICATION" in raw:
                events.append(("note", f"System notification  ({ts} UTC)", safe(re.sub(r"<[^>]+>", " ", clean_user(raw)).strip())))
                continue
            t = clean_user(raw)
            if t:
                events.append(("user", f"USER  ({ts} UTC)", safe(t)))
        elif typ == "user" and bt == "tool_result":
            name = tool_names.get(b.get("tool_use_id"), "tool")
            body = re.sub(r"<system-reminder>.*?</system-reminder>", "", result_text(b.get("content")), flags=re.S).rstrip()
            events.append(("result", f"Result of {name}" + ("  [error]" if b.get("is_error") else ""), safe(cap(body, CAP_RESULT)) or "(no output)"))
        elif typ == "assistant" and bt == "text":
            if b.get("text", "").strip():
                events.append(("assistant", f"CLAUDE  ({ts} UTC)", safe(b["text"].strip())))
        elif typ == "assistant" and bt == "tool_use":
            tool_names[b.get("id")] = b.get("name")
            inp = b.get("input", {})
            parts = []
            for k in ("description", "file_path", "path", "pattern", "skill", "offset", "limit"):
                if k in inp:
                    parts.append(f"{k}: {inp[k]}")
            for k in ("command", "content", "old_string", "new_string", "prompt", "script"):
                if k in inp:
                    parts.append(f"--- {k} ---\n{inp[k]}")
            other = {k: v for k, v in inp.items() if k not in ("description", "file_path", "path", "pattern", "skill", "offset", "limit", "command", "content", "old_string", "new_string", "prompt", "script")}
            if other:
                parts.append(json.dumps(other, indent=1)[:4000])
            events.append(("tool", f"Tool call: {b.get('name')}", safe(cap("\n".join(parts), CAP_INPUT))))

HEADER = f"""This is the record of a Claude Code session that ran from 9 to 15 October 2026 on the MAIIC IFRS 9 system (repository MAICC-IFRS9). It is saved so that another member of staff, with or without an AI tool, can follow what was done, in what order, and why. The conversation was compacted several times; each compaction summary is reproduced where it was written.

It starts at "pull all maic changes and put the db and run any necessary commands" and runs to the saving of this transcript. The work falls into six stages, in time order:
1. 9 October: the changes pulled and the database set up; the user interface reworked (navigation, coloured tabs, the dashboard, the macro statistics, scenario, overlay and forward-looking pages); the pack 3 data.
2. 13 October: the dashboard's ECL build-up and its charts with their own period selectors; the maintenance page and its specification; the forward-looking screens improved; the write-off routes; S&P internal grades; the sidebar, breadcrumbs and the sample files and checks on every import; pack 8 reviewed and pack 9 drafted.
3. 13 to 14 October (overnight): the whole system audited end to end against the specifications and the packs; the results for every period compared with MAIIC's December 2025 model and written up in Downloads; the bootstrap proved reproducible; the documentation audited; everything pushed to master.
4. 14 October: packs 9 and 10 received and landed; pack 10 drafted and its results reviewed; the collateral haircuts from the December 2025 ECL model.
5. 14 to 15 October: the production database backed up to OneDrive and replaced by a fresh bootstrap (98 checks, 0 FAIL), audited against the backup; two install defects fixed (settings, S&P grades).
6. 15 October: what is outstanding; the 324 fee lines in a workbook for Finance; Finance's 2024 and 2025 fee workbooks reconciled to the GL; the fee workbook gate (FeeWorkbookTieOut) built, with the upload files for Finance; Chikwendeni's 800 as a question for Tamanda.

What is included: every user message, every reply shown to the user, every tool call with its input (commands, scripts and file contents) and every tool result. A tool input longer than {CAP_INPUT:,} characters or a result longer than {CAP_RESULT:,} keeps its beginning and end, with the cut marked; the whole log is kept, redacted, beside this transcript on OneDrive.

What is left out: the assistant's internal reasoning, automatic system reminders, image outputs, and credentials (two Laravel application keys found by redact_session_log.py are replaced by [REDACTED ...]).

How to follow it: the scripts the session ran are in docs/build-files/2026-10-15 in the repository, with a README, and in OneDrive under Query Requests to MAIIC/Build files/2026-10-15. The commits are on master (the last of the session is c343409); another session committed to the same branch over the same days, so the commit messages, not the dates, say which work is whose.

Times are UTC. Source: the session log {os.path.basename(SESSION)}, entries {start} to {end - 1}. Events: {len(events)}."""

# ---------------------------------------------------------------- markdown
md = [f"# {TITLE}\n", HEADER, "\n---\n"]
for kind, title, body in events:
    if kind in ("user", "assistant"):
        md.append(f"\n## {title}\n\n{body}\n")
    else:
        md.append(f"\n**{title}**\n\n````\n{body}\n````\n")
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
    buf = []

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


story = [Paragraph(TITLE, st_title)]
for para in HEADER.split("\n\n"):
    for ln in para.split("\n"):
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
    canvas.drawString(14 * mm, 8 * mm, TITLE + "  ·  9 to 15 October 2026  ·  for review")
    canvas.drawRightString(A4[0] - 14 * mm, 8 * mm, f"Page {doc.page}"); canvas.restoreState()


out = os.path.join(OUTDIR, NAME + ".pdf")
doc = BaseDocTemplate(out, pagesize=A4, leftMargin=14 * mm, rightMargin=14 * mm, topMargin=13 * mm, bottomMargin=17 * mm,
                      title=TITLE + " (complete)", author="Dupleix Institute (Claude Code session)")
doc.addPageTemplates([PageTemplate(id="p", frames=[Frame(doc.leftMargin, doc.bottomMargin, doc.width, doc.height, id="f",
                                                         leftPadding=0, rightPadding=0, topPadding=0, bottomPadding=0)], onPage=footer)])
doc.build(story)
kinds = {}
for k, _, _ in events:
    kinds[k] = kinds.get(k, 0) + 1
print("events", kinds, "| chars", sum(len(b) for _, _, b in events), "| redacted", dict(counter))
print("first:", events[0][1], "| last:", events[-1][1])
print("written", out)
