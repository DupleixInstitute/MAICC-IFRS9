p='C:/xampp/htdocs/MAICC-IFRS9/resources/js/Jetstream/SidebarNav.vue'
s=open(p,encoding='utf-8').read()
def rep(a,b):
    global s
    assert s.count(a)==1,a
    s=s.replace(a,b)

head_old = s[s.index('            <div v-for="(item, i) in menu" :key="item.name">'):s.index('                <DropdownMenu v-if="item.dropdown"')]
head_new = '''            <div v-for="(block, b) in blocks" :key="block.heading || b">
                <!-- block heading (config/menu.php 'heading'): an icon, the label and,
                     on every block but Overview, a chevron that folds the block -->
                <div v-if="collapsed" class="mx-4 h-px bg-white/10" :class="b === 0 ? 'mb-2' : 'my-3'"
                     role="separator" :aria-label="block.heading"/>
                <div v-else-if="block.heading" :class="b === 0 ? 'mt-1' : 'mx-3 mt-6 border-t border-white/10 pt-4'">
                    <div v-if="block.fixed" class="mb-1.5 flex items-center gap-2 py-1 text-[13px] font-semibold uppercase leading-4 tracking-wider text-amber-300/[0.85]"
                         :class="b === 0 ? 'px-5' : 'px-2'">
                        <font-awesome-icon :icon="block.icon" class="h-3.5 !w-4 flex-none" aria-hidden="true"/>
                        <span class="truncate">{{ block.heading }}</span>
                    </div>
                    <button v-else type="button" @click="toggleBlock(block.heading)"
                            class="mb-1.5 flex w-full items-center gap-2 rounded-lg py-1 text-left text-[13px] font-semibold uppercase leading-4 tracking-wider text-amber-300/[0.85] transition-colors duration-150 hover:text-amber-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-maiic-400/70"
                            :class="b === 0 ? 'px-5' : 'px-2'"
                            :aria-expanded="blockOpen(block) ? 'true' : 'false'" :aria-controls="'menu-block-' + b">
                        <font-awesome-icon :icon="block.icon" class="h-3.5 !w-4 flex-none" aria-hidden="true"/>
                        <span class="flex-1 truncate">{{ block.heading }}</span>
                        <font-awesome-icon icon="chevron-down" aria-hidden="true"
                                           class="h-3 w-3 flex-none text-amber-300/60 transition-transform duration-200"
                                           :class="blockOpen(block) ? 'rotate-180' : 'rotate-0'"/>
                    </button>
                </div>

                <div v-show="collapsed || blockOpen(block)" :id="'menu-block-' + b">
                <div v-for="(item, j) in block.items" :key="item.name">
                <!-- sub-heading inside a block ('subheading'), not clickable -->
                <div v-if="!collapsed && item.subheading && item.subheading !== (block.items[j - 1] || {}).subheading"
                     class="mb-1 px-5 text-[11px] font-semibold uppercase leading-4 tracking-wider text-white/45"
                     :class="j === 0 ? 'mt-1' : 'mt-3'">{{ item.subheading }}</div>

'''
s = s.replace(head_old, head_new)
# close the two extra wrappers after the item markup (end of the old item loop)
tail_old = '''                    <span v-if="!collapsed" class="truncate">{{ item.name }}</span>
                </div>
            </div>
        </nav>'''
tail_new = '''                    <span v-if="!collapsed" class="truncate">{{ item.name }}</span>
                </div>
                </div>
                </div>
            </div>
        </nav>'''
rep(tail_old, tail_new)

rep('''    computed: {
        menu() {
            return this.$page.props.menu || []
        },
    },''', '''    data() {
        return { openBlocks: readOpenBlocks(this.$page.props.user) }
    },
    computed: {
        menu() {
            return this.$page.props.menu || []
        },
        // the menu in its blocks (consecutive entries with the same 'heading')
        blocks() {
            const out = []
            for (const item of this.menu) {
                const last = out[out.length - 1]
                if (last && last.heading === (item.heading || null)) last.items.push(item)
                else out.push({ heading: item.heading || null, items: [item] })
            }
            return out.map(bl => ({
                ...bl,
                icon: BLOCK_ICONS[bl.heading] || 'circle',
                // Overview always shows; an untitled block cannot fold either
                fixed: !bl.heading || bl.heading === ALWAYS_OPEN,
                active: bl.items.some(it => containsCurrent(it)),
            }))
        },
    },''')
rep('''        // the first entry the user sees of a block (config/menu.php 'heading')
        startsBlock(i) {
            const item = this.menu[i]
            return !!item.heading && item.heading !== (this.menu[i - 1] || {}).heading
        },''', '''        // a block is open when it cannot fold, holds the page in view, or the
        // user left it open (remembered per user in this browser)
        blockOpen(block) {
            if (block.fixed) return true
            const state = this.openBlocks[block.heading]
            if (state === undefined) return block.active
            return state || block.active
        },
        toggleBlock(heading) {
            const block = this.blocks.find(bl => bl.heading === heading)
            this.openBlocks = { ...this.openBlocks, [heading]: !this.blockOpen(block) }
            writeOpenBlocks(this.$page.props.user, this.openBlocks)
        },''')
rep('''import { isCurrent } from '@/Jetstream/menuMatch'
''', '''import { isCurrent, containsCurrent } from '@/Jetstream/menuMatch'

// The block headings of config/menu.php ('heading'), as the Stress Testing
// suite draws them: an icon before each, Overview always open, every other
// block folds from its heading and opens by itself on the page in view.
const ALWAYS_OPEN = 'Overview'
const BLOCK_ICONS = {
    'Overview': 'th-large',
    'Data foundation': 'layer-group',
    'Financial modelling': 'project-diagram',
    'Risk & regulatory': 'balance-scale',
    'Administration & support': 'tools',
}
function blocksKey(user) {
    return 'maiic.sidebar.blocks.' + ((user && user.id) || 'guest')
}
function readOpenBlocks(user) {
    try {
        const v = JSON.parse(localStorage.getItem(blocksKey(user)) || '{}')
        return v && typeof v === 'object' ? v : {}
    } catch (e) {
        return {}
    }
}
function writeOpenBlocks(user, state) {
    try {
        localStorage.setItem(blocksKey(user), JSON.stringify(state))
    } catch (e) {
        /* storage unavailable: the blocks fold for this page only */
    }
}
''')
open(p,'w',encoding='utf-8',newline='').write(s)
print('ok')
