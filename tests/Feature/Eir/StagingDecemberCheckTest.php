<?php

namespace Tests\Feature\Eir;

use Database\Seeders\GovernanceSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Staging Module Specification v1, acceptance 4: December 2025 staged under the
 * 31 December 2025 policy, facility by facility against the December 2025 ECL
 * workbook, every difference with its cause.
 *
 * The workbook (four facilities, the last a catch-all line) against the book:
 *   Alpha Ltd      1,000,000.00   1-30 Days   Stage 1   book: Alpha Limited, current: same
 *   Bravo Farms      500,000.00   1-30 Days   Stage 1   book: BRAVO FARMING CO, 271+ bucket (matched on the balance): different bucket, Stage 3
 *   Charlie            2,000.00   >181 Days   Stage 3   book: Charlie, 2,500,000.00, 181+ bucket: different balance, same stage
 *   Closed lines         777.77   1-30 Days   Stage 1   no loan: in the workbook only
 *   (book only)  Delta, 300,000.00, current: in the system's book only
 */
class StagingDecemberCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_difference_from_the_workbook_is_listed_with_its_cause(): void
    {
        (new GovernanceSettingsSeeder())->run();
        $base = ['reporting_period' => '2025-12', 'reporting_year' => 2025, 'reporting_month' => 12, 'customer_id' => '1', 'external_identity_id' => 'TBA', 'product_code' => '1050101', 'tenor' => 36];
        foreach ([
            ['contract_id' => 'A1', 'customer_name' => 'Alpha Limited', 'carrying_amount' => 1000000],
            ['contract_id' => 'B1', 'customer_name' => 'BRAVO FARMING CO', 'carrying_amount' => 500000, 'arrears_271_to_360' => 400000],
            ['contract_id' => 'C1', 'customer_name' => 'Charlie', 'carrying_amount' => 2500000, 'arrears_180_to_270' => 100000],
            ['contract_id' => 'D1', 'customer_name' => 'Delta', 'carrying_amount' => 300000],
        ] as $row) {
            DB::table('loan_books')->insert($row + $base);
        }
        $csv = storage_path('app/test-december-workbook.csv');
        file_put_contents($csv, "# test workbook\ncustomer_name,carrying_amount,age_bracket_revised,workbook_stage\nAlpha Ltd,1000000,1-30 Days,1\nBravo Farms,500000,1-30 Days,1\nCharlie,2000,>181 Days,3\nClosed lines,777.77,1-30 Days,1\n");
        $out = storage_path('app/test-december-check.csv');

        $this->assertSame(0, Artisan::call('staging:december-check', ['--workbook' => $csv, '--out' => $out]));
        $rows = collect(array_map('str_getcsv', array_slice(file($out, FILE_IGNORE_NEW_LINES), 1)))->keyBy(fn ($r) => $r[0] !== '' ? $r[0] : $r[5]);
        $this->assertCount(5, $rows, 'four workbook lines and one loan the workbook does not have');
        $this->assertSame(['same', 'name and balance'], [$rows['Alpha Ltd'][11], $rows['Alpha Ltd'][12]]);
        $this->assertSame(['different arrears bucket', '3', 'balance (names differ)'], [$rows['Bravo Farms'][11], $rows['Bravo Farms'][9], $rows['Bravo Farms'][12]]);
        $this->assertSame(['different balance', 'yes'], [$rows['Charlie'][11], $rows['Charlie'][10]]);
        $this->assertStringStartsWith('in the workbook only', $rows['Closed lines'][11]);
        $this->assertStringStartsWith('in the system\'s book only', $rows['Delta'][11]);
        // nothing booked is changed
        $this->assertSame(0, DB::table('loan_books')->whereNotNull('ifrs9stage_post_qualitative')->count());
        @unlink($csv); @unlink($out);
    }
}
