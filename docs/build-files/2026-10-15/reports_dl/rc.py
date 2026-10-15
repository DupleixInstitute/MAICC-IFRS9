D = r'C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/reports_dl/'
p = 'app/Http/Controllers/ReportsController.php'
raw = open(p, encoding='utf-8', newline='').read()
crlf = '\r\n' in raw
s = raw.replace('\r\n', '\n')
a = s.index("    /**\n     * Reports hub - lists all available system reports as links.")
b = s.index("    /**\n     * Loan Book Export Report")
s = s[:a] + open(D + 'rc_new.php.txt', encoding='utf-8').read() + s[b:]
s = s.replace("use App\\Services\\Reports\\DisbursementReportService;",
              "use App\\Services\\Reports\\DisbursementReportService;\nuse App\\Support\\ReportDownload;\nuse Illuminate\\Support\\Facades\\DB;")
if crlf:
    s = s.replace('\n', '\r\n')
open(p, 'w', encoding='utf-8', newline='').write(s)
print('ok')
