p='C:/xampp/htdocs/MAICC-IFRS9/resources/js/Layouts/AppLayout.vue'
s=open(p,encoding='utf-8').read()
def rep(a,b):
    global s
    assert s.count(a)==1,a
    s=s.replace(a,b)
rep('''        <!-- Static sidebar for desktop -->
        <div class="hidden md:fixed md:inset-y-0 md:z-20 md:flex md:w-72 md:flex-col"
             style="background: linear-gradient(172deg, #0b2b1a 0%, #082013 48%, #051509 100%); border-right: 1px solid rgba(212,160,23,0.14);">
            <SidebarNav/>
        </div>
        <div class="flex flex-1 flex-col md:pl-72">''','''        <!-- Static sidebar for desktop: full width, or the 72px icon rail when
             collapsed (remembered per browser); the page widens with it. -->
        <div class="hidden md:fixed md:inset-y-0 md:z-20 md:flex md:flex-col md:transition-[width] md:duration-200"
             :class="sidebarCollapsed ? 'md:w-[72px]' : 'md:w-72'"
             style="background: linear-gradient(172deg, #0b2b1a 0%, #082013 48%, #051509 100%); border-right: 1px solid rgba(212,160,23,0.14);">
            <SidebarNav :collapsed="sidebarCollapsed" collapsible @toggle="setSidebarCollapsed(!sidebarCollapsed)" @expand="setSidebarCollapsed(false)"/>
        </div>
        <div class="flex min-w-0 flex-1 flex-col md:transition-[padding] md:duration-200" :class="sidebarCollapsed ? 'md:pl-[72px]' : 'md:pl-72'">''')
rep('''            sidebarOpen: false,
            showingNavigationDropdown: false,''','''            sidebarOpen: false,
            sidebarCollapsed: readSidebarCollapsed(),
            showingNavigationDropdown: false,''')
rep('''        isActiveTab(tab) {
            return !!this.activeTab && this.activeTab === tab
        },
''','''        isActiveTab(tab) {
            return !!this.activeTab && this.activeTab === tab
        },
        // the desktop sidebar's icon rail, remembered in this browser
        setSidebarCollapsed(value) {
            this.sidebarCollapsed = !!value
            try {
                localStorage.setItem(SIDEBAR_KEY, this.sidebarCollapsed ? '1' : '0')
            } catch (e) {
                /* storage unavailable: the choice holds for this page only */
            }
        },
''')
rep('''import { router } from '@inertiajs/vue3'

export default {''','''import { router } from '@inertiajs/vue3'

// The desktop sidebar is full width or a 72px icon rail; the choice is kept
// per browser (spec v4 section 11, decision D24).
const SIDEBAR_KEY = 'maiic.sidebar.collapsed'
function readSidebarCollapsed() {
    try {
        return localStorage.getItem(SIDEBAR_KEY) === '1'
    } catch (e) {
        return false
    }
}

export default {''')
open(p,'w',encoding='utf-8',newline='').write(s)
