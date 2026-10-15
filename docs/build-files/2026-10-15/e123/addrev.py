d = 'C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/e123/'
p = 'tests/Feature/Eir/EirRevenueServiceTest.php'
s = open(p, encoding='utf-8').read().rstrip()
assert s.endswith('}')
s = s[:-1].rstrip() + '\n' + open(d + 'revtests.txt', encoding='utf-8').read()
a = "use Tests\\Feature\\Eir\\Concerns\\CreatesGovernanceSchema;"
assert a in s
s = s.replace(a, "use Tests\\Feature\\Eir\\Concerns\\CreatesFeeSchema;\n" + a, 1)
s = s.replace("    use CreatesGovernanceSchema;", "    use CreatesGovernanceSchema, CreatesFeeSchema;", 1)
open(p, 'w', encoding='utf-8', newline='\n').write(s)
print('ok')
