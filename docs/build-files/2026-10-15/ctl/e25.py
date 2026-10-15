from ed import edit
edit('app/Console/Commands/EirLoadLedgerCash.php', r"""        foreach ($r['by_type'] as $type => $n) {
            $this->line("  {$type}: {$n}");
        }
""", r"""        foreach ($r['by_type'] as $type => $n) {
            $this->line("  {$type}: {$n}");
        }
        $rv = $r['reversals'];
        $this->line("Reversals (306, 343, 901): {$rv['reversals']}, {$rv['paired']} paired to the posting they reverse, {$rv['unpaired']} unpaired" . ($rv['unpaired_ids'] !== [] ? ' (vouchers ' . implode(', ', $rv['unpaired_ids']) . ')' : '') . '.');
""")
