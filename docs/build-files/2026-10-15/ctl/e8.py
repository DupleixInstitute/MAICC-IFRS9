from ed import edit
p='resources/js/Pages/Settings/Index.vue'
edit(p, r"""                                            <th>Effective from</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="t in staging.thresholds" :key="t.id">
                                            <td class="font-semibold text-gray-900">{{ classLabel(t.facility_class) }}</td>
                                            <td class="num">{{ t.min_tenor_months }}</td>
                                            <td class="num">{{ t.stage2_dpd }}</td>
                                            <td class="num">{{ t.stage3_dpd }}</td>
                                            <td class="whitespace-nowrap">{{ t.in_force ? formatDate(t.effective_from) : 'Not set' }}</td>
                                            <td>
                                                <span v-if="t.in_force" class="maiic-badge maiic-badge-green">In force</span>
                                                <span v-else class="maiic-badge maiic-badge-gold">Proposal, not in force</span>
                                            </td>
                                        </tr>
                                        <tr v-if="!staging.thresholds.length">
                                            <td colspan="6" class="maiic-empty">No thresholds are loaded. Staging then uses Stage 2 from 31 days and Stage 3 from 181 days.</td>
                                        </tr>""", r"""                                            <th>Effective from</th>
                                            <th>Status</th>
                                            <th>Proposed by</th>
                                            <th>Approved by</th>
                                            <th v-if="can('eir.govern')"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="t in staging.thresholds" :key="t.id">
                                            <td class="font-semibold text-gray-900">{{ classLabel(t.facility_class) }}</td>
                                            <td class="num">{{ t.min_tenor_months }}</td>
                                            <td class="num">{{ t.stage2_dpd }}</td>
                                            <td class="num">{{ t.stage3_dpd }}</td>
                                            <td class="whitespace-nowrap">{{ formatDate(t.effective_from) }}</td>
                                            <td>
                                                <span v-if="t.status === 'PROPOSED'" class="maiic-badge maiic-badge-gold" :title="t.reason || ''">Proposed, awaiting a second person</span>
                                                <span v-else-if="t.status === 'REJECTED'" class="maiic-badge maiic-badge-gray" :title="t.reason || ''">Rejected</span>
                                                <span v-else-if="t.in_force" class="maiic-badge maiic-badge-green">In force</span>
                                                <span v-else class="maiic-badge maiic-badge-gold">Approved, from its date</span>
                                            </td>
                                            <td>{{ t.proposer || (t.status === 'APPROVED' ? 'Loaded with the system' : '') }}</td>
                                            <td>{{ t.approver || '' }}</td>
                                            <td v-if="can('eir.govern')" class="whitespace-nowrap">
                                                <template v-if="t.status === 'PROPOSED'">
                                                    <button v-if="t.proposed_by !== currentUserId" type="button" class="text-xs font-semibold text-maiic-700 hover:underline" @click="approveThreshold(t)">Approve</button>
                                                    <span v-else class="text-xs text-gray-500" title="The person who proposed a change cannot approve it">Yours: a second person approves</span>
                                                    <button type="button" class="ml-3 text-xs font-semibold text-red-700 hover:underline" @click="rejectThreshold(t)">Reject</button>
                                                </template>
                                            </td>
                                        </tr>
                                        <tr v-if="!staging.thresholds.length">
                                            <td :colspan="can('eir.govern') ? 9 : 8" class="maiic-empty">No thresholds are loaded. Staging stops and names the missing rule until one is approved.</td>
                                        </tr>""")
edit(p, r"""                        <p class="mt-2 text-xs text-gray-500">A loan uses the most specific row it qualifies for: its own facility class before "All facilities", then the longest tenor it meets. These rows are loaded with the system and are not edited on screen.</p>
                    </div>""", r"""                        <p class="mt-2 text-xs text-gray-500">A loan uses the most specific approved row it qualifies for: its own facility class before "All facilities", then the longest tenor it meets. A change is proposed by one person and approved by another before staging reads it; it applies forward only, from its effective date.</p>
                        <form v-if="can('eir.govern')" class="maiic-panel mt-3 grid grid-cols-1 gap-3 p-4 sm:grid-cols-3" @submit.prevent="proposeThreshold">
                            <h5 class="sm:col-span-3 text-sm font-semibold text-gray-900">Propose a threshold change</h5>
                            <label class="text-xs text-gray-600">Facility class
                                <input v-model="thresholdForm.facility_class" type="text" class="mt-1 block w-full rounded-md border-gray-300 text-sm" placeholder="DEFAULT, LONG_TERM, MEGA_FARM"/>
                            </label>
                            <label class="text-xs text-gray-600">Applies from tenor (months)
                                <input v-model.number="thresholdForm.min_tenor_months" type="number" min="0" class="mt-1 block w-full rounded-md border-gray-300 text-sm"/>
                            </label>
                            <label class="text-xs text-gray-600">Effective from
                                <input v-model="thresholdForm.effective_from" type="date" class="mt-1 block w-full rounded-md border-gray-300 text-sm"/>
                            </label>
                            <label class="text-xs text-gray-600">Stage 2 from (days)
                                <input v-model.number="thresholdForm.stage2_dpd" type="number" min="1" class="mt-1 block w-full rounded-md border-gray-300 text-sm"/>
                            </label>
                            <label class="text-xs text-gray-600">Stage 3 from (days)
                                <input v-model.number="thresholdForm.stage3_dpd" type="number" min="2" class="mt-1 block w-full rounded-md border-gray-300 text-sm"/>
                            </label>
                            <label class="text-xs text-gray-600">Reason
                                <input v-model="thresholdForm.reason" type="text" class="mt-1 block w-full rounded-md border-gray-300 text-sm" placeholder="Why the change, at least 10 characters"/>
                            </label>
                            <label class="sm:col-span-3 text-xs text-gray-600">Rebuttal basis (needed when Stage 2 starts later than 30 days)
                                <textarea v-model="thresholdForm.rebuttal_basis" rows="2" class="mt-1 block w-full rounded-md border-gray-300 text-sm"></textarea>
                            </label>
                            <div class="sm:col-span-3 flex items-center justify-between">
                                <JetInputError :message="Object.values(thresholdForm.errors)[0]"/>
                                <button type="submit" :disabled="thresholdForm.processing" class="rounded-md bg-maiic-600 px-4 py-2 text-sm font-semibold text-white hover:bg-maiic-700">Propose</button>
                            </div>
                        </form>
                    </div>""")
edit(p, r"""    { key: 'staging', label: 'Staging basis', description: 'How days past due are counted and the thresholds that set each stage. Read only here.' },""",
r"""    { key: 'staging', label: 'Staging basis', description: 'How days past due are counted and the thresholds that set each stage. Thresholds change here under maker-checker; the other settings in the Governance Centre.' },""")
edit(p, r"""            reportingForm: this.$inertia.form({
                currency: this.reporting.currency,
            }),""", r"""            reportingForm: this.$inertia.form({
                currency: this.reporting.currency,
            }),
            thresholdForm: this.$inertia.form({
                facility_class: 'DEFAULT', min_tenor_months: 0, stage2_dpd: null, stage3_dpd: null,
                effective_from: '', reason: '', rebuttal_basis: '',
            }),""")
edit(p, r"""        dpdBasis() {""", r"""        currentUserId() {
            return this.$page.props.auth?.user?.id ?? null
        },
        dpdBasis() {""")
edit(p, r"""        saveEmail() {""", r"""        proposeThreshold() {
            this.thresholdForm.post(this.route('settings.staging-thresholds.propose'), { preserveScroll: true, onSuccess: () => this.thresholdForm.reset() })
        },
        approveThreshold(t) {
            const what = this.classLabel(t.facility_class) + ' from ' + t.min_tenor_months + ' months: Stage 2 from ' + t.stage2_dpd + ' days, Stage 3 from ' + t.stage3_dpd + ', effective ' + t.effective_from
            if (!window.confirm('Approve ' + what + '?')) return
            this.$inertia.post(this.route('settings.staging-thresholds.approve', t.id), {}, { preserveScroll: true })
        },
        rejectThreshold(t) {
            const reason = window.prompt('Why is this proposal rejected? (at least 10 characters)')
            if (!reason) return
            this.$inertia.post(this.route('settings.staging-thresholds.reject', t.id), { reason }, { preserveScroll: true })
        },
        saveEmail() {""")
