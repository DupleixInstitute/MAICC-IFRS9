<?php

namespace Tests\Feature\Eir;

use App\Models\ReferenceRate;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * "The rate in force on a date" is the one lookup every floating-rate
 * calculation depends on, so it is pinned down on its own: the latest row
 * on or before the date, null before the series starts, and one series
 * never answers for another.
 */
class ReferenceRateInForceTest extends TestCase
{
    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('reference_rate_series', function (Blueprint $t) {
            $t->increments('id');
            $t->string('index_code', 20)->default('PLR');
            $t->date('effective_date');
            $t->decimal('rate', 8, 5);
            $t->string('source_row')->nullable();
            $t->string('as_delivered')->nullable();
            $t->string('interpretation')->nullable();
            $t->unsignedBigInteger('import_id')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
        });

        foreach ([
            ['PLR', '2024-12-09', 25.3],
            ['PLR', '2025-04-14', 25.1],
            ['PLR', '2025-05-27', 25.2],
            ['PLR', '2026-02-04', 24.7],
            ['TBILL', '2025-01-01', 16.0],
        ] as [$index, $date, $rate]) {
            ReferenceRate::create(['index_code' => $index, 'effective_date' => $date, 'rate' => $rate]);
        }
    }

    public function test_the_rate_in_force_is_the_latest_row_on_or_before_the_date(): void
    {
        $this->assertEqualsWithDelta(25.3, ReferenceRate::inForce('PLR', Carbon::parse('2024-12-31'))->rate, 0.000001);
        $this->assertEqualsWithDelta(25.3, ReferenceRate::inForce('PLR', Carbon::parse('2025-04-13'))->rate, 0.000001);
        // The effective date itself carries the new rate.
        $this->assertEqualsWithDelta(25.1, ReferenceRate::inForce('PLR', Carbon::parse('2025-04-14'))->rate, 0.000001);
        $this->assertEqualsWithDelta(25.2, ReferenceRate::inForce('PLR', Carbon::parse('2025-12-31'))->rate, 0.000001);
        // After the last change the last rate stays in force.
        $this->assertEqualsWithDelta(24.7, ReferenceRate::inForce('PLR', Carbon::parse('2030-01-01'))->rate, 0.000001);
    }

    public function test_before_the_series_starts_there_is_no_rate_not_a_zero(): void
    {
        $this->assertNull(ReferenceRate::inForce('PLR', Carbon::parse('2024-12-08')));
    }

    public function test_one_index_never_answers_for_another(): void
    {
        $this->assertEqualsWithDelta(16.0, ReferenceRate::inForce('tbill', Carbon::parse('2025-06-30'))->rate, 0.000001);
        $this->assertNull(ReferenceRate::inForce('MPR', Carbon::parse('2025-06-30')));
    }
}
