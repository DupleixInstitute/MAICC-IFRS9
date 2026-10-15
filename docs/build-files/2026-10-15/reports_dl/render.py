import fitz, sys
src, out = sys.argv[1], sys.argv[2]
d = fitz.open(src)
pg = int(sys.argv[3]) if len(sys.argv) > 3 else 0
pix = d[pg].get_pixmap(dpi=90)
pix.save(out)
print(src, 'pages', d.page_count)
