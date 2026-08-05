<?php

namespace Database\Seeders;

use App\Models\EirAccountingRule;
use Illuminate\Database\Seeder;

class EirAccountingRuleSeeder extends Seeder
{
    public function run(): void
    {
        $rules = [
            ['name' => 'Arrangement fee linked to origination', 'fee_type' => 'arrangement', 'description_contains' => null, 'proposed_integral' => true, 'rationale' => 'Draft: fees that are integral to originating the specific facility are included in EIR.', 'priority' => 10],
            ['name' => 'Direct origination legal cost', 'fee_type' => 'legal', 'description_contains' => 'origination', 'proposed_integral' => true, 'rationale' => 'Draft: include only legal costs directly attributable to originating the specific financial asset.', 'priority' => 20],
            ['name' => 'Annual monitoring fee', 'fee_type' => 'other', 'description_contains' => 'monitoring', 'proposed_integral' => false, 'rationale' => 'Draft: an ongoing service is generally outside the original EIR.', 'priority' => 30],
            ['name' => 'Anniversary fee', 'fee_type' => 'other', 'description_contains' => 'anniversary', 'proposed_integral' => false, 'rationale' => 'Draft: a recurring service fee is generally outside the original EIR.', 'priority' => 31],
            ['name' => 'Penalty or default fee', 'fee_type' => 'default', 'description_contains' => null, 'proposed_integral' => false, 'rationale' => 'Draft: default-related charges are not part of the cash flows used to establish the original EIR.', 'priority' => 40],
            ['name' => 'General legal expenditure', 'fee_type' => 'legal', 'description_contains' => 'general', 'proposed_integral' => false, 'rationale' => 'Draft: general legal expenditure is not directly attributable to a specific loan origination.', 'priority' => 50],
        ];

        foreach ($rules as $rule) {
            EirAccountingRule::updateOrCreate(
                ['name' => $rule['name']],
                $rule + ['active' => true, 'approved_by' => null, 'approved_at' => null]
            );
        }
    }
}
