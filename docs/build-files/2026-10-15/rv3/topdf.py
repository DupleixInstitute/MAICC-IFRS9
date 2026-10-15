"""docx to pdf through the Word (or WPS) COM server. usage: python topdf.py in.docx out.pdf"""
import sys, os, win32com.client
src, out = os.path.abspath(sys.argv[1]), os.path.abspath(sys.argv[2])
w = win32com.client.DispatchEx('Word.Application')
w.Visible = False
try:
    w.DisplayAlerts = 0
except Exception:
    pass
d = w.Documents.Open(src, ReadOnly=True)
try:
    d.Fields.Update()
except Exception:
    pass
d.ExportAsFixedFormat(out, 17)
d.Close(False)
try:
    w.Quit()
except Exception:
    pass
print('pdf', out)
