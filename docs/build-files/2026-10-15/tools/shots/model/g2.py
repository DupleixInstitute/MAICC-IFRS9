import os, re, sys
sys.path.insert(0, os.path.dirname(__file__))
from vh import load, save, rep, rx
os.chdir(r'C:/xampp/htdocs/MAICC-IFRS9/resources/js/Pages/FLI')

p='Scenarios/ScenarioProfiles.vue'; s=load(p)
a=s.index('    <template #header>'); b=s.index('     <div v-if="showProfileForm"')
s=s[:a]+'''    <template #header>
      <div>
        <div class="mb-1 flex items-center gap-2 text-xs text-gray-500">
          <span>IFRS 9 Model Setup</span><span>/</span><span>Scenarios &amp; Overlays</span><span>/</span><span class="font-medium text-maiic-700">Scenario Profiles</span>
        </div>
        <h2 class="text-xl font-semibold text-gray-800">Scenario Profiles</h2>
        <p class="mt-1 text-sm text-gray-600">Named groups of economic scenarios (for example base, upside and downside) used by the weighted forecast</p>
      </div>
    </template>
    <template #actions>
      <button type="button" class="secondary-btn" @click="openScenarioForm()">Add scenario</button>
      <button type="button" class="primary-btn" @click="openProfileForm()">Create profile</button>
    </template>

    <div class="w-full space-y-4">
      <div class="maiic-panel">
        <div class="border-b border-gray-200 px-5 py-4">
          <h3 class="font-semibold text-gray-900">Profiles</h3>
          <p class="text-xs text-gray-500">{{ profiles.length }} profile(s). A profile is complete when its scenarios and weights are all in place.</p>
        </div>
        <div class="maiic-table-wrap overflow-x-auto">
          <table class="maiic-table">
            <thead>
              <tr><th>Code</th><th>Name</th><th>Created by</th><th>Status</th><th class="text-right">Actions</th></tr>
            </thead>
            <tbody>
              <tr v-for="profile in pagedProfiles" :key="profile.id">
                <td class="font-semibold text-gray-900">{{ profile.profile_code }}</td>
                <td>{{ profile.name }}</td>
                <td>{{ profile.created_by || '-' }}</td>
                <td><span class="maiic-badge" :class="profile.is_complete ? 'maiic-badge-green' : 'maiic-badge-gold'">{{ profile.is_complete ? 'Complete' : 'Incomplete' }}</span></td>
                <td>
                  <div class="flex justify-end gap-1.5">
                    <button type="button" class="maiic-action maiic-action-view" title="Open the profile's scenarios" @click="viewProfile(profile)"><font-awesome-icon icon="eye" /></button>
                  </div>
                </td>
              </tr>
              <tr v-if="!profiles.length">
                <td colspan="5" class="maiic-empty">No scenario profiles yet. Use <strong>Create profile</strong> at the top right, then add its scenarios.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <RowPager v-model="page" :total="profiles.length" class="border-t border-gray-100" />
      </div>
    </div>

'''+s[b:]
s=rep(s,"import { ref } from 'vue'","import { ref, computed } from 'vue'\nimport RowPager from '@/Components/Maiic/RowPager.vue'")
s=rep(s,'''const showProfileForm = ref(false)''','''const page = ref(1)
const pagedProfiles = computed(() => (props.profiles || []).slice((page.value - 1) * 15, page.value * 15))

const showProfileForm = ref(false)''')
s=rep(s,'''  Inertia.reload({ only: ['profiles'] })''','''  router.reload({ only: ['profiles'] })''')
save(p,s)

p='ScenarioSet/Index.vue'; s=load(p)
a=s.index('        <template #header>'); b=s.index('        <jet-confirmation-modal')
s=s[:a]+'''        <template #header>
            <div>
                <div class="mb-1 flex items-center gap-2 text-xs text-gray-500">
                    <span>IFRS 9 Model Setup</span><span>/</span><span>Scenarios &amp; Overlays</span><span>/</span><span class="font-medium text-maiic-700">Economic Scenarios</span>
                </div>
                <h2 class="text-xl font-semibold text-gray-800">Economic Scenarios</h2>
                <p class="mt-1 text-sm text-gray-600">Sets of economic scenarios and their macro paths, used by the external calculation</p>
            </div>
        </template>
        <template #actions>
            <Link :href="route('fli.scenarios.create')" class="primary-btn">Create scenario set</Link>
        </template>

        <div class="w-full space-y-4">
            <div class="maiic-panel">
                <div class="flex flex-col gap-3 border-b border-gray-200 px-5 py-4 md:flex-row md:items-end md:justify-between">
                    <div>
                        <h3 class="font-semibold text-gray-900">Scenario sets</h3>
                        <p class="text-xs text-gray-500">{{ scenarioSets.total ?? scenarioSets.data.length }} set(s)</p>
                    </div>
                    <div class="flex items-end gap-2">
                        <label class="block"><span class="maiic-flabel">Search</span><input v-model="form.search" type="text" class="maiic-input !w-64" placeholder="Name or description" /></label>
                        <button v-if="form.search" type="button" class="secondary-btn" @click="reset">Clear</button>
                    </div>
                </div>
                <div class="maiic-table-wrap overflow-x-auto">
                    <table class="maiic-table">
                        <thead>
                            <tr><th>Name</th><th>Description</th><th>Status</th><th>Created by</th><th class="text-right">Actions</th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="set in scenarioSets.data" :key="set.id">
                                <td class="font-semibold text-gray-900"><Link :href="route('fli.scenarios.edit', set.id)" class="hover:underline">{{ set.name }}</Link><div class="text-xs font-normal text-gray-500">#{{ set.id }}</div></td>
                                <td>{{ set.description || '-' }}</td>
                                <td><span class="maiic-badge" :class="set.is_active ? 'maiic-badge-green' : 'maiic-badge-grey'">{{ set.is_active ? 'Active' : 'Inactive' }}</span></td>
                                <td>{{ set.creator?.name || '-' }}</td>
                                <td>
                                    <div class="flex justify-end gap-1.5">
                                        <Link :href="route('fli.scenarios.edit', set.id)" class="maiic-action maiic-action-edit" title="Edit scenario set"><font-awesome-icon icon="pen" /></Link>
                                        <button type="button" class="maiic-action maiic-action-delete" title="Delete scenario set" @click="deleteAction(set.id)"><font-awesome-icon icon="trash" /></button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!scenarioSets.data.length">
                                <td colspan="5" class="maiic-empty">{{ form.search ? 'No scenario sets match the search.' : 'No scenario sets yet. Use Create scenario set at the top right.' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="scenarioSets.links && scenarioSets.links.length > 3" class="border-t border-gray-100 px-4 pb-4">
                    <pagination :links="scenarioSets.links"/>
                </div>
            </div>
        </div>
'''+s[b:]
s=rep(s,"import AppLayout from '@/Layouts/AppLayout.vue'","import AppLayout from '@/Layouts/AppLayout.vue'\nimport { Link } from '@inertiajs/vue3'")
s=rep(s,'''    components: {
        AppLayout,''','''    components: {
        AppLayout,
        Link,''')
s=rep(s,'''                Are you sure you want to delete this scenario set? This action cannot be undone.''','''                The scenario set and its scenarios are removed. This cannot be undone.''')
s=rep(s,'''            <template #title>
                Delete Scenario Set
            </template>''','''            <template #title>
                Delete this scenario set?
            </template>''')
save(p,s)
print('ok')
