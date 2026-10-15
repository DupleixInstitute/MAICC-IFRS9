p = r'c:\xampp\htdocs\MAICC-IFRS9\app\Services\Reports\Ifrs9NoteDocuments.php'
s = open(p, encoding='utf-8').read()


def rep(old, new):
    global s
    assert s.count(old) == 1, old[:80]
    s = s.replace(old, new, 1)


rep("""        $note['write_offs'] = self::writeOffs($report, $unit);

        return $note;
    }""", """        $note['write_offs'] = self::writeOffs($report, $unit);
        $note['modifications'] = self::modifications($report, $unit);

        return $note;
    }

    /**
     * (h) Modified financial assets: IFRS 7.35J(a), 35J(b) and 20A, and the IFRS 9 B5.4.6
     * catch-up, from the confirmed and applied restructure register. No row when there is
     * nothing to disclose: the message says so, and how many candidates await confirmation.
     */
    private static function modifications(array $report, array $unit): ?array
    {
        $m = $report['modifications'] ?? null;
        if ($m === null) {
            return null;
        }
        $amt = fn ($v) => (int) round(Attribution::cents((float) $v) / $unit['step']);
        $rows = [];
        if ($m['installed'] && ! $m['none']) {
            $closing = Ifrs9NoteService::period($report['closing_period']);
            $rows = [
                ['key' => 'lifetime_cost', 'ref' => 'IFRS 7.35J(a)', 'label' => 'Modified in the period while the loss allowance was lifetime ECL: amortised cost before the modification',
                    'contracts' => $m['lifetime_ecl_modified']['count'], 'amount' => $amt($m['lifetime_ecl_modified']['amortised_cost_before'])],
                ['key' => 'lifetime_gain', 'ref' => 'IFRS 7.35J(a)', 'label' => 'Net modification gain / (loss) on those assets',
                    'contracts' => null, 'amount' => $amt($m['lifetime_ecl_modified']['net_gain_loss'])],
                ['key' => 'now_12_month', 'ref' => 'IFRS 7.35J(b)', 'label' => 'Modified since initial recognition while the allowance was lifetime ECL, allowance changed to 12-month ECL in the period: gross carrying amount at ' . $closing,
                    'contracts' => $m['lifetime_modified_now_12_month']['count'], 'amount' => $amt($m['lifetime_modified_now_12_month']['gross_carrying_amount'])],
                ['key' => 'all_gain', 'ref' => 'IFRS 9 5.4.3', 'label' => 'All modifications in the period that did not derecognise the asset: net modification gain / (loss)',
                    'contracts' => $m['all_modified']['count'], 'amount' => $amt($m['all_modified']['net_gain_loss'])],
                ['key' => 'derecognised_cost', 'ref' => 'IFRS 7.20A', 'label' => 'Derecognised on a substantial modification: carrying amount derecognised',
                    'contracts' => $m['derecognised']['count'], 'amount' => $amt($m['derecognised']['carrying_amount'])],
                ['key' => 'derecognised_gain', 'ref' => 'IFRS 7.20A', 'label' => 'Gain / (loss) on that derecognition',
                    'contracts' => null, 'amount' => $amt($m['derecognised']['gain_loss'])],
                ['key' => 'catch_up', 'ref' => 'IFRS 9 B5.4.6', 'label' => 'Revised estimates of the contractual cash flows: catch-up adjustment to the gross carrying amount',
                    'contracts' => $m['catch_up']['count'], 'amount' => $amt($m['catch_up']['adjustment'])],
            ];
        }

        return ['installed' => (bool) $m['installed'], 'none' => (bool) $m['none'], 'message' => (string) $m['message'], 'rows' => $rows,
            'candidates' => (int) ($m['candidates'] ?? 0), 'proposed' => (int) ($m['proposed'] ?? 0), 'pending' => (int) ($m['pending'] ?? 0), 'blocked' => (int) ($m['blocked'] ?? 0),
            'caption' => 'Loans whose contractual cash flows were modified or revised in the period, from the restructure register (classified by one person and approved by another) as the revenue run applied them (IFRS 7.35J, 20A; IFRS 9 5.4.3, B5.4.6). The reconciliations in (a) and (b) have no line of their own for these amounts.'];
    }""")

# Word
rep("""            $doc->paragraph('Amounts written off still subject to enforcement activity (IFRS 7.35L): ' . $note['write_offs']['enforcement']);
        }
""", """            $doc->paragraph('Amounts written off still subject to enforcement activity (IFRS 7.35L): ' . $note['write_offs']['enforcement']);
        }
        if (! empty($note['modifications'])) {
            $mo = $note['modifications'];
            $doc->heading('(h) Modified financial assets', 2);
            $doc->paragraph($mo['caption'], ['italic' => true, 'size' => 16, 'color' => '6B7280', 'after' => 80]);
            if ($mo['rows']) {
                $rows = array_map(fn ($r) => ['cells' => [$r['ref'], $r['label'], $r['contracts'] === null ? '' : self::count($r['contracts']), $fmt($r['amount'])]], $mo['rows']);
                $doc->table([['width' => 1500, 'align' => 'left'], ['width' => 5338, 'align' => 'left'], ['width' => 1100], ['width' => 1700]], $rows,
                    ['cells' => ['Reference', '', 'Contracts', $note['unit']['heading']]]);
            }
            $doc->paragraph($mo['message']);
        }
""")

# Excel / CSV payload
rep("""        foreach (['ecl' => 'loss allowance', 'gross' => 'gross carrying amount'] as $key => $what) {
            if ($recon = $note['tables'][$key]['recon']) {""", """        if (! empty($note['modifications'])) {
            $mo = $note['modifications'];
            $sections[] = ['heading' => '(h) Modified financial assets', 'note' => $mo['caption'] . ' ' . $mo['message'],
                'columns' => ['Reference', 'Line', 'Contracts', 'Amount (' . $note['unit']['heading'] . ')'], 'align' => ['l', 'l', 'r', 'r'],
                'rows' => $mo['rows']
                    ? array_map(fn ($r) => [$r['ref'], $r['label'], $r['contracts'] === null ? '' : self::count($r['contracts']), $fmt($r['amount'])], $mo['rows'])
                    : [['IFRS 7.35J, 20A', $mo['message'], '', '']]];
        }
        foreach (['ecl' => 'loss allowance', 'gross' => 'gross carrying amount'] as $key => $what) {
            if ($recon = $note['tables'][$key]['recon']) {""")
open(p, 'w', encoding='utf-8', newline='').write(s)
print('ok')
