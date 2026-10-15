p = r'c:\xampp\htdocs\MAICC-IFRS9\resources\js\Layouts\AppLayout.vue'
s = open(p, encoding='utf-8').read()
def rep(old, new):
    global s
    assert s.count(old) == 1, old[:70]
    s = s.replace(old, new)
rep('import SidebarNav from "@/Jetstream/SidebarNav.vue"\n', 'import SidebarNav from "@/Jetstream/SidebarNav.vue"\nimport { currentSection, activeTab } from "@/Jetstream/menuMatch"\n')
rep('    data() {\n', '''    computed: {
        section() {
            return currentSection(this.$page.props.menu)
        },
        activeTab() {
            return this.section ? activeTab(this.section) : null
        },
    },
    data() {
''')
open(p, 'w', encoding='utf-8', newline='').write(s)
print('ok')
