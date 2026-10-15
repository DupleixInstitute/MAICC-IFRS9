p = 'C:/xampp/htdocs/MAICC-IFRS9/app/Http/Controllers/DashboardController.php'
s = open(p, encoding='utf-8').read()
n = open('C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/newmethods.php', encoding='utf-8').read()
assert '__NEWMETHODS__\n' in s
s = s.replace('__NEWMETHODS__\n', n + '\n')
if 'use Illuminate\\Support\\Facades\\DB;' not in s:
    s = s.replace('use Illuminate\\Support\\Facades\\Log;', 'use Illuminate\\Support\\Facades\\DB;\nuse Illuminate\\Support\\Facades\\Log;')
open(p, 'w', encoding='utf-8', newline='\n').write(s)
