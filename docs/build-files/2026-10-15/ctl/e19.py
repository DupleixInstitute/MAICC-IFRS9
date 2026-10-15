from ed import edit
p='app/Services/Ebanker/LoanBookBuildService.php'
edit(p, r"""    /** The method in force on a month-end: the Governance Centre setting's letter (section 6.2). */
    public function methodInForce(string $monthEnd): string
    {
        try {
            $value = $this->governance->get('loan_book_build_method', CarbonImmutable::parse($monthEnd));
        } catch (Throwable) {
            $value = 'B';
        }
        $letter = strtoupper(substr(trim($value), 0, 1));

        return isset(self::METHODS[$letter]) ? $letter : 'B';
    }""", r"""    /**
     * The method in force on a month-end: the Governance Centre setting's
     * letter (section 6.2). No method is assumed: with no approved value the
     * build stops (GovernanceSettingMissingException), and a value the build
     * does not know is refused with its name (it used to fall back to B).
     */
    public function methodInForce(string $monthEnd): string
    {
        $value = $this->governance->get('loan_book_build_method', CarbonImmutable::parse($monthEnd));
        $letter = strtoupper(substr(trim($value), 0, 1));
        if (! isset(self::METHODS[$letter])) {
            throw new RuntimeException("The governed loan book build method '{$value}' in force at {$monthEnd} is not one the build knows (A, B or C).");
        }

        return $letter;
    }""")
edit(p, r"""        $basis = 'Oldest';
        try {
            $basis = $this->governance->get('dpd_basis', CarbonImmutable::parse($monthEnd));
        } catch (Throwable) {
        }
        if (str_starts_with($basis, 'Oldest') && $odDate !== null && $inArrears) {""", r"""        // the governed basis, never assumed: with no approved value the build stops (decision D31)
        $basis = $this->governance->get('dpd_basis', CarbonImmutable::parse($monthEnd));
        if (! str_starts_with($basis, 'Oldest') && ! str_starts_with($basis, 'Bucket')) {
            throw new RuntimeException("The governed days-past-due basis '{$basis}' is not one the build knows.");
        }
        if (str_starts_with($basis, 'Oldest') && $odDate !== null && $inArrears) {""")
p='tests/Feature/Ebanker/LoanBookBuildServiceTest.php'
edit(p, r"""        (new EbankerQuerySeeder())->run();
        DB::table('users')->insert([['id' => 1, 'name' => 'Maker', 'created_at' => now(), 'updated_at' => now()], ['id' => 2, 'name' => 'Checker', 'created_at' => now(), 'updated_at' => now()]]);""", r"""        (new EbankerQuerySeeder())->run();
        // the build reads its method and the days-past-due basis from the Governance Centre with no value in code
        Schema::create('governance_settings', function (Blueprint $t) { $t->increments('id'); $t->string('key', 60); $t->string('value'); $t->text('options'); $t->string('label'); $t->text('description'); $t->string('effective_from'); $t->integer('set_by')->nullable(); $t->integer('approved_by')->nullable(); $t->string('approved_at')->nullable(); $t->string('reason', 500)->nullable(); $t->string('status', 20)->default('PROPOSED'); $t->timestamps(); });
        foreach (['loan_book_build_method', 'dpd_basis'] as $key) {
            $def = GovernanceService::catalogue()[$key];
            DB::table('governance_settings')->insert(['key' => $key, 'value' => $def['default'], 'options' => json_encode($def['options']), 'label' => $def['label'], 'description' => '', 'effective_from' => '1900-01-01', 'status' => 'APPROVED', 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('users')->insert([['id' => 1, 'name' => 'Maker', 'created_at' => now(), 'updated_at' => now()], ['id' => 2, 'name' => 'Checker', 'created_at' => now(), 'updated_at' => now()]]);""")
