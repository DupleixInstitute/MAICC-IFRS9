import openpyxl, sys, warnings
warnings.filterwarnings('ignore')
p=sys.argv[1]
wv=openpyxl.load_workbook(p,data_only=True)['ECLComp']
for r in (13,21,27,41,55,56):
  print(r,[(c,wv[f'{c}{r}'].value if not isinstance(wv[f'{c}{r}'].value,float) else round(wv[f'{c}{r}'].value,4)) for c in ['E','R','AR','AS','AV','X','BA','BB','BC','CB']])
