from ed import edit
p='app/Services/Eir/GovernanceService.php'
edit(p, r"""    /** @return list<string> */
    public static function keys(): array""", r"""    /**
     * The settings that are recorded and approved like every other but that no
     * engine reads yet (system audit M12; spec 4.2), each with what the engine
     * does instead. The Governance Centre shows them as "Recorded, not yet
     * applied", so no setting looks as if it governs a figure it does not.
     * A key leaves this list in the same change that wires it into an engine.
     *
     * @return array<string, string>
     */
    public static function notYetApplied(): array
    {
        return [
            'plr_mid_period' => 'Contractual interest always charges the whole month at the month-end rate, as E-Banker does (spec 3.1); the other options are not built.',
            'reset_trigger' => 'A floating loan\'s EIR is re-estimated each month with the rate E-Banker charged (D38); no reset date is chosen from this setting.',
            'rate_source_precedence' => 'The rate type is taken from the Interest Policy alone: P floating, every other code fixed (spec 7.2).',
            'margin_basis' => 'The spread is derived monthly as the rate charged less the PLR (SpreadDerivationService); this setting does not choose the source.',
            'cash_source' => 'The engine reads actual cash from the E-Banker ledger (D35, eir:load-ledger-cash) and the contractual schedule only for a month the ledger does not cover; the value recorded here does not change that.',
            'manual_policy_handling' => 'The engine treats Interest Policy M as fixed (spec 7.2), so a manual loan keeps its EIR. The recorded value is not applied: wiring it waits on open choice O4 and review item R14 (a manual change is not automatically a market reset).',
            'counter_reset_handling' => 'Cash comes from the ledger posting by posting (D35), so a falling Repayments counter is not read.',
            'contractual_record' => 'The engine reads the E-Banker system record in every case (D20).',
            'expected_cashflow_basis' => 'Schedules are built from the core dates and rate; an LOS schedule is not read.',
            'history_before_dec_2025' => 'Superseded by loan_book_build_method, which governs every month (D22).',
            'trueup_gl_account' => 'No EIR true-up journal is proposed yet (O10).',
            'auditor_export_format' => 'The auditor exports have one shape; this setting does not choose it.',
            'keyman_insurance_treatment' => 'Each fee line is classified by a person on Fee Classification; this setting does not pre-classify keyman insurance.',
            'nascomex_preference_shares' => 'The Nascomex entries (type 401) are kept out of the loan cash by the build (spec 3.3); this setting is not read.',
            'staging_rebuttal' => 'A rebuttal is a staging_thresholds row with its evidence, proposed and approved on Settings, Staging basis; this setting is not read.',
            'historic_materiality_assessment' => 'The historic materiality figure is not reproduced yet (O20).',
            'fee_reclass_journal' => 'No fee reclassification journal is proposed yet (O21).',
        ];
    }

    /** @return list<string> */
    public static function keys(): array""")
edit(p, r"""                'default' => $definition['default'],
                'in_force' => $inForce ? $this->presentRow($inForce, $inForce, $asOf) : null,""", r"""                'default' => $definition['default'],
                // recorded and approved, but read by no engine yet: shown as such (audit M12)
                'applied' => ! isset(self::notYetApplied()[$key]),
                'not_applied_note' => self::notYetApplied()[$key] ?? null,
                'in_force' => $inForce ? $this->presentRow($inForce, $inForce, $asOf) : null,""")
p='resources/js/Pages/Eir/Governance.vue'
edit(p, r"""                <span v-if="!s.in_force" class="shrink-0 rounded-full border border-red-300 bg-red-50 px-2 py-0.5 text-[10px] font-semibold text-red-700 dark:border-rose-400/40 dark:bg-rose-400/10 dark:text-rose-200">no value</span>""",
r"""                <span v-if="s.applied === false" class="shrink-0 rounded-full border border-gray-300 bg-gray-50 px-2 py-0.5 text-[10px] font-semibold text-gray-600 dark:border-slate-500 dark:bg-slate-700/60 dark:text-slate-300" :title="s.not_applied_note">recorded, not yet applied</span>
                <span v-else-if="!s.in_force" class="shrink-0 rounded-full border border-red-300 bg-red-50 px-2 py-0.5 text-[10px] font-semibold text-red-700 dark:border-rose-400/40 dark:bg-rose-400/10 dark:text-rose-200">no value</span>""")
edit(p, r"""        <p class="mt-3 text-sm leading-relaxed text-gray-600 dark:text-slate-300">{{ plain(detail.description) }}</p>
""", r"""        <p class="mt-3 text-sm leading-relaxed text-gray-600 dark:text-slate-300">{{ plain(detail.description) }}</p>
        <p v-if="detail.applied === false" class="mt-2 rounded-md border border-gray-300 bg-gray-50 px-3 py-2 text-sm text-gray-700 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200"><strong>Recorded, not yet applied.</strong> No engine reads this setting yet: changing it changes no figure. {{ detail.not_applied_note }}</p>
""")
