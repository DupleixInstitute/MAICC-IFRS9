p = r'C:\xampp\htdocs\MAICC-IFRS9\database\migrations\2026_10_14_230000_restructure_register_and_modifications.php'
s = open(p, encoding='utf-8').read()
old_start = s.index("        if (Schema::hasTable('rate_reset_events') && ! Schema::hasColumn('rate_reset_events', 'detection_key')) {")
old_end = s.index("        foreach (['eir_amortisation', 'eir_amortisation_history'] as $table) {\n            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'modification_adjustment')) {")
new = '''        if (Schema::hasTable('rate_reset_events')) {
            // The first version of this migration named the maker-checker state `status`; the
            // reset pipeline of 2026_10_01_000000 owns `status` (DETECTED, SCHEDULED, SOLVED,
            // BLOCKED). On a database where that first version ran, the column is renamed by
            // 2026_10_01_000000 (it runs after it there); here only while the pipeline's columns
            // are absent, so the pipeline's `status` is never taken for the register's.
            if (Schema::hasColumn('rate_reset_events', 'detection_key') && Schema::hasColumn('rate_reset_events', 'status')
                && ! Schema::hasColumn('rate_reset_events', 'classification_status') && ! Schema::hasColumn('rate_reset_events', 'plr_effective_date')) {
                Schema::table('rate_reset_events', fn (Blueprint $t) => $t->renameColumn('status', 'classification_status'));
            }
            $columns = [
                'detection_key' => fn (Blueprint $t) => $t->string('detection_key', 120)->nullable()->unique(),
                'account' => fn (Blueprint $t) => $t->string('account', 30)->nullable(),
                'reporting_period' => fn (Blueprint $t) => $t->string('reporting_period', 7)->nullable()->comment('The month the move is charged in: E-Banker charges the whole month at the new rate (section 3.1)'),
                'event_type' => fn (Blueprint $t) => $t->string('event_type', 20)->nullable()->comment('PLR_RESET or SPREAD_CHANGE: two events when both move on one date (spec v4 P5)'),
                'old_rate' => fn (Blueprint $t) => $t->decimal('old_rate', 10, 5)->nullable()->comment('Rate charged (loan book), percent'),
                'new_rate' => fn (Blueprint $t) => $t->decimal('new_rate', 10, 5)->nullable(),
                'old_spread' => fn (Blueprint $t) => $t->decimal('old_spread', 10, 5)->nullable()->comment('Rate charged less PLR, percentage points'),
                'new_spread' => fn (Blueprint $t) => $t->decimal('new_spread', 10, 5)->nullable(),
                'evidence' => fn (Blueprint $t) => $t->string('evidence', 500)->nullable(),
                'suggested_classification' => fn (Blueprint $t) => $t->string('suggested_classification', 20)->nullable()->comment('IN_CONTRACT or NEGOTIATED (O17)'),
                'classification' => fn (Blueprint $t) => $t->string('classification', 20)->nullable(),
                'classification_status' => fn (Blueprint $t) => $t->string('classification_status', 12)->default('DETECTED')->comment('Maker-checker: DETECTED, PROPOSED, APPROVED'),
                'reason' => fn (Blueprint $t) => $t->string('reason', 1000)->nullable(),
                'proposed_by' => fn (Blueprint $t) => $t->unsignedBigInteger('proposed_by')->nullable(),
                'proposed_at' => fn (Blueprint $t) => $t->timestamp('proposed_at')->nullable(),
                'approved_by' => fn (Blueprint $t) => $t->unsignedBigInteger('approved_by')->nullable(),
                'approver_label' => fn (Blueprint $t) => $t->string('approver_label')->nullable(),
                'approved_at' => fn (Blueprint $t) => $t->timestamp('approved_at')->nullable(),
                'close_reason' => fn (Blueprint $t) => $t->string('close_reason', 500)->nullable(),
                'restructure_event_id' => fn (Blueprint $t) => $t->unsignedBigInteger('restructure_event_id')->nullable(),
            ];
            foreach ($columns as $name => $add) {
                if (! Schema::hasColumn('rate_reset_events', $name)) {
                    Schema::table('rate_reset_events', fn (Blueprint $t) => $add($t));
                }
            }
            // the pipeline's one-reset-per-date rule widens to one event of each kind per date
            $indexes = array_keys(Schema::getConnection()->getDoctrineSchemaManager()->listTableIndexes('rate_reset_events'));
            if (in_array('uq_reset_contract_date', $indexes, true)) {
                Schema::table('rate_reset_events', fn (Blueprint $t) => $t->dropUnique('uq_reset_contract_date'));
            }
            if (! in_array('uq_reset_contract_date_type', $indexes, true) && Schema::hasColumn('rate_reset_events', 'plr_effective_date')) {
                Schema::table('rate_reset_events', fn (Blueprint $t) => $t->unique(['contract_id', 'reset_date', 'event_type'], 'uq_reset_contract_date_type'));
            }
        }

'''
s = s[:old_start] + new + s[old_end:]
old_down_start = s.index("        if (Schema::hasTable('rate_reset_events') && Schema::hasColumn('rate_reset_events', 'detection_key')) {")
old_down_end = s.index("        Schema::dropIfExists('contract_modifications');")
new_down = '''        if (Schema::hasTable('rate_reset_events') && Schema::hasColumn('rate_reset_events', 'detection_key')) {
            $indexes = array_keys(Schema::getConnection()->getDoctrineSchemaManager()->listTableIndexes('rate_reset_events'));
            if (in_array('uq_reset_contract_date_type', $indexes, true)) {
                Schema::table('rate_reset_events', fn (Blueprint $t) => $t->dropUnique('uq_reset_contract_date_type'));
            }
            Schema::table('rate_reset_events', fn (Blueprint $t) => $t->dropUnique(['detection_key']));
            foreach (['detection_key', 'account', 'reporting_period', 'event_type', 'old_rate', 'new_rate', 'old_spread', 'new_spread', 'evidence',
                'suggested_classification', 'classification', 'classification_status', 'reason', 'proposed_by', 'proposed_at', 'approved_by', 'approver_label', 'approved_at',
                'close_reason', 'restructure_event_id'] as $column) {
                if (Schema::hasColumn('rate_reset_events', $column)) {
                    Schema::table('rate_reset_events', fn (Blueprint $t) => $t->dropColumn($column));
                }
            }
            // the pipeline's own rule again, while its columns are there
            if (Schema::hasColumn('rate_reset_events', 'plr_effective_date')) {
                Schema::table('rate_reset_events', fn (Blueprint $t) => $t->unique(['contract_id', 'reset_date'], 'uq_reset_contract_date'));
            }
        }
'''
s = s[:old_down_start] + new_down + s[old_down_end:]
old_doc = """ *  - rate_reset_events (existing, never written until now): the writer's
 *    columns, so each move of a floating loan's rate is recorded with its
 *    evidence and passes maker-checker."""
assert old_doc in s
s = s.replace(old_doc, """ *  - rate_reset_events (shared with the reset pipeline of 2026_10_01_000000,
 *    which owns its `status`): the register's columns, so each move of a
 *    floating loan's rate carries its loan-book evidence and passes
 *    maker-checker in `classification_status`. Each column is added only where
 *    it is missing, so this and 2026_10_01_000000 run in either order.""")
open(p, 'w', encoding='utf-8', newline='\n').write(s)
print('ok')
