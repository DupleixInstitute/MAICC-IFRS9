import os, re, sys
sys.path.insert(0, os.path.dirname(__file__))
from vh import load, save, rep, rx
os.chdir(r'C:/xampp/htdocs/MAICC-IFRS9/resources/js/Pages/FLI/ExternalCalculations')

p='List.vue'; s=load(p)
a=s.index('        <template #header>'); b=s.index('                        <!-- Filters -->')
s=s[:a]+'''        <template #header>
            <div>
                <div class="mb-1 flex items-center gap-2 text-xs text-gray-500">
                    <span>IFRS 9 Model Setup</span><span>/</span><span>Scenarios &amp; Overlays</span><span>/</span><span class="font-medium text-maiic-700">Calculation History</span>
                </div>
                <h2 class="text-xl font-semibold text-gray-800">Calculation History</h2>
                <p class="mt-1 text-sm text-gray-600">Every external forward-looking calculation saved, with its scenarios and parameters</p>
            </div>
        </template>
        <template #actions>
            <Link :href="route('fli.external.index')" class="primary-btn">New calculation</Link>
        </template>

        <div class="w-full space-y-4">
            <div>
                <KpiRow :cards="[
                    { label: 'Calculations', value: parameters.length },
                    { label: 'Reporting periods', value: Number(uniqueReportingPeriods || 0) },
                    { label: 'Adjustments', value: Number(totalAdjustments || 0) },
                ]" />
            </div>
            <div>
                <div class="maiic-panel">
                    <div class="px-5 py-4">
'''+s[b:]
s=rep(s,'''<div class="mb-4 flex flex-wrap gap-4">''','''<div class="mb-4 flex flex-wrap items-end gap-3">''')
s=s.replace('<label class="block text-sm font-medium text-gray-700 mb-1">','<label class="maiic-flabel">')
s=s.replace('class="w-full border-gray-300 focus:border-maiic-500 focus:ring-maiic-500 rounded-md shadow-sm"','class="maiic-input w-full"')
s=rep(s,'<table class="min-w-full divide-y divide-gray-200">','<table class="maiic-table">')
s=rep(s,'<thead class="bg-gray-50">','<thead>')
s=s.replace('<th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">','<th>')
s=rep(s,'<tbody class="bg-white divide-y divide-gray-200">','<tbody>')
s=s.replace('<option value="">All Statistics</option>','<option value="">All statistics</option>')
s=s.replace('Get started by creating a new calculation','Use New calculation at the top right to run one.')
if "import KpiRow" not in s:
    m=re.search(r"\n(\s*)import AppLayout[^\n]*", s)
    s=s[:m.end()]+"\nimport KpiRow from '@/Components/Maiic/KpiRow.vue'\nimport { Link } from '@inertiajs/vue3'"+s[m.end():]
m=re.search(r'components:\s*\{', s)
if m:
    s=s[:m.end()]+' KpiRow, Link,'+s[m.end():]
save(p,s)
print('ok', 'components' in s)
