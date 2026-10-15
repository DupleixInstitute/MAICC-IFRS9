import os
os.chdir(r'C:/xampp/htdocs/MAICC-IFRS9/resources/js/Pages/Eir')

def rep(s, a, b):
    if a not in s:
        raise SystemExit('NOT FOUND: ' + a[:120])
    return s.replace(a, b)

p='Governance.vue'; s=open(p,encoding='utf-8').read()
s=rep(s,"""        .replace(/\\s*\\((?:spec v\\d+ section [\\d.]+[^)]*|O\\d+)\\)/g, '')""","""        .replace(/\\s*\\((?:spec v\\d+ section [\\d.]+[^)]*|O\\d+|decision D\\d+)\\)/g, '')
        .replace(/\\s*Agreed as decision D\\d+\\./g, '')""")
for a,b in [("label: 'Interest and rates'","label: 'Interest & rates'"),("label: 'Cash flows and schedules'","label: 'Cash flows'"),("label: 'Accounting and controls'","label: 'Accounting & controls'"),("label: 'Forward-looking and scenarios'","label: 'Forward-looking'")]:
    s=rep(s,a,b)
s=rep(s,"{ label: 'In force', value: this.settings.length - noValue, sub: `with an approved value on ${this.asOf}`, accent: '#15803d' },","{ label: 'In force', value: this.settings.length - noValue, sub: 'approved value today', accent: '#15803d' },")
s=rep(s,"{ label: 'Approved, not yet effective', value: upcoming, sub: 'apply from a later date', accent: '#0284c7' },","{ label: 'Upcoming', value: upcoming, sub: 'approved, from a later date', accent: '#0284c7' },")
s=rep(s,"{ label: 'No approved value', value: noValue, sub: 'calculations needing them stop',","{ label: 'No approved value', value: noValue, sub: 'calculations would stop',")
open(p,'w',encoding='utf-8').write(s)
print('ok')
