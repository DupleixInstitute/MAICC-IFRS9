p = r'c:\xampp\htdocs\MAICC-IFRS9\resources\js\Jetstream\SidebarNav.vue'
s = open(p, encoding='utf-8').read()
def rep(old, new):
    global s
    assert s.count(old) == 1, old[:70]
    s = s.replace(old, new)
rep("""                    <font-awesome-icon v-if="item.icon" :icon="item.icon" aria-hidden="true"
                                       class="h-4 w-4 flex-shrink-0 text-maiic-300/70 group-hover:text-maiic-200"/>
                    <span class="truncate leading-snug">{{ item.name }}</span>
                </a>""", """                    <font-awesome-icon v-if="item.icon" :icon="item.icon" aria-hidden="true"
                                       class="h-4 w-4 flex-shrink-0" :style="item.color ? { color: item.color } : null"/>
                    <span class="truncate leading-snug">{{ item.name }}</span>
                </a>""")
rep("""                    <font-awesome-icon v-if="item.icon" :icon="item.icon" aria-hidden="true"
                                       class="h-4 w-4 flex-shrink-0 transition-colors duration-150"
                                       :class="isCurrent(item) ? 'text-maiicgold-400' : 'text-maiic-300/70 group-hover:text-maiic-200'"/>""", """                    <font-awesome-icon v-if="item.icon" :icon="item.icon" aria-hidden="true"
                                       class="h-4 w-4 flex-shrink-0 transition-colors duration-150"
                                       :class="item.color ? '' : (isCurrent(item) ? 'text-maiicgold-400' : 'text-maiic-300/70 group-hover:text-maiic-200')"
                                       :style="item.color ? { color: item.color } : null"/>""")
rep("""import DropdownMenu from '@/Jetstream/DropdownMenu.vue'
""", """import DropdownMenu from '@/Jetstream/DropdownMenu.vue'
import { isCurrent } from '@/Jetstream/menuMatch'
""")
rep("""        isCurrent(item) {
            try {
                return route().current(item.route) ||
                    (item.route_check && route().current(item.route_check))
            } catch (e) {
                return false
            }
        },""", """        isCurrent(item) {
            return isCurrent(item)
        },""")
s = s.replace("text-maiic-100/60 hover:bg-white/[0.05]", "text-maiic-100/70 hover:bg-white/[0.05]")
open(p, 'w', encoding='utf-8', newline='').write(s)
print('ok')
