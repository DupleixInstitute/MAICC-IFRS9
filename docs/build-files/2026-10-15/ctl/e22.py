from ed import edit
p='app/Services/Eir/ContractMasterImportService.php'
edit(p, r"""        // rate_type keeps its own mapping from RATE_BASIS and is not touched.""", r"""        // rate_type is the file's RATE_TYPE where stated, else the policy's (spec 7.2:
        // P floating, any other policy fixed); the RATE_BASIS alias is gone (audit M15).""")
edit(p, r"""            'rate_type' => $this->rateType($row['rate_type'] ?? null),""", r"""            'rate_type' => $this->rateTypeFor($row['rate_type'] ?? null, $interestPolicy, $rowNotes),""")
edit(p, r"""    private function rateType($value): ?string
    {""", r"""    /**
     * The rate type: as the file states it (RATE_TYPE), else as E-Banker's
     * Interest Policy says (P floating, S or M fixed: spec 7.2, the rule the
     * E-Banker build applies). A stated type that disagrees with the policy
     * is kept and noted. With neither, null: never a FIXED default.
     */
    private function rateTypeFor($stated, ?string $interestPolicy, array &$rowNotes): ?string
    {
        $type = $this->rateType($stated);
        $fromPolicy = match ($interestPolicy) {
            'P' => 'FLOATING',
            'S', 'M' => 'FIXED',
            default => null,
        };
        if ($type !== null && $fromPolicy !== null && $type !== $fromPolicy) {
            $rowNotes[] = "rate type '{$type}' disagrees with interest policy '{$interestPolicy}' ({$fromPolicy}); the stated type is kept";
        }

        return $type ?? $fromPolicy;
    }

    private function rateType($value): ?string
    {""")
edit('tests/Unit/Eir/MappedFileReaderTest.php', r"""    /**
     * The delivered RATE_BASIS column holds Fixed/Variable — the rate type.
     * Mapped anywhere else, 204 of the book's 362 facilities keep rate_type
     * on its FIXED default while actually being floating.
     */
    public function test_rate_basis_column_maps_to_rate_type(): void
    {
        $this->assertSame('rate_type', ContractMasterImport::aliases()['RATE_BASIS']);""", r"""    /**
     * RATE_BASIS is no longer read as the rate type (system audit M15, spec
     * 7.2): the rate type is the file's RATE_TYPE, else E-Banker's Interest
     * Policy. A RATE_BASIS header now maps to the free-text rate_basis.
     */
    public function test_rate_basis_is_not_read_as_the_rate_type(): void
    {
        $this->assertArrayNotHasKey('RATE_BASIS', ContractMasterImport::aliases());
        $this->assertSame('rate_type', ContractMasterImport::aliases()['RATE_TYPE']);""")
edit('tests/Feature/Eir/ContractMasterNewFieldsTest.php', r"""        // The existing rate_type mapping from RATE_BASIS is untouched (D16).""", r"""        // rate_type is the stated RATE_TYPE (the RATE_BASIS alias is gone, audit M15).""")
edit('tests/Feature/Eir/ContractMasterNewFieldsTest.php', r"""        $this->assertSame('FIXED', $c->rate_type, 'rate_type still comes from RATE_BASIS, never from the flag');""", r"""        $this->assertSame('FIXED', $c->rate_type, 'rate_type is the stated type, never the flag');
        $this->assertStringContainsString("rate type 'FIXED' disagrees with interest policy 'P'", $result['notes']['104450000053']);""")
