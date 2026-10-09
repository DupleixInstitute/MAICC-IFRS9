<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * MAIIC's transition profile M101: the measured IFRS 9 stage (the
 * post-qualitative one, so the instalment trigger, the SICR flag and the cure
 * hold count; TransitionMatrixService falls back to the calculated stage and
 * then the DPD stage where a row carries none) as the grade at the start and
 * the end of the window, balances as the aggregation, and "Paid" as the end
 * state of a loan that left the book settled. Until the system audit of
 * 9 October 2026 (finding M10) the profile graded on the DPD stage alone, so
 * a loan in default by the instalment trigger was not a default to the PD;
 * an installed M101 is moved to the measured stage here. Idempotent on
 * profile_code; needed by the PD engine (spec v4 section 6.10.1, step 4) on
 * a clean install.
 */
class MaiicTransitionProfileSeeder extends Seeder
{
    public const GRADING_COLUMN = 'ifrs9stage_post_qualitative';

    public function run(): void
    {
        $existing = DB::table('transition_profile_definitions')->where('profile_code', 'M101')->first();
        if ($existing && ($existing->start_grading_col !== self::GRADING_COLUMN || $existing->end_grading_col !== self::GRADING_COLUMN)) {
            DB::table('transition_profile_definitions')->where('id', $existing->id)->update(['start_grading_col' => self::GRADING_COLUMN, 'end_grading_col' => self::GRADING_COLUMN, 'updated_at' => now()]);
        }
        $id = $existing?->id ?? (int) DB::table('transition_profile_definitions')->insertGetId([
            'profile_code' => 'M101', 'short_name' => 'MAIIC', 'description' => 'MAIIC: IFRS 9 stage to stage (measured stage), balances',
            'start_table' => 'loan_books', 'end_table' => 'loan_books', 'start_grading_col' => self::GRADING_COLUMN, 'end_grading_col' => self::GRADING_COLUMN,
            'start_value_type' => 'text', 'end_value_type' => 'text', 'start_client_id_col' => 'contract_id', 'end_client_id_col' => 'contract_id', 'aggregation_criteria' => 'Balance',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $options = [
            ['1', 'start', 1, 1, 1, '1', 0], ['2', 'start', 2, 2, 2, '2', 0], ['3', 'start', 3, 3, 3, '3', 0],
            ['1', 'end', 1, 1, 1, '1', 0], ['2', 'end', 2, 2, 2, '2', 0], ['3', 'end', 3, 3, 3, '3', 1], ['Paid', 'end', 4, 0, 0, 'Paid', 0],
        ];
        foreach ($options as [$name, $side, $order, $min, $max, $text, $default]) {
            DB::table('transition_profile_options')->updateOrInsert(['profile_id' => $id, 'category_name' => $name, 'is_start_or_end' => $side],
                ['ordering_index' => $order, 'min_value' => $min, 'max_value' => $max, 'text_value' => $text, 'default_value' => $default, 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->command?->info('Transition profile M101 seeded with 7 options.');
    }
}
