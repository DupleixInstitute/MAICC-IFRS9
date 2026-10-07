<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Extend scenarios table ────────────────────────────────────────────
        Schema::table('scenarios', function (Blueprint $table): void {
            // Risk family classification (climate, esg, sovereign, cyber, systemic, etc.)
            $table->string('scenario_family', 30)->default('macro')->after('scenario_type');

            // Multi-risk category tags  ['credit', 'climate', 'liquidity']
            $table->json('risk_categories')->nullable()->after('scenario_family');

            // Severity index 1 (mild) – 10 (extreme)
            $table->unsignedTinyInteger('severity_index')->default(5)->after('horizon_years');

            // Extended parameters as JSON (climate indices, ESG flags, cyber amounts, etc.)
            $table->json('extended_params')->nullable()->after('severity_index');

            // Segment scope filter — which segments does this scenario target?
            $table->json('segment_filter_json')->nullable()->after('extended_params');

            // Management actions offset list [{type, description, amount, year, target}]
            $table->json('management_actions')->nullable()->after('segment_filter_json');

            // Combined scenario linkage
            $table->json('combination_scenario_ids')->nullable()->after('management_actions');
            $table->string('combination_mode', 20)->nullable()->after('combination_scenario_ids');

            // Reverse stress configuration
            $table->boolean('is_reverse_stress')->default(false)->after('combination_mode');
            $table->string('solver_target', 60)->nullable()->after('is_reverse_stress');
            $table->decimal('solver_threshold', 8, 4)->nullable()->after('solver_target');
        });

        // ── Extend scenario_shocks table ─────────────────────────────────────
        Schema::table('scenario_shocks', function (Blueprint $table): void {
            // Which risk family generated this shock (climate, esg, cyber, sovereign, etc.)
            $table->string('shock_family', 30)->nullable()->after('scenario_id');

            // Target type clarification (macro_driver, sector, geography, segment, etc.)
            $table->string('target_type', 40)->nullable()->after('shock_target');

            // Which segments this shock applies to (null = all)
            $table->json('segment_filter_json')->nullable()->after('target_type');

            // Transmission chain description [trigger, intermediate, outcome]
            $table->json('transmission_path')->nullable()->after('segment_filter_json');

            // Sensitivity multiplier (overrides default elasticity)
            $table->decimal('elasticity', 8, 4)->nullable()->after('transmission_path');

            // Delayed onset (0 = immediate, 1 = next year, etc.)
            $table->unsignedTinyInteger('lag_years')->default(0)->after('elasticity');

            // Floor and cap on resulting value after shock
            $table->decimal('floor_value', 14, 4)->nullable()->after('lag_years');
            $table->decimal('cap_value', 14, 4)->nullable()->after('floor_value');
        });

        // ── Scenario parameters table (extended risk-specific values) ────────
        Schema::create('scenario_parameters', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('scenario_id');
            $table->string('parameter_key', 80);
            $table->string('parameter_label', 120);
            $table->decimal('parameter_value', 18, 6);
            $table->string('unit', 30)->nullable();       // %, days, USD, index
            $table->string('category', 30)->nullable();   // climate, esg, cyber, sovereign, etc.
            $table->timestamps();

            $table->foreign('scenario_id')
                ->references('id')
                ->on('scenarios')
                ->cascadeOnDelete();
            $table->index('scenario_id');
            $table->unique(['scenario_id', 'parameter_key']);
        });

        // ── Transmission rule library ─────────────────────────────────────────
        Schema::create('scenario_transmission_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('rule_code', 80)->unique();
            $table->string('name', 160);
            $table->string('family', 30);                 // climate, esg, sovereign, cyber, systemic
            $table->string('trigger', 80);                // e.g. "drought"
            $table->string('intermediate', 80)->nullable();
            $table->string('outcome', 80);                // e.g. "npl_increase"
            $table->string('shock_target', 80);           // CoA target key
            $table->decimal('default_elasticity', 8, 4)->default(1.0);
            $table->string('shock_type', 20)->default('pct_change');
            $table->string('direction', 10)->default('negative'); // positive / negative
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ── Seed transmission rule library ───────────────────────────────────
        $this->seedTransmissionRules();
    }

    public function down(): void
    {
        Schema::dropIfExists('scenario_transmission_rules');
        Schema::dropIfExists('scenario_parameters');

        Schema::table('scenario_shocks', function (Blueprint $table): void {
            $table->dropColumn([
                'shock_family', 'target_type', 'segment_filter_json',
                'transmission_path', 'elasticity', 'lag_years', 'floor_value', 'cap_value',
            ]);
        });

        Schema::table('scenarios', function (Blueprint $table): void {
            $table->dropColumn([
                'scenario_family', 'risk_categories', 'severity_index', 'extended_params',
                'segment_filter_json', 'management_actions', 'combination_scenario_ids',
                'combination_mode', 'is_reverse_stress', 'solver_target', 'solver_threshold',
            ]);
        });
    }

    private function seedTransmissionRules(): void
    {
        $rules = [
            // ── Climate Physical ──────────────────────────────────────────────
            ['rule_code' => 'CLIMATE_DROUGHT_GDP',       'name' => 'Drought → GDP Contraction',              'family' => 'climate',      'trigger' => 'drought',           'intermediate' => 'agriculture_output_drop', 'outcome' => 'gdp_decline',             'shock_target' => 'GDP_GROWTH',          'default_elasticity' => 2.5,  'shock_type' => 'pct_change', 'direction' => 'negative'],
            ['rule_code' => 'CLIMATE_DROUGHT_NPL',       'name' => 'Drought → Agri Loan Defaults',           'family' => 'climate',      'trigger' => 'drought',           'intermediate' => 'farm_income_collapse',     'outcome' => 'npl_increase',            'shock_target' => 'NPL_RATIO',           'default_elasticity' => 3.0,  'shock_type' => 'absolute',   'direction' => 'negative'],
            ['rule_code' => 'CLIMATE_FLOOD_COLLATERAL',  'name' => 'Flood → Collateral Value Loss',          'family' => 'climate',      'trigger' => 'flood',             'intermediate' => 'property_damage',          'outcome' => 'collateral_decline',      'shock_target' => 'LGD_SECURED',         'default_elasticity' => 1.8,  'shock_type' => 'pct_change', 'direction' => 'negative'],
            ['rule_code' => 'CLIMATE_FLOOD_CREDIT',      'name' => 'Flood → Credit Deterioration',           'family' => 'climate',      'trigger' => 'flood',             'intermediate' => 'business_disruption',      'outcome' => 'credit_quality_drop',     'shock_target' => 'NPL_RATIO',           'default_elasticity' => 2.2,  'shock_type' => 'absolute',   'direction' => 'negative'],

            // ── Climate Transition ────────────────────────────────────────────
            ['rule_code' => 'TRANSITION_CARBON_TAX_MARGIN', 'name' => 'Carbon Tax → Sector Margin Compression', 'family' => 'climate',   'trigger' => 'carbon_tax',        'intermediate' => 'operating_cost_increase',  'outcome' => 'profitability_decline',   'shock_target' => 'NON_INTEREST_INCOME', 'default_elasticity' => 1.5,  'shock_type' => 'pct_change', 'direction' => 'negative'],
            ['rule_code' => 'TRANSITION_CARBON_CREDIT',     'name' => 'Carbon Tax → Credit Risk in High-Emission Sectors', 'family' => 'climate', 'trigger' => 'carbon_tax', 'intermediate' => 'sector_profitability_drop', 'outcome' => 'credit_risk_increase',  'shock_target' => 'NPL_RATIO',           'default_elasticity' => 1.2,  'shock_type' => 'absolute',   'direction' => 'negative'],
            ['rule_code' => 'TRANSITION_ENERGY_REVAL',      'name' => 'Energy Transition → Asset Stranding',    'family' => 'climate',   'trigger' => 'energy_transition', 'intermediate' => 'fossil_asset_writedown',   'outcome' => 'capital_erosion',         'shock_target' => 'INDUSTRY_CAR',        'default_elasticity' => 1.0,  'shock_type' => 'pct_change', 'direction' => 'negative'],

            // ── ESG Risk ──────────────────────────────────────────────────────
            ['rule_code' => 'ESG_GOV_FAILURE_FRAUD',    'name' => 'Governance Failure → Fraud Losses',      'family' => 'esg',          'trigger' => 'governance_failure','intermediate' => 'control_breakdown',        'outcome' => 'operational_loss',        'shock_target' => 'OPERATIONAL_EXPENSES','default_elasticity' => 1.5,  'shock_type' => 'absolute',   'direction' => 'negative'],
            ['rule_code' => 'ESG_GOV_DEPOSITS',         'name' => 'Governance Scandal → Deposit Flight',    'family' => 'esg',          'trigger' => 'governance_failure','intermediate' => 'reputation_damage',        'outcome' => 'deposit_runoff',          'shock_target' => 'RETAIL_DEPOSITS',     'default_elasticity' => 2.0,  'shock_type' => 'pct_change', 'direction' => 'negative'],
            ['rule_code' => 'ESG_SOCIAL_UNREST',        'name' => 'Social Unrest → Branch/Business Disruption', 'family' => 'esg',     'trigger' => 'social_unrest',     'intermediate' => 'operations_disruption',    'outcome' => 'income_reduction',        'shock_target' => 'NON_INTEREST_INCOME', 'default_elasticity' => 1.8,  'shock_type' => 'pct_change', 'direction' => 'negative'],
            ['rule_code' => 'ESG_ENV_FINES',            'name' => 'Environmental Fines → Capital Drain',    'family' => 'esg',          'trigger' => 'environmental_fine','intermediate' => null,                       'outcome' => 'capital_erosion',         'shock_target' => 'OPERATIONAL_EXPENSES','default_elasticity' => 1.0,  'shock_type' => 'absolute',   'direction' => 'negative'],

            // ── Sovereign Risk ────────────────────────────────────────────────
            ['rule_code' => 'SOVEREIGN_HAIRCUT_CAPITAL', 'name' => 'Bond Haircut → Capital Reduction',      'family' => 'sovereign',    'trigger' => 'sovereign_default', 'intermediate' => 'bond_writedown',           'outcome' => 'capital_erosion',         'shock_target' => 'INDUSTRY_CAR',        'default_elasticity' => 1.0,  'shock_type' => 'pct_change', 'direction' => 'negative'],
            ['rule_code' => 'SOVEREIGN_HAIRCUT_LIQUIDITY','name' => 'Bond Haircut → Liquidity Pressure',    'family' => 'sovereign',    'trigger' => 'sovereign_default', 'intermediate' => 'hqla_value_drop',          'outcome' => 'lcr_decline',             'shock_target' => 'LCR',                 'default_elasticity' => 1.2,  'shock_type' => 'pct_change', 'direction' => 'negative'],
            ['rule_code' => 'SOVEREIGN_SPREAD_FUNDING',  'name' => 'Spread Widening → Funding Cost Rise',   'family' => 'sovereign',    'trigger' => 'sovereign_downgrade','intermediate' => 'market_risk_premium',    'outcome' => 'funding_cost_increase',   'shock_target' => 'INTERBANK_RATE',      'default_elasticity' => 0.8,  'shock_type' => 'absolute',   'direction' => 'negative'],

            // ── Cyber Risk ────────────────────────────────────────────────────
            ['rule_code' => 'CYBER_INCOME_LOSS',        'name' => 'Cyber Attack → Operational Income Loss', 'family' => 'cyber',        'trigger' => 'cyber_attack',      'intermediate' => 'system_downtime',          'outcome' => 'income_disruption',       'shock_target' => 'NON_INTEREST_INCOME', 'default_elasticity' => 1.5,  'shock_type' => 'pct_change', 'direction' => 'negative'],
            ['rule_code' => 'CYBER_RECOVERY_COST',      'name' => 'Cyber Attack → Recovery & Remediation',  'family' => 'cyber',        'trigger' => 'cyber_attack',      'intermediate' => 'recovery_operations',      'outcome' => 'cost_spike',              'shock_target' => 'OPERATIONAL_EXPENSES','default_elasticity' => 1.0,  'shock_type' => 'absolute',   'direction' => 'negative'],
            ['rule_code' => 'CYBER_DEPOSIT_FLIGHT',     'name' => 'Data Breach → Deposit Outflows',         'family' => 'cyber',        'trigger' => 'data_breach',       'intermediate' => 'customer_trust_loss',      'outcome' => 'deposit_runoff',          'shock_target' => 'RETAIL_DEPOSITS',     'default_elasticity' => 2.5,  'shock_type' => 'pct_change', 'direction' => 'negative'],
            ['rule_code' => 'CYBER_RWA_INCREASE',       'name' => 'Cyber Event → Operational RWA Surge',    'family' => 'cyber',        'trigger' => 'cyber_attack',      'intermediate' => 'risk_profile_change',      'outcome' => 'rwa_increase',            'shock_target' => 'OPERATIONAL_RISK_RWA','default_elasticity' => 1.2,  'shock_type' => 'pct_change', 'direction' => 'negative'],

            // ── Systemic Risk ─────────────────────────────────────────────────
            ['rule_code' => 'SYSTEMIC_INTERBANK_FREEZE','name' => 'Interbank Freeze → Funding Cost Spike',  'family' => 'systemic',     'trigger' => 'interbank_freeze',  'intermediate' => 'market_access_lost',       'outcome' => 'funding_cost_spike',      'shock_target' => 'INTERBANK_RATE',      'default_elasticity' => 3.0,  'shock_type' => 'absolute',   'direction' => 'negative'],
            ['rule_code' => 'SYSTEMIC_CONTAGION_NPL',   'name' => 'Banking Crisis → Contagion Credit Loss', 'family' => 'systemic',     'trigger' => 'banking_crisis',    'intermediate' => 'confidence_collapse',      'outcome' => 'npl_contagion',           'shock_target' => 'NPL_RATIO',           'default_elasticity' => 2.0,  'shock_type' => 'absolute',   'direction' => 'negative'],
            ['rule_code' => 'SYSTEMIC_DEPOSIT_RUNOFF',  'name' => 'Systemic Crisis → Mass Deposit Outflow', 'family' => 'systemic',     'trigger' => 'banking_crisis',    'intermediate' => 'deposit_confidence_loss',  'outcome' => 'deposit_runoff',          'shock_target' => 'RETAIL_DEPOSITS',     'default_elasticity' => 2.5,  'shock_type' => 'pct_change', 'direction' => 'negative'],
            ['rule_code' => 'SYSTEMIC_LIQUIDITY_DROP',  'name' => 'Market Freeze → Liquidity Evaporation',  'family' => 'systemic',     'trigger' => 'market_freeze',     'intermediate' => 'asset_marketability_loss', 'outcome' => 'lcr_collapse',            'shock_target' => 'LCR',                 'default_elasticity' => 1.8,  'shock_type' => 'pct_change', 'direction' => 'negative'],

            // ── Concentration Risk ────────────────────────────────────────────
            ['rule_code' => 'CONC_TOP_DEPOSITOR',       'name' => 'Top Depositor Withdrawal → LCR Breach',  'family' => 'concentration', 'trigger' => 'top_depositor_exit','intermediate' => 'funding_gap',             'outcome' => 'liquidity_pressure',      'shock_target' => 'WHOLESALE_FUNDING',   'default_elasticity' => 1.0,  'shock_type' => 'pct_change', 'direction' => 'negative'],
            ['rule_code' => 'CONC_SECTOR_COLLAPSE',     'name' => 'Sector Concentration → Cluster Default', 'family' => 'concentration', 'trigger' => 'sector_collapse',   'intermediate' => 'correlated_defaults',     'outcome' => 'npl_spike',               'shock_target' => 'NPL_RATIO',           'default_elasticity' => 2.0,  'shock_type' => 'absolute',   'direction' => 'negative'],
            ['rule_code' => 'CONC_GEO_SHOCK',          'name' => 'Geographic Shock → Regional Credit Loss',  'family' => 'concentration', 'trigger' => 'regional_disaster', 'intermediate' => 'geographic_exposure',     'outcome' => 'credit_loss',             'shock_target' => 'NPL_RATIO',           'default_elasticity' => 1.5,  'shock_type' => 'absolute',   'direction' => 'negative'],

            // ── Profitability Stress ──────────────────────────────────────────
            ['rule_code' => 'PROFIT_MARGIN_COMPRESS',   'name' => 'Margin Compression → NIM Decline',       'family' => 'profitability', 'trigger' => 'rate_compression',  'intermediate' => 'spread_squeeze',           'outcome' => 'net_interest_income_drop','shock_target' => 'BOZ_MPR',             'default_elasticity' => 0.6,  'shock_type' => 'pct_change', 'direction' => 'negative'],
            ['rule_code' => 'PROFIT_COST_SPIKE',        'name' => 'Cost Shock → Efficiency Ratio Worsening', 'family' => 'profitability', 'trigger' => 'cost_increase',     'intermediate' => 'expense_growth',           'outcome' => 'cost_income_deterioration','shock_target' => 'OPERATIONAL_EXPENSES','default_elasticity' => 1.0,  'shock_type' => 'pct_change', 'direction' => 'negative'],
            ['rule_code' => 'PROFIT_FEE_INCOME_DROP',   'name' => 'Fee Income Drop → Total Income Decline',  'family' => 'profitability', 'trigger' => 'fee_compression',   'intermediate' => 'non_interest_squeeze',     'outcome' => 'income_decline',          'shock_target' => 'NON_INTEREST_INCOME', 'default_elasticity' => 1.0,  'shock_type' => 'pct_change', 'direction' => 'negative'],
        ];

        foreach ($rules as $rule) {
            \DB::table('scenario_transmission_rules')->updateOrInsert(
                ['rule_code' => $rule['rule_code']],
                array_merge($rule, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }
};
