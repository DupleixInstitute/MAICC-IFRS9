from ed import edit
p='app/Services/Ebanker/TakeonLandingService.php'
edit(p, r"""    public function build(?int $userId = null): array
    {""", r"""    /**
     * The Build with approval (spec 6.9): one person proposes the build of the
     * latest landed workbook, a different person approves it, and only the
     * approval builds. The bootstrap may approve under its own label, which
     * is recorded and is never a MAIIC approval. A newer proposal supersedes
     * an older one that was not approved.
     *
     * @return array{request:int, load_id:int}
     */
    public function proposeBuild(?int $userId, ?string $reason = null): array
    {
        if ($userId === null) {
            throw new RuntimeException('A take-on build is proposed by a named person.');
        }
        $loadId = DB::table('ebanker_loads')->where('route', self::ROUTE)->where('status', 'LANDED')->orderByDesc('id')->value('id');
        if ($loadId === null) {
            throw new RuntimeException('No take-on workbook has been landed.');
        }
        $id = DB::transaction(function () use ($loadId, $userId, $reason) {
            DB::table('takeon_build_requests')->where('status', 'PROPOSED')->update(['status' => 'SUPERSEDED', 'updated_at' => now()]);

            return DB::table('takeon_build_requests')->insertGetId(['load_id' => $loadId, 'status' => 'PROPOSED', 'proposed_by' => $userId, 'proposed_at' => now(),
                'reason' => $reason !== null ? mb_substr($reason, 0, 500) : null, 'created_at' => now(), 'updated_at' => now()]);
        });
        AuditLoggerService::log('Take-on Build Proposed', 'takeon_build_requests', $id, ['new_values' => ['load_id' => $loadId, 'reason' => $reason], 'meta' => ['user' => $userId]]);

        return ['request' => (int) $id, 'load_id' => (int) $loadId];
    }

    /** The open proposal, if any. */
    public function pendingBuild(): ?object
    {
        if (! DB::getSchemaBuilder()->hasTable('takeon_build_requests')) {
            return null;
        }

        return DB::table('takeon_build_requests')->where('status', 'PROPOSED')->orderByDesc('id')->first();
    }

    /**
     * Approve a proposed build and run it. The approver must be named and a
     * different person from the proposer, unless the bootstrap label stands in.
     * The proposal must be for the workbook still landed last.
     */
    public function approveBuild(int $requestId, ?int $approverId, ?string $label = null): array
    {
        $request = DB::table('takeon_build_requests')->where('id', $requestId)->first() ?? throw new RuntimeException("No take-on build request {$requestId}.");
        if ($request->status !== 'PROPOSED') {
            throw new RuntimeException("Take-on build request {$requestId} is {$request->status}; only a proposed build can be approved.");
        }
        $bootstrap = $label === LoanBookBuildService::BOOTSTRAP_LABEL;
        if ($label !== null && ! $bootstrap) {
            throw new RuntimeException('Only the bootstrap label may stand in for a named approver.');
        }
        if (! $bootstrap && ($approverId === null || $request->proposed_by === null || (int) $request->proposed_by === $approverId)) {
            throw new RuntimeException('Maker-checker: the take-on build is approved by a different person from the one who proposed it, and both must be named.');
        }
        $latest = DB::table('ebanker_loads')->where('route', self::ROUTE)->where('status', 'LANDED')->orderByDesc('id')->value('id');
        if ((int) $latest !== (int) $request->load_id) {
            throw new RuntimeException('A later take-on workbook has been landed since the build was proposed; propose the build again.');
        }
        $counts = $this->build($approverId);
        DB::table('takeon_build_requests')->where('id', $requestId)->update(['status' => 'APPROVED', 'approved_by' => $approverId, 'approver_label' => $bootstrap ? $label : null,
            'approved_at' => now(), 'result' => json_encode(array_diff_key($counts, ['fees' => 1])), 'updated_at' => now()]);
        AuditLoggerService::log('Take-on Build Approved', 'takeon_build_requests', $requestId, ['new_values' => array_diff_key($counts, ['fees' => 1]), 'meta' => ['proposed_by' => $request->proposed_by, 'approved_by' => $approverId, 'label' => $bootstrap ? $label : null]]);

        return $counts + ['request' => $requestId];
    }

    /**
     * The build itself. It is reached through approveBuild (the screen, the
     * console and the bootstrap); called directly it is the engine step the
     * tests exercise.
     */
    public function build(?int $userId = null): array
    {""")
