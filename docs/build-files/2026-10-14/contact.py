import pypdfium2 as pdfium
from PIL import Image
D = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\3. Project Execution\E-Banker Integration"
for name, tag in [("Data access request - E-Banker read-only access (route 4) - 14 Oct 2026.pdf", "tech"), ("Plain-language guide - connecting the IFRS 9 system to E-Banker - 14 Oct 2026.pdf", "plain")]:
    doc = pdfium.PdfDocument(f"{D}\\{name}")
    pages = [doc[i].render(scale=0.62).to_pil() for i in range(len(doc))]
    w, h = pages[0].size
    per = 6
    for k in range(0, len(pages), per):
        sheet = Image.new("RGB", (w * 3 + 40, h * 2 + 30), "white")
        for j, im in enumerate(pages[k:k + per]):
            sheet.paste(im, (10 + (j % 3) * (w + 10), 10 + (j // 3) * (h + 10)))
        sheet.save(f"{tag}_sheet_{k // per + 1}.png")
    print(tag, len(pages), "pages ->", (len(pages) + per - 1) // per, "sheets")
