R = 'C:/xampp/htdocs/MAICC-IFRS9/'

def patch(p, pairs):
    s = open(R + p, encoding='utf-8').read()
    for a, b in pairs:
        assert a in s, (p, a[:90])
        s = s.replace(a, b, 1)
    open(R + p, 'w', encoding='utf-8').write(s)

FEED = 'resources/js/Pages/Eir/Feed/Index.vue'
patch(FEED, [
# actions, per tab
("""    <template #actions>
      <a :href="route('eir-feed.queries')" class="secondary-btn" title="The extract queries, as one file to run on E-Banker">Download the queries</a>
    </template>""",
 """    <template #actions>
      <a v-if="tab === 'loads' || tab === 'queries'" :href="route('eir-feed.queries')" class="secondary-btn" title="The extract queries, as one file to run on E-Banker"><font-awesome-icon icon="download" /> Download the queries</a>
      <button v-if="tab === 'loads' && canGovern" type="button" class="primary-btn" @click="packOpen = true"><font-awesome-icon icon="upload" /> Load a pack</button>
      <button v-if="tab === 'builds' && canGovern" type="button" class="primary-btn" @click="buildOpen = true"><font-awesome-icon icon="plus" /> Propose a build</button>
    </template>"""),
# loads: description only, no inline form
("""              <p class="text-sm text-gray-500">Every pack that came in, by any route. A pack that fails a check is quarantined, with the file and row named.</p>
              <form v-if="canGovern" @submit.prevent="landPack" class="flex flex-wrap items-center gap-2">
                <input type="file" accept=".zip" @change="packFile = $event.target.files[0]" class="text-sm"/>
                <button type="submit" :disabled="!packFile || landing" class="primary-btn">{{ landing ? 'Loading...' : 'Load a pack' }}</button>
              </form>
            </div>
            <details v-if="canGovern" class="border-b border-gray-200 px-5 py-2 text-sm text-gray-700">
              <summary class="cursor-pointer font-semibold text-maiic-800">How to load a pack</summary>
              <p class="mt-1 pb-1">Run the queries (Download the queries, top right) on E-Banker, put the CSV extracts and their <span class="font-mono">manifest.json</span> in one zip file, and load the zip here. The manifest lists each file with its row count; the checks compare every file with it before anything is kept.</p>
            </details>""",
 """              <p class="text-sm text-gray-500">Every pack that came in, by any route. A pack that fails a check is quarantined, with the file and row named. Use <strong>Load a pack</strong> for the steps.</p>
            </div>"""),
# builds: description only
("""              <p class="text-sm text-gray-500">One person proposes a build of the loan book and a second approves it. The differences from the previous build are shown first; a locked period is never restated.</p>
              <form v-if="canGovern" @submit.prevent="requestBuild" class="mt-2 flex flex-wrap items-end gap-3">
                <label><span class="maiic-flabel">From</span><input v-model="build.from" type="month" class="maiic-input" required/></label>
                <label><span class="maiic-flabel">To</span><input v-model="build.to" type="month" class="maiic-input" required/></label>
                <label><span class="maiic-flabel">Method</span>
                  <select v-model="build.method" class="maiic-select"><option value="">In force on each period end</option><option value="A">A: from the stored run</option><option value="B">B: derived from the ledger</option></select>
                </label>
                <label class="flex items-center gap-2 pb-2 text-sm text-gray-700" title="Retires rows of this month that came from older file imports, not from any build, and that this build does not produce. Mega Farm and out-of-scope rows are never touched."><input v-model="build.retire_stale" type="checkbox"/> Retire rows from older imports</label>
                <button type="submit" class="primary-btn" :disabled="building">{{ building ? 'Proposing...' : 'Propose a build' }}</button>
              </form>
            </div>""",
 """              <p class="text-sm text-gray-500">One person proposes a build of the loan book and a second approves it. The differences from the previous build are shown first; a locked period is never restated. Use <strong>Propose a build</strong> for the steps.</p>
            </div>"""),
("""                  <tr v-if="!builds.length"><td :colspan="canGovern ? 8 : 7" class="maiic-empty">No build yet. Choose the periods above and propose one.</td></tr>""",
 """                  <tr v-if="!builds.length"><td :colspan="canGovern ? 8 : 7" class="maiic-empty">No build yet. Use <strong>Propose a build</strong> to choose the months.</td></tr>"""),
# the two dialogs, before </app-layout>
("""      </div>
    </div>
  </app-layout>
</template>""",
 """      </div>
    </div>

    <!-- Load a pack -->
    <jet-modal :show="packOpen" max-width="3xl" @close="packOpen = false">
      <form class="p-6" @submit.prevent="landPack">
        <h3 class="text-lg font-semibold text-gray-900">Load an E-Banker pack</h3>
        <div class="mt-3 grid gap-4 md:grid-cols-2">
          <div class="rounded-lg bg-maiic-50 p-3 text-sm text-gray-700">
            <p class="font-semibold text-maiic-800">What a pack is</p>
            <p class="mt-1">The results of the versioned E-Banker queries, one CSV file per query, with a <span class="font-mono">manifest.json</span> that lists each file, its query and its row count, zipped into one file.</p>
            <p class="mt-2 font-semibold text-maiic-800">How to make one</p>
            <ol class="mt-1 list-decimal space-y-0.5 pl-5">
              <li>Press <strong>Download the queries</strong> and run them in SQL Developer, starting with RUN 0 (ISO dates and a point for decimals).</li>
              <li>Export each result as CSV with its query id in the file name (for example <span class="font-mono">P1_01_ledger_all.csv</span>).</li>
              <li>Write the manifest: every file, its query id and its row count.</li>
              <li>Zip the CSV files and the manifest together, and choose the zip below.</li>
            </ol>
          </div>
          <div class="rounded-lg bg-gray-50 p-3 text-sm text-gray-700">
            <p class="font-semibold text-gray-800">What happens when you load it</p>
            <ul class="mt-1 list-disc space-y-0.5 pl-5">
              <li>Every file is checked against the manifest before anything is kept: the row counts, the accounts against the account master, the balances against the ledger, the month-end rows and the date format.</li>
              <li>A pack that fails a check is quarantined: it is listed with the file and row named, and nothing reads it.</li>
              <li>The same pack loaded twice is recognised and nothing changes; a row that changed is kept as a new version.</li>
              <li>Loading a pack does not change the loan book: a build does, on the Loan Book Builds tab, with a second person's approval.</li>
            </ul>
          </div>
        </div>
        <div class="mt-4">
          <label class="maiic-flabel" for="pack-file">Pack (zip)</label>
          <input id="pack-file" type="file" accept=".zip" class="block w-full text-sm" @change="packFile = $event.target.files[0]" />
        </div>
        <label class="mt-3 flex items-center gap-2 text-sm text-gray-700"><input v-model="packDryRun" type="checkbox" /> Check only: run the checks and keep nothing</label>
        <div class="mt-6 flex justify-end gap-2">
          <button type="button" class="secondary-btn" :disabled="landing" @click="packOpen = false">Cancel</button>
          <button type="submit" class="primary-btn" :disabled="!packFile || landing">{{ landing ? 'Checking...' : (packDryRun ? 'Check the pack' : 'Load the pack') }}</button>
        </div>
      </form>
    </jet-modal>

    <!-- Propose a build -->
    <jet-modal :show="buildOpen" max-width="2xl" @close="buildOpen = false">
      <form class="p-6" @submit.prevent="requestBuild">
        <h3 class="text-lg font-semibold text-gray-900">Propose a build of the loan book</h3>
        <div class="mt-3 rounded-lg bg-maiic-50 p-3 text-sm text-gray-700">
          <ul class="list-disc space-y-0.5 pl-5">
            <li>A build writes the month-end loan book for each month in the range from the packs loaded. It is only a proposal until a second person approves it here.</li>
            <li><strong>A: from the stored run</strong> takes E-Banker's own Loan Book Report for the month end. <strong>B: derived from the ledger</strong> rebuilds each balance from the postings. Leave it on the method in force unless you have a reason.</li>
            <li>The proposal shows, month by month, what would change against the loan book held now. A locked month is never restated.</li>
            <li>Calculations already run for a month (staging, PD, LGD, ECL, EIR) are not re-run by the build; run them again after it.</li>
          </ul>
        </div>
        <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-3">
          <label><span class="maiic-flabel">From</span><input v-model="build.from" type="month" class="maiic-input" required/></label>
          <label><span class="maiic-flabel">To</span><input v-model="build.to" type="month" class="maiic-input" required/></label>
          <label><span class="maiic-flabel">Method</span>
            <select v-model="build.method" class="maiic-select"><option value="">In force on each period end</option><option value="A">A: from the stored run</option><option value="B">B: derived from the ledger</option></select>
          </label>
        </div>
        <label class="mt-3 flex items-center gap-2 text-sm text-gray-700" title="Retires rows of this month that came from older file imports, not from any build, and that this build does not produce. Mega Farm and out-of-scope rows are never touched."><input v-model="build.retire_stale" type="checkbox"/> Retire rows from older imports that this build does not produce</label>
        <div class="mt-6 flex justify-end gap-2">
          <button type="button" class="secondary-btn" :disabled="building" @click="buildOpen = false">Cancel</button>
          <button type="submit" class="primary-btn" :disabled="building || !build.from || !build.to">{{ building ? 'Proposing...' : 'Propose the build' }}</button>
        </div>
      </form>
    </jet-modal>
  </app-layout>
</template>"""),
("""import AppLayout from '@/Layouts/AppLayout.vue'
import LocalPager from '@/Components/Data/LocalPager.vue'""",
 """import AppLayout from '@/Layouts/AppLayout.vue'
import JetModal from '@/Jetstream/Modal.vue'
import LocalPager from '@/Components/Data/LocalPager.vue'"""),
("""  components: { AppLayout, LocalPager },""", """  components: { AppLayout, JetModal, LocalPager },"""),
("""      packFile: null, landing: false, building: false,""", """      packFile: null, packDryRun: false, packOpen: false, buildOpen: false, landing: false, building: false,"""),
("""      router.post(route('eir-feed.land'), { pack: this.packFile }, { forceFormData: true, onFinish: () => { this.landing = false; this.packFile = null } })""",
 """      router.post(route('eir-feed.land'), { pack: this.packFile, dry_run: this.packDryRun ? 1 : 0 }, { forceFormData: true, onFinish: () => { this.landing = false; this.packFile = null; this.packOpen = false } })"""),
("""      router.post(route('eir-feed.build'), this.build, { preserveScroll: true, onFinish: () => { this.building = false } })""",
 """      router.post(route('eir-feed.build'), this.build, { preserveScroll: true, onFinish: () => { this.building = false; this.buildOpen = false } })"""),
])

# the controller: the check-only run
patch('app/Http/Controllers/EirFeedController.php', [
("""            $r = $service->land($packDir, $request->user()->id, PackLandingService::ROUTE_MANUAL);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
        $note = $extracted['skipped'] !== [] ? ' ' . count($extracted['skipped']) . ' entry name(s) were refused and left out: ' . implode(', ', $extracted['skipped']) . '.' : '';
""",
 """            $r = $service->land($packDir, $request->user()->id, PackLandingService::ROUTE_MANUAL, $request->boolean('dry_run'));
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
        $note = $extracted['skipped'] !== [] ? ' ' . count($extracted['skipped']) . ' entry name(s) were refused and left out: ' . implode(', ', $extracted['skipped']) . '.' : '';
        if ($request->boolean('dry_run')) {
            $failed = array_filter($r['gates']['files'] ?? [], fn ($f) => ($f['failures'] ?? []) !== []);
            $packFails = array_filter($r['gates']['pack'] ?? [], fn ($g) => is_array($g) && ($g['result'] ?? '') === 'FAIL');

            return back()->with($failed === [] && $packFails === [] ? 'success' : 'error', 'Check only, nothing kept: '
                . ($failed === [] && $packFails === [] ? 'every check passed; load the pack to keep it.' : count($failed) . ' file(s) and ' . count($packFails) . ' pack check(s) failed: '
                    . implode('; ', array_map(fn ($name, $f) => $name . ' ' . implode(', ', $f['failures']), array_keys($failed), $failed))) . $note);
        }
"""),
])

# take-on page
TK = 'resources/js/Pages/Eir/Takeon/Index.vue'
patch(TK, [
("""    <template #actions>
      <button v-if="canGovern && load" @click="build" class="primary-btn" title="Build the take-on population under the basis in force; the previous build is replaced">Build the take-on population</button>
    </template>""",
 """    <template #actions>
      <button v-if="canGovern && tab === 'blocks'" type="button" class="primary-btn" @click="uploadOpen = true"><font-awesome-icon icon="upload" /> Load take-on workbook</button>
      <button v-if="canGovern && load" type="button" class="secondary-btn" title="Build the take-on population under the basis in force; the previous build is replaced" @click="build">Build the take-on population</button>
    </template>"""),
("""            <details v-if="canGovern" class="border-b border-gray-200 px-5 py-2 text-sm text-gray-700" :open="!load">
              <summary class="cursor-pointer font-semibold text-maiic-800">Load a take-on workbook</summary>
              <p class="mt-1">Choose the take-on workbook exactly as received and the returned mapping workbook (account mapping and fees), both as .xlsx, then click Load.</p>
              <form @submit.prevent="upload" class="mt-2 flex flex-wrap items-end gap-3 pb-1">
                <label><span class="maiic-flabel">Original workbook (as received)</span><input type="file" accept=".xlsx" class="text-sm" @change="files.original = $event.target.files[0]"/></label>
                <label><span class="maiic-flabel">Mapping workbook with fees</span><input type="file" accept=".xlsx" class="text-sm" @change="files.mapping = $event.target.files[0]"/></label>
                <button type="submit" class="primary-btn" :disabled="!files.original || !files.mapping || uploading">{{ uploading ? 'Loading...' : 'Load' }}</button>
              </form>
            </details>""", ""),
])
s = open(R + TK, encoding='utf-8').read()
a = s.rindex('  </app-layout>')
s = s[:a] + """    <jet-modal :show="uploadOpen" max-width="3xl" @close="uploadOpen = false">
      <form class="p-6" @submit.prevent="upload">
        <h3 class="text-lg font-semibold text-gray-900">Load the take-on workbook</h3>
        <div class="mt-3 grid gap-4 md:grid-cols-2">
          <div class="rounded-lg bg-maiic-50 p-3 text-sm text-gray-700">
            <p class="font-semibold text-maiic-800">The two files</p>
            <ul class="mt-1 list-disc space-y-0.5 pl-5">
              <li><strong>The original workbook</strong>: Finance's amortisation schedules at 31 October 2024, exactly as received (sheets Loan Book and Amortisation and Repayments).</li>
              <li><strong>The mapping workbook</strong>: the same schedules with each facility's E-Banker account (sheet Mapping, Finance's Y or N in column X) and the fees in the yellow columns of Upload summary (arrangement, legal, other, the fee date, whether deducted, the source).</li>
            </ul>
            <p class="mt-2">Load both as received: do not re-save them in another program.</p>
          </div>
          <div class="rounded-lg bg-gray-50 p-3 text-sm text-gray-700">
            <p class="font-semibold text-gray-800">What happens</p>
            <ul class="mt-1 list-disc space-y-0.5 pl-5">
              <li>Each block is checked: an account for it, its principal against E-Banker's take-on posting of 31 July 2024, a fee row. A block that fails is flagged or refused, with the cell named.</li>
              <li>Loading the workbook does not change any EIR. <strong>Build the take-on population</strong> does: where a block is mapped and has its fees, the EIR is solved from origination with the fees; otherwise the loan starts at its take-on balance.</li>
              <li>A later, corrected workbook is loaded the same way; the earlier load is kept.</li>
            </ul>
          </div>
        </div>
        <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
          <label><span class="maiic-flabel">Original workbook (as received)</span><input type="file" accept=".xlsx" class="block w-full text-sm" @change="files.original = $event.target.files[0]"/></label>
          <label><span class="maiic-flabel">Mapping workbook with fees</span><input type="file" accept=".xlsx" class="block w-full text-sm" @change="files.mapping = $event.target.files[0]"/></label>
        </div>
        <div class="mt-6 flex justify-end gap-2">
          <button type="button" class="secondary-btn" :disabled="uploading" @click="uploadOpen = false">Cancel</button>
          <button type="submit" class="primary-btn" :disabled="!files.original || !files.mapping || uploading">{{ uploading ? 'Loading...' : 'Load the workbook' }}</button>
        </div>
      </form>
    </jet-modal>
""" + s[a:]
open(R + TK, 'w', encoding='utf-8').write(s)
print('ok')
