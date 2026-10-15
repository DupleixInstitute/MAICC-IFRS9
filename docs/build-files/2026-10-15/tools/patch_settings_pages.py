import re
R = 'C:/xampp/htdocs/MAICC-IFRS9/resources/js/Pages/'

def legacy_table(s):
    s = s.replace('<table class="min-w-full divide-y divide-gray-200">', '<table class="maiic-table">')
    s = re.sub(r'<thead class="bg-gray-(?:50|100|200)">', '<thead>', s)
    def th(m):
        cls = m.group(1)
        if 'text-right' in cls:
            return '<th class="num">'
        if 'text-center' in cls:
            return '<th class="text-center">'
        return '<th>'
    s = re.sub(r'<th class="([^"]*uppercase[^"]*)">', th, s)
    s = s.replace('<tbody class="bg-white divide-y divide-gray-200">', '<tbody>')
    return s

def header(s, title, desc, actions=''):
    m = re.search(r'<app-layout>\s*<template #header>.*?</template>', s, re.S)
    assert m
    new = f'<app-layout title="{title}" description="{desc}">'
    if actions:
        new += '\n        <template #actions>\n' + actions + '\n        </template>'
    return s[:m.start()] + new + s[m.end():]

# Payment tracking (LGD calculations)
p = R + 'RepaymentReports/Index.vue'
s = open(p, encoding='utf-8').read()
s = header(s, 'LGD payment tracking', 'Runs that follow Stage 3 loans and their payments month by month: the cures and recoveries the loss given default is built from', """            <button type="button" class="secondary-btn" @click="refreshData"><i class="fas fa-sync-alt"></i> Refresh</button>
            <button type="button" class="secondary-btn" @click="showFilters = !showFilters"><i class="fas fa-filter"></i> Filters</button>
            <Link :href="route('lgd-calculations.create')" class="primary-btn"><i class="fa fa-calculator"></i> New calculation</Link>""")
s = legacy_table(s)
s = s.replace('<button @click="clearFilters" class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">Reset</button>', '<button type="button" @click="clearFilters" class="secondary-btn">Reset</button>')
s = s.replace('<button @click="applyFilters" class="px-4 py-2 bg-maiic-600 text-white rounded hover:bg-maiic-700">Apply</button>', '<button type="button" @click="applyFilters" class="primary-btn">Apply</button>')
open(p, 'w', encoding='utf-8').write(s)

# Page help text (manuals)
p = R + 'Manual/ManualList.vue'
s = open(p, encoding='utf-8').read()
s = header(s, 'Page help text', 'Short help shown by the ? button on a particular screen. The User and Administrator manuals are under System Documentation; a screen without its own help text opens the manual article for it.', """            <Link :href="route('manuals.create')" class="primary-btn"><i class="fas fa-plus"></i> Add page help</Link>""")
s = legacy_table(s)
s = s.replace('No manuals found.', 'No screen has its own help text yet: the ? button on every screen opens the matching manual article.')
s = s.replace('<div class="overflow-x-auto mt-6">\n      <div class="bg-white shadow-md rounded-lg">', '<div class="maiic-panel">\n      <div class="maiic-table-wrap">')
open(p, 'w', encoding='utf-8').write(s)

# Licence
p = R + 'License/Index.vue'
s = open(p, encoding='utf-8').read()
s = header(s, 'Licence', 'The licence key for this installation and what it covers')
s = s.replace('<div class=" mx-auto">\n            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-4">\n                <div class="grid grid-cols-2 gap-2">',
              '<div>\n            <div class="maiic-panel p-5">\n                <div class="grid grid-cols-1 gap-6" :class="{ \'lg:grid-cols-2\': decodedPurchaseCode }">')
open(p, 'w', encoding='utf-8').write(s)

# Currencies
p = R + 'Currencies/Index.vue'
s = open(p, encoding='utf-8').read()
s = header(s, 'Currencies', 'The currency list the reporting currency is chosen from, with each one\\\'s symbol, rate and decimals')
open(p, 'w', encoding='utf-8').write(s)
print('ok')
