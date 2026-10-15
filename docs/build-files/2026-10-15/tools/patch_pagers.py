import re
R = 'C:/xampp/htdocs/MAICC-IFRS9/resources/js/Pages/'

p = R + 'AuditTrail/Index.vue'
s = open(p, encoding='utf-8').read()
a = s.index('            <!-- Numbered pager in the Pagination.vue style')
b = s.index('            </div>\n        </div>\n    </app-layout>')
b = b + len('            </div>\n')
s = s[:a] + """            <!-- The standard pager; this list merges two logs, so it pages on the server -->
            <div v-if="pagination.last_page > 1" class="border-t border-gray-100 px-4 py-3">
                <ClientPager :model-value="pagination.current_page" :total="pagination.total" :per-page="pagination.per_page" @update:model-value="go"/>
            </div>
""" + s[b:]
s = s.replace("import pickBy from 'lodash/pickBy'", "import pickBy from 'lodash/pickBy'\nimport ClientPager from '@/Components/ClientPager.vue'", 1)
s = s.replace('    components: { AppLayout },', '    components: { AppLayout, ClientPager },', 1)
# pageWindow is no longer used
s = re.sub(r"        // Page numbers around the current page, with the first and last\.\n        pageWindow\(\) \{.*?\n        \},\n", '', s, flags=re.S)
open(p, 'w', encoding='utf-8').write(s)

p = R + 'LossGivenDefault/Index.vue'
s = open(p, encoding='utf-8').read()
a = '<RowPager v-model="page" :total="lossGivenDefaults.length" class="border-t border-gray-100" />'
assert a in s
s = s.replace(a, '<div v-if="lossGivenDefaults.length > 15" class="border-t border-gray-100 px-4 py-3"><ClientPager v-model="page" :total="lossGivenDefaults.length" /></div>')
s = s.replace("import RowPager from '@/Components/Maiic/RowPager.vue';", "import ClientPager from '@/Components/ClientPager.vue';")
s = s.replace('        RowPager,', '        ClientPager,')
open(p, 'w', encoding='utf-8').write(s)
print('ok', 'pageWindow' in open(R + 'AuditTrail/Index.vue', encoding='utf-8').read())
