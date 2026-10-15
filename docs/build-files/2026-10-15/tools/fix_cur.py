R = 'C:/xampp/htdocs/MAICC-IFRS9/resources/js/Pages/'
p = R + 'Currencies/Index.vue'
s = open(p, encoding='utf-8').read()
bad = "with each one\\'s symbol, rate and decimals"
assert bad in s
s = s.replace(bad, 'with the symbol, rate and decimals of each')
open(p, 'w', encoding='utf-8').write(s)

# the payment tracking page keeps its ? help button, now in the actions
p = R + 'RepaymentReports/Index.vue'
s = open(p, encoding='utf-8').read()
a = '<template #actions>\n'
assert a in s
s = s.replace(a, a + '            <HelpManual />\n', 1)
open(p, 'w', encoding='utf-8').write(s)
print('ok')
