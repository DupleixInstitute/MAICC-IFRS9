<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EclScenarioAssumptionSeeder extends Seeder
{
    public function run(): void
    {
        foreach([
            ['BASE','Base',0.60,1.00,1.00,1.00],
            ['UPSIDE','Upside',0.20,0.75,0.90,0.98],
            ['DOWNSIDE','Downside',0.20,1.50,1.20,1.05],
        ] as [$code,$name,$weight,$pd,$lgd,$ead]) DB::table('ecl_scenario_assumptions')->updateOrInsert(
            ['scenario_code'=>$code,'effective_from'=>'2024-01-01'],
            ['name'=>$name,'weight'=>$weight,'pd_multiplier'=>$pd,'lgd_multiplier'=>$lgd,'ead_multiplier'=>$ead,
                'status'=>'APPROVED','rationale'=>'Controlled synthetic scenario for end-to-end IFRS 9 validation.','created_at'=>now(),'updated_at'=>now()]
        );

        // Matured Stage-3 synthetic facility: collections are expected after
        // contractual maturity, so a reviewed recovery horizon is required.
        if (DB::table('loan_books')->where('contract_id','900000000008')->exists()) {
            foreach ([['2026-04-30',30_000_000],['2026-10-31',33_500_000]] as [$date,$amount])
                DB::table('ecl_recovery_cashflows')->updateOrInsert(
                    ['contract_id'=>'900000000008','reporting_period'=>'2025-10','recovery_date'=>$date,'recovery_type'=>'COLLECTION'],
                    ['expected_recovery'=>$amount,'source'=>'REVIEWED_SYNTHETIC_ESTIMATE','status'=>'APPROVED',
                        'rationale'=>'Controlled Stage-3 recovery timing for end-to-end validation.','created_at'=>now(),'updated_at'=>now()]
                );
        }
    }
}
