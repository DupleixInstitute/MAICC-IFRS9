"""Render selected pages of the scanned offer letters to PNG for reading."""
import sys, os, glob
import pypdfium2 as pdfium

SRC = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\2. Documents from clients\Offer Letters\Signed Offer Letter - Received 7 october"
OUT = os.path.join(os.path.dirname(__file__), "letters")
os.makedirs(OUT, exist_ok=True)

def render(name_fragment, pages=None, scale=1.3):
    f = [p for p in glob.glob(os.path.join(SRC, "*.pdf")) if name_fragment.lower() in os.path.basename(p).lower()]
    assert len(f) == 1, (name_fragment, f)
    pdf = pdfium.PdfDocument(f[0])
    n = len(pdf)
    pages = pages or list(range(1, n + 1))
    out = []
    for p in pages:
        if p == -1: p = n
        if p < 1 or p > n: continue
        img = pdf[p - 1].render(scale=scale).to_pil()
        path = os.path.join(OUT, f"{name_fragment.replace(' ', '_')}_p{p:02}.png")
        img.save(path, optimize=True)
        out.append(path)
    print(name_fragment, "pages", n, "->", [os.path.basename(x) for x in out])

if __name__ == "__main__":
    frag = sys.argv[1]
    pages = [int(x) for x in sys.argv[2].split(",")] if len(sys.argv) > 2 else None
    scale = float(sys.argv[3]) if len(sys.argv) > 3 else 1.3
    render(frag, pages, scale)
