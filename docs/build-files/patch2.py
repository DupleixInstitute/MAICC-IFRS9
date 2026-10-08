p = "maiic_report.py"; s = open(p, encoding="utf-8").read()
def rep(old, new, n=1):
    global s
    assert s.count(old) == n, (old[:50], s.count(old)); s = s.replace(old, new)
rep("PageBreak, KeepTogether, Image, ListFlowable, ListItem)", "PageBreak, KeepTogether, Image, ListFlowable, ListItem, CondPageBreak)")
rep('def H2(t): story.append(Paragraph(t, S["h2"]))',
    'def H2(t): story.append(Paragraph(t, S["h2"]))\n\n\ndef H2T(t):\n    """Heading that sits directly above a long table: break first if little room is left."""\n    story.append(CondPageBreak(62 * mm)); story.append(Paragraph(t, S["h2t"]))')
rep('    "p": ParagraphStyle("p",', '    "h2t": ParagraphStyle("h2t", fontName="Seg-B", fontSize=11.5, leading=15, textColor=ORANGE, spaceBefore=10, spaceAfter=4),\n    "p": ParagraphStyle("p",')
rep('H2("Where it falls short")', 'H2T("Where it falls short")', 3)
rep('H2("Tier 1: answers the open questions on Extracts A, B and C")', 'H2T("Tier 1: answers the open questions on Extracts A, B and C")')
rep('H1("8. What the scripts settle, and what is still open")\n', 'H1("8. What the scripts settle, and what is still open")\nP("The questions below are the ones we raised in September, with what the scripts now tell us about each and the query "\n  "that would close it. Run numbers refer to section 10.")\n')
rep('H1("7. The three findings, and what to do about each")\n', 'H1("7. The three findings, and what to do about each")\nP("These are the three points from the summary, with the action each one calls for.")\n')
open(p, "w", encoding="utf-8").write(s); print("ok")
