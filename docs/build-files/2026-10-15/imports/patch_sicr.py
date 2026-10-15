import re

MODAL = '''        <!-- Bulk import: how to, sample CSV, upload -->
        <jet-modal :show="showImportModal" @close="closeImportModal" max-width="2xl">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900">Import {{KIND}} from a CSV file</h3>
                <p class="mt-1 text-sm text-gray-500">{{INTRO}}</p>

                <div class="mt-5 grid gap-5 md:grid-cols-5">
                    <div class="md:col-span-3">
                        <FileDrop :key="importKey" :error="importError" :disabled="processing" @file="onFile"/>
                    </div>
                    <ImportHowTo class="md:col-span-2" title="How to import" :sample-url="route('import-samples.show', '{{SAMPLE}}')">
{{STEPS}}
                    </ImportHowTo>
                </div>

                <div class="mt-6 flex justify-end gap-2 border-t border-gray-200 pt-4">
                    <button type="button" class="secondary-btn" :disabled="processing" @click="closeImportModal">Cancel</button>
                    <button type="button" class="primary-btn" :disabled="processing || !csvFile" @click="uploadCsv">
                        {{ processing ? 'Importing...' : 'Start import' }}
                    </button>
                </div>
            </div>
        </jet-modal>'''

PAGES = {
    'Groups': dict(
        KIND='SICR groups', SAMPLE='sicr-groups',
        INTRO='Add many groups at once. A group that already exists, by name, is left as it is.',
        STEPS=[
            'Download the sample CSV: two columns, name and description, headings in lower case.',
            'One row per group. Rows without a name are skipped.',
            'A name that already exists is not added again and its description is not changed.',
            'Choose the file and press Start import. The message at the top says how many rows were read.',
        ]),
    'Items': dict(
        KIND='SICR alert items', SAMPLE='sicr-items',
        INTRO='Add or update many alert items at once, each under its group.',
        STEPS=[
            'Download the sample CSV: three columns, group, name and active, headings in lower case.',
            'group is the SICR group name; a group that does not exist yet is created.',
            'active is true or false (1 / 0 and yes / no also work).',
            'An item with the same group and name is updated, not duplicated. Rows without a name are skipped.',
            'Choose the file and press Start import. The message at the top says how many items were read.',
        ]),
}

for page, cfg in PAGES.items():
    p = 'C:/xampp/htdocs/MAICC-IFRS9/resources/js/Pages/StageingRules/%s.vue' % page
    s = open(p, encoding='utf-8').read()
    a = s.index('        <!-- Bulk Import Modal -->')
    b = s.index('</jet-modal>', a) + len('</jet-modal>')
    steps = '\n'.join('                        <li>%s</li>' % x for x in cfg['STEPS'])
    m = MODAL.replace('{{KIND}}', cfg['KIND']).replace('{{INTRO}}', cfg['INTRO']) \
        .replace('{{SAMPLE}}', cfg['SAMPLE']).replace('{{STEPS}}', steps)
    s = s[:a] + m + s[b:]

    # components
    old = "import JetModal from '@/Jetstream/Modal.vue'\n"
    assert s.count(old) == 1
    s = s.replace(old, old + "import FileDrop from '@/Components/Data/FileDrop.vue'\nimport ImportHowTo from '@/Components/Data/ImportHowTo.vue'\n")
    s, n = re.subn(r'(components:\s*\{)', r'\1 FileDrop, ImportHowTo,', s, count=1)
    assert n == 1
    # state
    old = 'showImportModal: false'
    assert s.count(old) == 1
    s = s.replace(old, "showImportModal: false,\n            importError: ''")
    # file handler + upload errors shown in the modal, not the console
    old = '''        onFile(e) {
            this.csvFile = e.target.files[0]
        },'''
    assert s.count(old) == 1, page
    s = s.replace(old, '''        onFile(file) {
            this.csvFile = file
            this.importError = ''
        },''')
    s = re.sub(r"                onError: \(errors\) => \{\n                    console\.error\('Import errors:', errors\)\n                    this\.\$toast\?\.error\('Failed to import CSV\. Please check the file format\.'\)\n                \}",
               "                onError: (errors) => {\n                    this.importError = Object.values(errors || {})[0] || 'The file could not be imported. Check it against the sample CSV.'\n                }", s)
    s = s.replace("                    this.$toast?.success('CSV imported successfully!')\n", "")
    old = """        openImportModal() {
            this.showImportModal = true
            this.csvFile = null"""
    assert s.count(old) == 1
    s = s.replace(old, old + "\n            this.importError = ''\n            this.importKey++")
    s = s.replace("importError: ''", "importError: '',\n            importKey: 0", 1)
    open(p, 'w', encoding='utf-8').write(s)
    print(page, 'ok')
