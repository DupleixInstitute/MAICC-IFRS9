<?php

namespace Tests\Unit\Support;

use App\Support\ContractId;
use PHPUnit\Framework\TestCase;

/**
 * The padding difference between E-Banker extracts and MAIIC's loan tape is
 * what held every extract row at import; these cases pin the canonical form.
 */
class ContractIdTest extends TestCase
{
    public function test_it_strips_the_e_banker_zero_padding(): void
    {
        $this->assertSame('104430000062', ContractId::normalise('000104430000062'));
        $this->assertSame('104450000053', ContractId::normalise('000104450000053'));
    }

    public function test_an_already_canonical_identifier_is_unchanged(): void
    {
        $this->assertSame('104430000062', ContractId::normalise('104430000062'));
    }

    public function test_it_removes_spreadsheet_debris(): void
    {
        $this->assertSame('104430000062', ContractId::normalise("  000104430000062\t"));
        $this->assertSame('104430000062', ContractId::normalise("000104430000062\xC2\xA0"));
        $this->assertSame('104430000062', ContractId::normalise('"000104430000062"'));
    }

    public function test_it_recovers_identifiers_read_out_of_numeric_cells(): void
    {
        $this->assertSame('104430000062', ContractId::normalise(104430000062));
        $this->assertSame('104430000062', ContractId::normalise(104430000062.0));
        $this->assertSame('104430000000', ContractId::normalise('1.0443E+11'));
    }

    public function test_tape_suffixes_are_preserved_because_they_identify_a_different_record(): void
    {
        $this->assertSame('104450000037_cleared', ContractId::normalise('104450000037_cleared'));
    }

    public function test_an_all_zero_identifier_keeps_one_zero_rather_than_vanishing(): void
    {
        $this->assertSame('0', ContractId::normalise('000'));
        $this->assertSame('0', ContractId::normalise('0'));
    }

    public function test_absent_identifiers_return_null(): void
    {
        $this->assertNull(ContractId::normalise(null));
        $this->assertNull(ContractId::normalise(''));
        $this->assertNull(ContractId::normalise('   '));
    }

    public function test_matches_compares_across_padding_conventions(): void
    {
        $this->assertTrue(ContractId::matches('000104430000062', '104430000062'));
        $this->assertFalse(ContractId::matches('000104430000062', '104430000063'));
        $this->assertFalse(ContractId::matches(null, null));
    }
}
