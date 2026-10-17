<?php

namespace Tests\Feature\Eir;

use App\Models\User;
use App\Services\Decisions\DecisionsPackExporter;
use App\Services\Decisions\DecisionsPackReplayer;
use App\Services\Eir\LoanGrossAmountOverrideService;
use App\Services\Eir\LoanStagingBasisOverrideService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The per-loan staging and gross-amount overrides are people's decisions, so a fresh bootstrap
 * gets them only through the decisions pack: carried before the loan book is built, each with its
 * proposer, decider, dates and status, so the build applies the approved ones as the source did.
 */
class LoanOverridesDecisionsPackTest extends TestCase
{
    use RefreshDatabase;

    private User $maker;

    private User $checker;

    private string $folder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->maker = User::create(['name' => 'Finance analyst', 'email' => 'maker@example.com', 'password' => bcrypt('x')]);
        $this->checker = User::create(['name' => 'Chief Finance Officer', 'email' => 'checker@example.com', 'password' => bcrypt('x')]);
        $this->folder = storage_path('framework/testing/decisions-' . uniqid());
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->folder);
        parent::tearDown();
    }

    public function test_the_overrides_are_carried_with_their_people_dates_and_status(): void
    {
        $staging = new LoanStagingBasisOverrideService();
        $approved = $staging->propose('104430000087', '2025-12', 'Audited at 69 days.', $this->maker->id, 69);
        $staging->approve($approved, $this->checker->id);
        $withdrawn = $staging->propose('104430000002', '2025-12', 'Withdrawn later.', $this->maker->id, 0);
        $staging->approve($withdrawn, $this->checker->id);
        $staging->withdraw($withdrawn, $this->maker->id);
        $gross = new LoanGrossAmountOverrideService();
        $g = $gross->propose('104420000005', '2025-12', 'MUSECO audited at nil.', $this->maker->id, 0.0);
        $gross->approve($g, $this->checker->id);
        $sourceRow = DB::table('loan_staging_basis_overrides')->where('id', $approved)->first();

        $export = (new DecisionsPackExporter())->export($this->folder, 'overrides-test');
        $this->assertSame(2, $export['counts']['staging_overrides']);
        $this->assertSame(1, $export['counts']['gross_amount_overrides']);
        $this->assertSame(1, $export['manifest']['tie_out']['staging_overrides_approved']);

        // a fresh database: no overrides at all
        DB::table('loan_staging_basis_overrides')->delete();
        DB::table('loan_gross_amount_overrides')->delete();
        $replayer = new DecisionsPackReplayer($this->folder);
        $summary = $replayer->replayExclusions();

        $this->assertSame(['APPLIED' => 2], $summary['staging_overrides']);
        $this->assertSame(['APPLIED' => 1], $summary['gross_amount_overrides']);
        $active = $staging->active('2025-12');
        $this->assertSame(['104430000087'], array_map('strval', array_keys($active)));
        $this->assertSame(69, $active['104430000087']['override_days']);
        $this->assertSame('audited_workbook', $active['104430000087']['source']);
        $this->assertSame(0.0, $gross->active('2025-12')['104420000005']['override_amount']);
        $here = DB::table('loan_staging_basis_overrides')->where('contract_id', '104430000087')->first();
        $this->assertSame($this->maker->id, (int) $here->proposed_by);
        $this->assertSame($this->checker->id, (int) $here->approved_by);
        $this->assertSame((string) $sourceRow->created_at, (string) $here->created_at);
        $this->assertSame('WITHDRAWN', DB::table('loan_staging_basis_overrides')->where('contract_id', '104430000002')->value('status'));
        $this->assertSame(3, DB::table('audit_logs')->where('action', 'Carried By Decisions Pack')->whereIn('entity_type', ['loan_staging_basis_overrides', 'loan_gross_amount_overrides'])->count());
        $tie = $replayer->tieOut();
        $this->assertSame(['source' => 1, 'here' => 1, 'listed' => 0], $tie['staging overrides approved']);
        $this->assertSame(['source' => 1, 'here' => 1, 'listed' => 0], $tie['gross amount overrides approved']);

        // replayed again: already here, nothing doubled
        $again = (new DecisionsPackReplayer($this->folder))->replayExclusions();
        $this->assertSame(['ALREADY' => 2], $again['staging_overrides']);
        $this->assertSame(1, DB::table('loan_gross_amount_overrides')->count());
    }

    public function test_the_people_an_override_names_are_carried_before_it(): void
    {
        $gross = new LoanGrossAmountOverrideService();
        $g = $gross->propose('104420000005', '2025-12', 'MUSECO audited at nil.', $this->maker->id, 0.0);
        $gross->approve($g, $this->checker->id);
        (new DecisionsPackExporter())->export($this->folder, 'overrides-test');

        DB::table('loan_gross_amount_overrides')->delete();
        DB::table('users')->where('id', $this->checker->id)->delete();
        $summary = (new DecisionsPackReplayer($this->folder))->replayExclusions();

        $this->assertSame(['APPLIED' => 1], $summary['gross_amount_overrides']);
        $checkerHere = (int) DB::table('users')->where('email', 'checker@example.com')->value('id');
        $this->assertSame($checkerHere, (int) DB::table('loan_gross_amount_overrides')->value('approved_by'));
    }
}
