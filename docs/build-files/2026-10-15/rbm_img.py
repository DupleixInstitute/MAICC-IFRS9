import sys
from pypdf import PdfReader
r = PdfReader(sys.argv[1])
for pi in [3,5]:
    p = r.pages[pi]
    for j, im in enumerate(p.images):
        fn = "%s/p%d_%d_%s" % (sys.argv[2], pi+1, j, im.name)
        open(fn, "wb").write(im.data)
        print(fn, len(im.data))
