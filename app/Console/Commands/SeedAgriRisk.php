<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Make collateral / LGD and concentration reflect smallholder-agri reality:
 *
 *  - credit_enhancement: input loans are rarely backed by real estate. They
 *    are secured by off-take / contract-farming agreements, warehouse
 *    receipts, group / cooperative guarantees or AIP backing; equipment and
 *    irrigation are asset-backed; industrial is conventional. LGD is then
 *    derived from the enhancement's typical recovery, NOT a real-estate
 *    haircut.
 *
 *  - cooperative: smallholder loans are economically tied to a cooperative
 *    or anchor buyer, so default is correlated. Tagging the linkage lets the
 *    concentration / contagion report measure it.
 *
 * Deterministic (CRC32 of contract_id) so it is idempotent. After re-pricing
 * LGD it refreshes the ECL store via ifrs9:generate-2025 so every report
 * stays reconciled. Demo enrichment; reversible via the DB backup.
 */
class SeedAgriRisk extends Command
{
    protected $signature = 'ifrs9:seed-agri-risk {--dry-run}';

    protected $description = 'Model agri credit enhancements (off-take/warehouse/group/AIP), LGD and cooperative linkage.';

    /** Enhancement => LGD (1 − typical recovery). */
    private const LGD = [
        'Off-take / Contract farming' => 0.45,
        'Warehouse receipt'           => 0.35,
        'Group / Cooperative guarantee' => 0.50,
        'AIP backing'                 => 0.40,
        'Asset-backed'                => 0.30,
        'Conventional'                => 0.45,
        'Unsecured'                   => 0.80,
    ];

    public function handle(): int
    {
        if ($this->option('dry-run')) {
            $this->info('[dry-run] Would assign credit_enhancement, cooperative and re-price LGD, then refresh ECL.');
            $this->line('Enhancement LGDs: ' . json_encode(self::LGD));
            return self::SUCCESS;
        }

        // 1. Credit enhancement by portfolio, with a deterministic mix for
        //    smallholder input / working-capital loans.
        DB::statement("
            UPDATE loan_books lb
            LEFT JOIN loan_portfolios p ON p.id = lb.loan_portfolio_id
            SET lb.credit_enhancement = CASE
                WHEN p.name = 'Farm Equipment' THEN 'Asset-backed'
                WHEN p.name = 'Irrigation'     THEN 'Asset-backed'
                WHEN p.name = 'Industrial'     THEN 'Conventional'
                WHEN p.name = 'Agri-Inputs' THEN ELT(1 + (CRC32(COALESCE(lb.contract_id, lb.id)) % 3),
                        'Off-take / Contract farming', 'Group / Cooperative guarantee', 'AIP backing')
                WHEN p.name = 'Agri Working Capital' THEN ELT(1 + (CRC32(COALESCE(lb.contract_id, lb.id)) % 3),
                        'Off-take / Contract farming', 'Warehouse receipt', 'Group / Cooperative guarantee')
                ELSE 'Unsecured' END
        ");

        // 2. Re-price collection_lgd from the enhancement's recovery.
        $case = 'CASE lb.credit_enhancement';
        foreach (self::LGD as $type => $lgd) {
            $case .= " WHEN '" . addslashes($type) . "' THEN $lgd";
        }
        $case .= ' ELSE 0.60 END';
        DB::statement("UPDATE loan_books lb SET lb.collection_lgd = $case");

        // 3. Cooperative / anchor linkage (agri only; others lend direct).
        $coops = [
            'Lilongwe Smallholder Coop', 'Kasungu Maize Union', 'Mzuzu Growers Coop',
            'Shire Valley Irrigation Coop', 'Central Soya Anchor', 'Tobacco Anchor Buyer',
            'Balaka Legumes Coop', 'Mchinji Groundnut Union',
        ];
        $elt = "ELT(1 + (CRC32(COALESCE(lb.contract_id, lb.id)) % " . count($coops) . "), '"
            . implode("','", array_map('addslashes', $coops)) . "')";
        DB::statement("
            UPDATE loan_books lb
            LEFT JOIN loan_portfolios p ON p.id = lb.loan_portfolio_id
            SET lb.cooperative = CASE
                WHEN p.name IN ('Agri-Inputs','Agri Working Capital') THEN $elt
                ELSE 'Direct (no cooperative)' END
        ");

        // 4. Keep loan-level ECL consistent with the new LGD …
        DB::statement("
            UPDATE loan_books
            SET ecl_value = COALESCE(pd_post_fli, pd_prefli, 0)
                          * COALESCE(collection_lgd, 0)
                          * COALESCE(carrying_amount, 0)
        ");

        $this->info('Agri credit enhancements, cooperative linkage and LGD applied.');

        // 5. … and refresh the aggregated ECL store so all reports reconcile.
        $this->line('Refreshing ECL store (ifrs9:generate-2025)…');
        Artisan::call('ifrs9:generate-2025');
        $this->line(trim(Artisan::output()));

        return self::SUCCESS;
    }
}
