import io, re
p = r'C:/xampp/htdocs/MAICC-IFRS9/resources/js/Pages/Tickets/Show.vue'
s = io.open(p, encoding='utf-8').read().replace('\r\n', '\n')

# header -> layout title/description + actions top right
a = s.index('        <template #header>')
b = s.index('</template>', a) + len('</template>')
s = s[:a] + '''        <template #header>
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800"><span class="font-mono text-maiic-700">{{ ticket.reference_display }}</span> {{ ticket.title }}</h2>
                <p class="mt-0.5 text-sm text-gray-500">Logged {{ formatDate(ticket.created_at) }}{{ ticket.creator ? ' by ' + ticket.creator.name : '' }}; the activity below is the full trail</p>
            </div>
        </template>
        <template #actions>
            <button v-if="can('tickets.destroy')" type="button" class="inline-flex items-center gap-2 rounded-lg border border-red-200 bg-white px-4 py-2 text-sm font-bold text-red-600 shadow-sm transition hover:bg-red-50" @click="destroy">
                <font-awesome-icon icon="trash"/> Delete
            </button>
            <inertia-link :href="route('tickets.index')" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-bold text-gray-700 shadow-sm transition hover:bg-gray-50">Back to tickets</inertia-link>
        </template>''' + s[b:]

# remove the sidebar delete button and the jet modal
a = s.index('                <button v-if="can(\'tickets.destroy\')" @click="confirmingDeletion = true"')
b = s.index('</button>', a) + len('</button>')
s = s[:a] + s[b:]
a = s.index('        <jet-confirmation-modal')
b = s.index('</jet-confirmation-modal>', a) + len('</jet-confirmation-modal>')
s = s[:a] + s[b:]
s = s.replace("        <teleport to=\"head\"><title>{{ ticket.reference_display }}: {{ ticket.title }}</title></teleport>\n", '')

# panels and fields in the MAIIC style
s = s.replace('class="rounded-lg bg-white p-6 shadow"', 'class="maiic-panel p-6"')
s = s.replace('text-sm font-semibold uppercase tracking-wide text-gray-400', 'text-[11px] font-bold uppercase tracking-wider text-gray-500')
s = s.replace('class="mb-1 block text-xs font-medium text-gray-600"', 'class="maiic-flabel"')
s = re.sub(r'class="block w-full rounded(-lg)? border-gray-300 (shadow-sm )?(text-sm )?focus:border-maiic-500 focus:ring-maiic-500"', 'class="maiic-input"', s)
s = s.replace('<select v-model="editForm.status" class="maiic-input">', '<select v-model="editForm.status" class="maiic-select">')
s = s.replace('class="maiic-input">\n                                    <option v-for="(label, key) in categories"', 'class="maiic-select">\n                                    <option v-for="(label, key) in categories"')
s = s.replace('class="rounded border-gray-300 text-sm focus:border-maiic-500 focus:ring-maiic-500"', 'class="maiic-select w-48 py-1.5"')
s = s.replace('rounded-md bg-maiic-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-maiic-800', 'rounded-lg bg-maiic-600 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-maiic-700')
s = s.replace('placeholder="Progress note, comment or resolution detail\u2026"', 'placeholder="Progress note, comment or resolution detail"')

# script: shared confirm dialog
s = s.replace("import JetConfirmationModal from '@/Jetstream/ConfirmationModal.vue'\nimport JetDangerButton from '@/Jetstream/DangerButton.vue'\nimport JetSecondaryButton from '@/Jetstream/SecondaryButton.vue'\n", "import { confirmDialog } from '@/Components/confirmDialog'\n")
s = s.replace("components: { AppLayout, JetConfirmationModal, JetDangerButton, JetSecondaryButton },", "components: { AppLayout },")
s = s.replace("            confirmingDeletion: false,\n", '')
s = s.replace("""        destroy() {
            this.$inertia.delete(this.route('tickets.destroy', this.ticket.id))
            this.confirmingDeletion = false
        },""", """        async destroy() {
            if (!(await confirmDialog({
                title: 'Delete ticket ' + this.ticket.reference_display + '?',
                message: 'The ticket and its activity trail are removed for good.',
                confirmLabel: 'Delete ticket',
                tone: 'danger',
            }))) return
            this.$inertia.delete(this.route('tickets.destroy', this.ticket.id))
        },""")
s = s.replace("toLocaleDateString(undefined,", "toLocaleDateString('en-GB',").replace("toLocaleString(undefined,", "toLocaleString('en-GB',")
io.open(p, 'w', encoding='utf-8').write(s)
print('ok', 'confirmingDeletion' in s, 'jet-' in s)
