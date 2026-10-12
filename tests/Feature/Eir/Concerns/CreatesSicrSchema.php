<?php

namespace Tests\Feature\Eir\Concerns;

/**
 * The qualitative SICR register tables (groups, items, triggers) on the
 * private in-memory schema, built from the real migrations so the tests
 * read exactly the columns the engine and the screens use.
 */
trait CreatesSicrSchema
{
    protected function createSicrSchema(): void
    {
        foreach ([
            '2025_10_20_200510_create_finance_sicr_groups_table.php',
            '2025_10_20_200520_create_finance_sicr_items_table.php',
            '2025_10_20_200530_create_finance_sicr_triggers_table.php',
            '2026_10_09_800000_sicr_qualitative_register.php',
        ] as $file) {
            (require base_path('database/migrations/' . $file))->up();
        }
    }
}
