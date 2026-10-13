<?php

namespace App\Support;

use App\Models\TransitionProfileDefinition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Display facts for the transition matrix pop-up: the transition profile
 * (code, name, aggregation basis) and a readable name for the matrix's
 * segment. Read only; it never touches the matrix figures.
 */
class MatrixPopupMeta
{
    public static function for(object $matrix): array
    {
        $profile = TransitionProfileDefinition::query()
            ->whereKey($matrix->transition_profile_id)
            ->first(['id', 'profile_code', 'short_name', 'aggregation_criteria']);

        return [
            'profile' => $profile ? [
                'code' => $profile->profile_code,
                'name' => $profile->short_name,
                'aggregation' => $profile->aggregation_criteria,
            ] : null,
            'segment' => self::segmentLabel($matrix),
        ];
    }

    /** "Pooled book", a portfolio name, "B. Agriculture" or a combination joined with " / ". */
    public static function segmentLabel(object $matrix): ?string
    {
        $level = (string) ($matrix->pd_calculation_level ?? '');
        $code = (string) ($matrix->pd_calculation_code ?? '');

        if ($code === '' && $level === 'portfolio' && $matrix->pd_calculation_id) {
            $code = 'portfolio:' . $matrix->pd_calculation_id;
        } elseif ($code === '' && $level === 'book') {
            $code = 'book';
        } elseif ($code !== '' && $level === 'sector' && ! str_contains($code, ':')) {
            $code = 'sector:' . $code; // matrices built from the screen store the bare sector code
        }
        if ($code === '') {
            return null;
        }

        $portfolios = Schema::hasTable('loan_portfolios') ? DB::table('loan_portfolios')->pluck('name', 'id')->all() : [];
        $sectors = Schema::hasTable('industry_types') ? DB::table('industry_types')->pluck('name', 'code')->all() : [];

        $parts = [];
        foreach (explode('|', $code) as $p) {
            if ($p === 'book') {
                $parts[] = 'Pooled book';
            } elseif (str_starts_with($p, 'portfolio:')) {
                $id = (int) substr($p, 10);
                $parts[] = $portfolios[$id] ?? "Portfolio {$id}";
            } elseif (str_starts_with($p, 'sector:')) {
                $s = substr($p, 7);
                $parts[] = isset($sectors[$s]) ? "{$s}. {$sectors[$s]}" : "Sector {$s}";
            } else {
                $parts[] = $p;
            }
        }

        return implode(' / ', $parts);
    }
}
