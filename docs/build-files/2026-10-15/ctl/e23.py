from ed import edit
p='tests/Feature/Eir/EirSampleFileServiceTest.php'
edit(p, r"""    /**
     * RATE_BASIS in a delivered contract master carries Fixed/Variable and is
     * deliberately read as rate_type. A rate_basis column in the template
     * would therefore land on rate_type alongside the real one, so the
     * generator withdraws it.
     */
    public function test_a_field_whose_name_an_alias_claims_is_withdrawn(): void
    {
        $headers = $this->samples->headers('contract_master');

        $this->assertContains('rate_type', $headers);
        $this->assertNotContains('rate_basis', $headers);
        $this->assertContains('rate_basis', MappedFileReader::OPTIONAL_FIELDS['contract_master'],
            'the field is still supported; only the template column is withdrawn');
    }""", r"""    /**
     * A field whose own name an alias claims for a different field is
     * withdrawn from the template. Until 14 October 2026 RATE_BASIS was read
     * as rate_type and rate_basis was withdrawn; the alias is gone (system
     * audit M15), so both columns are in the template and each feeds its own
     * field, and no alias claims a field's own name.
     */
    public function test_a_field_whose_name_an_alias_claims_is_withdrawn(): void
    {
        $headers = $this->samples->headers('contract_master');

        $this->assertContains('rate_type', $headers);
        $this->assertContains('rate_basis', $headers);
        $aliases = array_change_key_case(MappedFileReader::aliasTemplateFor('contract_master'), CASE_LOWER);
        foreach ($headers as $h) {
            $claimed = $aliases[strtolower($h)] ?? $h;
            $this->assertSame($h, $claimed, "the template column {$h} is claimed by an alias for {$claimed}");
        }
    }""")
