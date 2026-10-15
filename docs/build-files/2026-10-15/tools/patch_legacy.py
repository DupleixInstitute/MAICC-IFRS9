import re
R = 'C:/xampp/htdocs/MAICC-IFRS9/resources/js/'

# 1. Jetstream button: the system's primary button, sentence case
p = R + 'Jetstream/Button.vue'
s = open(p, encoding='utf-8').read()
old = 'class="inline-flex items-center px-4 py-2 bg-maiic-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-maiic-700 active:bg-maiic-800 focus:outline-none focus:border-maiic-800 focus:shadow-outline-gray transition ease-in-out duration-150 disabled:bg-slate-50 disabled:text-slate-500 disabled:border-slate-200 disabled:shadow-none"'
assert old in s
s = s.replace(old, 'class="primary-btn"')
open(p, 'w', encoding='utf-8').write(s)

PAGES = {
    'TransitionMatrix/Create.vue': ('Create monthly transition matrix', 'Choose the profile, the window and the level; the next step enters or calculates the matrix', '/transition-matrix'),
    'TransitionMatrix/CummulativeCreate.vue': ('Create cumulative transition matrix', 'Compound closed monthly matrices into the PD term structure for a window', '/transition-matrix-cummulative'),
    'LossGivenDefault/Create.vue': ('Calculate monthly LGD', 'Loss given default from cures and recoveries on Stage 3 loans for one window', '/loss-given-default/list'),
    'LossGivenDefault/CummulativeCreate.vue': ('Calculate cumulative LGD', 'Combine closed monthly LGD runs into the LGD used by the ECL', '/loss-given-default/cummulative'),
    'TransitionProfileDefinitions/Create.vue': ('Create transition profile', 'Which table and column hold the grade at the start and the end of each period, and how transitions are weighted', None),
}

for f, (title, desc, back) in PAGES.items():
    p = R + 'Pages/' + f
    s = open(p, encoding='utf-8').read()
    # header block -> title and description, Back as a secondary action
    m = re.search(r'<app-layout>\s*<template #header>.*?</template>', s, re.S)
    assert m, f
    back_href = f'"{back}"' if back else "route('transition-profiles.index')"
    bind = ':href' if not back else 'href'
    head = (f'<app-layout title="{title}" description="{desc}">\n'
            f'        <template #actions>\n'
            f'            <Link {bind}={back_href} class="secondary-btn">Back</Link>\n'
            f'        </template>')
    if not back:
        head = head.replace('<Link :href="route(\'transition-profiles.index\')"', '<Link :href="route(\'transition-profiles.index\')"')
    s = s[:m.start()] + head + s[m.end():]
    s = s.replace('<div class="py-12">', '<div>', 1)
    s = s.replace('class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6"', 'class="maiic-panel p-6"', 1)
    # the in-form Back link moves to the header
    s = re.sub(r'\s*<Link href="[^"]*" class="inline-flex items-center px-4 py-2 bg-maiic-600 hover:bg-maiic-400 text-black-700 rounded-md">\s*Back\s*</Link>', '', s)
    # stray inline styling on the jet buttons
    s = s.replace('<jet-button @click="toggleModal" class=" items-center px-4 py-2 bg-black-600 hover:bg-gray-400 text-black-700 rounded-md">', '<jet-button @click="toggleModal">')
    s = s.replace('Cummulative', 'Cumulative') if False else s
    open(p, 'w', encoding='utf-8').write(s)
    print(f, 'Link' in s and ("import { Link" in s or "Link }" in s or "Link," in s or "Link " in s))
