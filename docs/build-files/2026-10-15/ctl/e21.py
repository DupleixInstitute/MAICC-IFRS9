from ed import edit
edit('app/Services/Eir/BaselineService.php', r"""                $net[$gl] += (float) ($r['payload']['TRANSAMT'] ?? 0);""", r"""                // the export may write thousands commas ('1,234.50'): a bare cast read it as 1 (audit L5)
                $net[$gl] += (float) str_replace([',', ' '], '', (string) ($r['payload']['TRANSAMT'] ?? 0));""")
edit('app/Imports/ContractMasterImport.php', r"""            // The delivered RATE_BASIS column carries Fixed/Variable — it is
            // the rate type, not a day-count or accrual basis. Mapping it to
            // the free-text rate_basis column would leave rate_type on its
            // FIXED default for the 204 variable-rate facilities in the book.
            'RATE_BASIS' => 'rate_type',
            'RATE BASIS' => 'rate_type',
            'RATE_TYPE' => 'rate_type',""", r"""            // RATE_BASIS is not mapped (system audit M15, spec 7.2). It was read
            // as the rate type, but the rate type is derived from E-Banker's
            // INTEREST_POLICY (P floating, any other policy fixed) and a
            // RATE_BASIS column has carried the opposite sense in a delivered
            // file; a column whose meaning is not certain is not read. A file
            // states the rate type as RATE_TYPE.
            'RATE_TYPE' => 'rate_type',""")
