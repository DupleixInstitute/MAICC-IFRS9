from ed import edit
p='tests/Feature/Eir/StagingClassifierTest.php'
edit(p, r"""        $this->assertSame('2', (new StagingClassifier('2026-08-31'))->classify(['31_90_days' => '100']), 'the 31-90 bucket reads as 31 days, still under 61');""",
r"""        $this->assertSame('1', (new StagingClassifier('2026-08-31'))->classify(['31_90_days' => '100']), 'the 31-90 bucket reads as 31 days, under the 61 in force from June');""")
p='tests/Feature/Eir/StagingThresholdSeederTest.php'
s=open(p,encoding='utf-8',newline='').read()
import re
n=len(re.findall(r"StagingThreshold::forFacility\(([^)]*?)\)", s))
s=re.sub(r"StagingThreshold::forFacility\((null|'MEGA_FARM'|'LONG_TERM'), (\d+|\$tenor)\)", r"StagingThreshold::forFacility(\1, \2, now()->toDateString())", s)
open(p,'w',encoding='utf-8',newline='').write(s)
print(n)
