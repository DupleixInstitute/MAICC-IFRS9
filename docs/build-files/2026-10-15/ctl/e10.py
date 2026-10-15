from ed import edit
p='app/Http/Controllers/EirTakeonController.php'
edit(p, r"""            'blocks' => $blocks, 'population' => $population, 'basisInForce' => $basis,""", r"""            'blocks' => $blocks, 'population' => $population, 'basisInForce' => $basis,
            // the Build with approval (spec 6.9): the open proposal, so the screen offers the approval to a second person
            'pendingBuild' => ($pb = app(TakeonLandingService::class)->pendingBuild()) ? ['id' => $pb->id, 'load_id' => $pb->load_id, 'proposed_by' => $pb->proposed_by,
                'proposer' => DB::table('users')->where('id', $pb->proposed_by)->value('name'), 'proposed_at' => $pb->proposed_at, 'reason' => $pb->reason] : null,
            'userId' => auth()->id(),""")
edit(p, r"""    public function build(Request $request, TakeonLandingService $service)
    {
        try {
            $c = $service->build($request->user()->id);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Take-on population built: {$c['accounts']} accounts, {$c['recomputed']} recomputed from origination, {$c['takeon_balance']} at the take-on balance, {$c['refused']} refused.");
    }""", r"""    /**
     * The Build with approval (spec 6.9): with no open proposal the first
     * press proposes the build; a different person's press approves the open
     * proposal and builds. The proposer pressing again is refused.
     */
    public function build(Request $request, TakeonLandingService $service)
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        try {
            $pending = $service->pendingBuild();
            if ($pending === null) {
                $service->proposeBuild($request->user()->id, $data['reason'] ?? null);

                return back()->with('success', 'Build proposed. A second person approves it on this screen; nothing is built until then.');
            }
            $c = $service->approveBuild((int) $pending->id, $request->user()->id);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Take-on population built on approval: {$c['accounts']} accounts, {$c['recomputed']} recomputed from origination, {$c['takeon_balance']} at the take-on balance, {$c['refused']} refused.");
    }""")
p='resources/js/Pages/Eir/Takeon/Index.vue'
edit(p, r"""      <button v-if="canGovern && load" type="button" class="secondary-btn" title="Build the take-on population under the basis in force; the previous build is replaced" @click="build">Build the take-on population</button>""",
r"""      <button v-if="canGovern && load && !pendingBuild" type="button" class="secondary-btn" title="Propose the build of the take-on population under the basis in force; a second person approves it, and only the approval builds" @click="build">Propose the take-on build</button>
      <button v-if="canGovern && pendingBuild && pendingBuild.proposed_by !== userId" type="button" class="primary-btn" :title="'Proposed by ' + (pendingBuild.proposer || 'another person') + '; approving builds the population and replaces the previous build'" @click="build">Approve and build</button>
      <span v-if="pendingBuild && pendingBuild.proposed_by === userId" class="maiic-badge maiic-badge-gold" title="The person who proposed the build cannot approve it">Build proposed by you: a second person approves</span>""")
edit(p, r"""  props: { tab: { type: String, default: 'blocks' }, tabCounts: Object, load: Object, blocks: Array, population: Array, basisInForce: String, canGovern: Boolean },""",
r"""  props: { tab: { type: String, default: 'blocks' }, tabCounts: Object, load: Object, blocks: Array, population: Array, basisInForce: String, canGovern: Boolean, pendingBuild: Object, userId: Number },""")
edit(p, r"""    async build() {
      if (!(await confirmDialog({ title: 'Build the take-on population?', message: 'It is built under the basis in force and replaces the previous build.', confirmLabel: 'Build' }))) return
      router.post(route('eir-takeon.build'), {}, { preserveScroll: true })
    },""", r"""    async build() {
      const approving = !!this.pendingBuild
      const ok = await confirmDialog(approving
        ? { title: 'Approve and build the take-on population?', message: 'Proposed by ' + (this.pendingBuild.proposer || 'another person') + '. It is built under the basis in force and replaces the previous build.', confirmLabel: 'Approve and build' }
        : { title: 'Propose the take-on build?', message: 'A second person approves it on this screen; only the approval builds.', confirmLabel: 'Propose' })
      if (!ok) return
      router.post(route('eir-takeon.build'), {}, { preserveScroll: true })
    },""")
edit(p, r"""Not built yet. Load the workbook, then use Build the take-on population (top right).""", r"""Not built yet. Load the workbook, then propose the take-on build (top right); a second person approves it.""")
edit(p, r"""<strong>Build the take-on population</strong> does""", r"""<strong>The approved take-on build</strong> does""")
p='app/Console/Commands/LandTakeonWorkbook.php'
edit(p, r"""        {--build : Also build contract_takeon under takeon_history_basis}""", r"""        {--build : Also propose the build of contract_takeon under takeon_history_basis (a second person approves it)}
        {--approve-build= : Approve the proposed take-on build with this id and build (a different --user from the proposer)}""")
edit(p, r"""    public function handle(TakeonLandingService $service): int
    {
        $dir = base_path('docs/bootstrap/takeon');""", r"""    public function handle(TakeonLandingService $service): int
    {
        if ($this->option('approve-build') !== null) {
            try {
                $c = $service->approveBuild((int) $this->option('approve-build'), $this->option('user') !== null ? (int) $this->option('user') : null);
            } catch (Throwable $e) {
                $this->error($e->getMessage());
                return self::FAILURE;
            }
            $this->info("Take-on population built on approval under '{$c['setting']}': {$c['accounts']} accounts, {$c['recomputed']} recomputed from origination, {$c['takeon_balance']} starting at the take-on balance, {$c['refused']} refused.");

            return self::SUCCESS;
        }
        $dir = base_path('docs/bootstrap/takeon');""")
edit(p, r"""            try {
                $c = $service->build($user);
            } catch (Throwable $e) {
                $this->error($e->getMessage());
                return self::FAILURE;
            }
            $this->info("Take-on population built under '{$c['setting']}': {$c['accounts']} accounts, {$c['recomputed']} recomputed from origination, {$c['takeon_balance']} starting at the take-on balance, {$c['refused']} refused.");""",
r"""            try {
                $p = $service->proposeBuild($user);
            } catch (Throwable $e) {
                $this->error($e->getMessage());
                return self::FAILURE;
            }
            $this->info("Take-on build proposed (request {$p['request']}); a second person approves it: php artisan eir:land-takeon --approve-build={$p['request']} --user=<approver>.");""")
