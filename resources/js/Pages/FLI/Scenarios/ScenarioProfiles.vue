<template>
  <AppLayout title="Scenario Profiles" description="Reusable templates of economic scenarios: names, weights, narratives, anchors and shocks. Start the month's scenario set from a profile, then propose and approve it on Scenario Sets.">
    <template #actions>
      <button v-if="canGovern && !profiles.some(p => p.profile_code === 'MAIIC4')" type="button" class="secondary-btn text-sm" @click="seed">Add the standard profile</button>
      <button type="button" class="secondary-btn text-sm" @click="openScenarioForm()"><font-awesome-icon icon="plus" class="mr-1" />Scenario</button>
      <button type="button" class="primary-btn text-sm" @click="openProfileForm()"><font-awesome-icon icon="plus" class="mr-1" />Profile</button>
    </template>

    <div class="w-full space-y-4">
      <div class="maiic-panel">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-3 dark:border-slate-700">
          <div><h3 class="font-semibold text-gray-900">Profiles</h3><p class="text-xs text-gray-500">{{ profiles.length }} profile(s). The weights of a profile must total 100 before a set started from it can be proposed.</p></div>
          <label v-if="canGovern" class="flex items-center gap-2 text-xs font-semibold text-gray-600">Start a set for
            <select v-model="period" class="maiic-input !w-36"><option v-for="p in periods" :key="p" :value="p">{{ monthLabel(p) }}{{ setsByPeriod[p] ? ' (' + setsByPeriod[p] + ' set' + (setsByPeriod[p] > 1 ? 's' : '') + ')' : '' }}</option></select>
          </label>
        </div>
        <div class="maiic-table-wrap"><table class="maiic-table">
          <thead><tr><th>Code</th><th>Profile</th><th>Scenarios and weights</th><th class="num">Total</th><th class="num">Sets started</th><th class="text-right">Actions</th></tr></thead>
          <tbody>
            <tr v-for="profile in pagedProfiles" :key="profile.id">
              <td class="font-semibold text-gray-900">{{ profile.profile_code }}</td>
              <td class="max-w-[18rem]"><div class="font-semibold">{{ profile.name }}</div><div class="text-xs text-gray-500">{{ profile.description }}</div></td>
              <td class="text-xs">
                <span v-for="s in profile.scenarios" :key="s.id" class="mr-2 inline-block"><span class="font-semibold">{{ s.name }}</span> {{ weight(profile, s) }}%<span v-if="s.is_base_case" class="text-maiic-700"> (base)</span><span v-if="shocks(profile, s.name)" class="text-gray-400"> · {{ shocks(profile, s.name) }} shocks</span></span>
                <span v-if="!profile.scenarios.length" class="text-gray-400">No scenario yet</span>
              </td>
              <td class="num"><span class="maiic-badge" :class="Math.abs(profile.weights_sum - 100) < 0.01 ? 'maiic-badge-green' : 'maiic-badge-gold'">{{ profile.weights_sum }}%</span></td>
              <td class="num">{{ profile.sets_started }}</td>
              <td><div class="flex justify-end gap-1.5">
                <button v-if="canGovern" type="button" class="primary-btn !px-2.5 !py-1 text-xs" :disabled="!profile.scenarios.length" :title="'Start a draft scenario set for ' + monthLabel(period) + ' from this profile'" @click="start(profile)">Start a set</button>
                <button type="button" class="maiic-action maiic-action-view" title="Open the profile's scenarios" @click="viewProfile(profile)"><font-awesome-icon icon="eye" /></button>
                <button type="button" class="maiic-action maiic-action-edit" title="Edit the profile" @click="openProfileForm(profile)"><font-awesome-icon icon="pen" /></button>
              </div></td>
            </tr>
            <tr v-if="!profiles.length"><td colspan="6" class="maiic-empty">No profile yet. Add the standard profile (four scenarios anchored to Malawi episodes) or create your own.</td></tr>
          </tbody>
        </table></div>
        <RowPager v-model="page" :total="profiles.length" class="border-t border-gray-100" />
      </div>
    </div>

    <div v-if="showProfileForm" class="fixed inset-0 z-50 flex items-center justify-center">
      <div class="absolute inset-0 bg-gray-500 bg-opacity-75" @click="closeForm"></div>
      <div class="relative mx-4 w-full max-w-lg rounded-lg bg-white shadow-xl dark:bg-slate-800"><ProfileForm :profile="currentProfile" @close="closeForm" @saved="reload" /></div>
    </div>
    <div v-if="showScenarioForm" class="fixed inset-0 z-50 flex items-center justify-center">
      <div class="absolute inset-0 bg-gray-500 bg-opacity-75" @click="closeForm"></div>
      <div class="relative mx-4 w-full max-w-lg rounded-lg bg-white shadow-xl dark:bg-slate-800"><ScenarioForm :scenario="currentScenario" :profiles="profiles" @close="closeForm" @saved="reload" /></div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import RowPager from '@/Components/Maiic/RowPager.vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import ProfileForm from './Components/ProfileForm.vue'
import ScenarioForm from './ScenarioForm.vue'
import { confirmDialog } from '@/Components/confirmDialog'

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
const props = defineProps({ profiles: Array, periods: Array, setsByPeriod: Object, canGovern: Boolean })

const page = ref(1)
const period = ref(props.periods?.[0] || '')
const pagedProfiles = computed(() => (props.profiles || []).slice((page.value - 1) * 15, page.value * 15))
const showProfileForm = ref(false)
const currentProfile = ref(null)
const showScenarioForm = ref(false)
const currentScenario = ref(null)

function monthLabel(p) { if (!p) return '-'; const [y, m] = String(p).split('-'); return m ? `${MONTHS[Number(m) - 1]} ${y}` : p }
function weight(profile, s) { const p = Number(s.probability); const frac = profile.weights_sum <= 100.01 && profile.scenarios.reduce((a, x) => a + Number(x.probability), 0) <= 1.0001; return (frac ? p * 100 : p).toFixed(1).replace(/\.0$/, '') }
function shocks(profile, name) { const d = (profile.design || []).find(x => x.name === name); return d && d.shocks ? d.shocks.length : 0 }
function openProfileForm(profile = null) { currentProfile.value = profile; showProfileForm.value = true }
function openScenarioForm() { currentScenario.value = null; showScenarioForm.value = true }
function reload() { router.reload({ only: ['profiles'] }) }
function closeForm() { currentProfile.value = null; showProfileForm.value = false; currentScenario.value = null; showScenarioForm.value = false }
function viewProfile(profile) { router.get(route('scenarios.index', profile.id)) }
function seed() { router.post(route('scenarios.profiles.seed'), {}, { preserveScroll: true }) }
async function start(profile) {
  if (!(await confirmDialog({ title: `Start a scenario set for ${monthLabel(period.value)}?`, message: `A draft set is created from ${profile.name}, with its scenarios, weights and shocks. It reaches no loan until it is proposed and approved on Scenario Sets.`, confirmLabel: 'Start the set' }))) return
  router.post(route('scenarios.profiles.start', profile.id), { period: period.value })
}
</script>
