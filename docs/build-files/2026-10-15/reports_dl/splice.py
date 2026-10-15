import sys
p='app/Http/Controllers/Reports/Ifrs9ReportsController.php'
s=open(p,encoding='utf-8').read()
a=s.index("    // key => [title, subtitle, category]. Full catalogue")
b=s.index("    /* ===================================================================== */\n    /*  Core ECL")
new=open(sys.argv[1],encoding='utf-8').read()
s=s[:a]+new+s[b:]
open(p,'w',encoding='utf-8').write(s)
