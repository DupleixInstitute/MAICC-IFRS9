<?php
// Compare two bootstrap databases on the same server, read-only: per-period totals and row-level
// fingerprints of the engine outputs. Usage: php compare.php dbA dbB
[$_, $a, $b] = $argv;
$pdo = new PDO('mysql:host=127.0.0.1;port=3306', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
function q(PDO $pdo, string $db, string $sql): array
{
    $pdo->exec("USE `{$db}`");
    $pdo->exec('SET SESSION group_concat_max_len = 1073741824');
    try {
        return $pdo->query($sql)->fetchAll(PDO::FETCH_NUM);
    } catch (Throwable $e) {
        return [['ERROR ' . str_replace($db, 'DB', substr($e->getMessage(), 0, 160))]];
    }
}
$checks = [
    'loan_books by period and stage' => "SELECT reporting_period, product_code IN ('1050301','1050302','1050303','1050304','1050305','1050306','1050307') mf, ifrs9_stage, count(*), round(sum(carrying_amount),2), round(sum(ecl_value),2), round(sum(ecl_value_discounted),2), round(sum(pd_value),8), round(sum(lgd_value),8), round(sum(pd_post_fli),8), round(sum(ead),2), sum(sicr) FROM loan_books GROUP BY 1,2,3 ORDER BY 1,2,3",
    'loan_books row fingerprint' => "SELECT reporting_period, md5(group_concat(concat_ws('|', contract_id, product_code, round(carrying_amount,2), ifrs9_stage, round(ecl_value,2), round(ecl_value_discounted,2), round(pd_value,10), round(lgd_value,10), round(pd_post_fli,10), overdue_days, build_flag) ORDER BY contract_id, product_code SEPARATOR ';')) FROM loan_books GROUP BY 1 ORDER BY 1",
    'eir_amortisation by period' => "SELECT reporting_period, count(*), round(sum(opening_gross),2), round(sum(interest_accrued),2), round(sum(cash_received),2), round(sum(closing_gross),2), round(sum(ecl_allowance),2), round(sum(unwind_amount),2), round(sum(modification_gain_loss),2) FROM eir_amortisation GROUP BY 1 ORDER BY 1",
    'eir_amortisation row fingerprint' => "SELECT md5(group_concat(concat_ws('|', contract_id, reporting_period, schedule_version, round(opening_gross,2), round(interest_accrued,2), round(cash_received,2), round(closing_gross,2), round(ecl_allowance,2), round(eir_annual,10)) ORDER BY contract_id, reporting_period, schedule_version SEPARATOR ';')) FROM eir_amortisation",
    'contract_eir' => "SELECT calculation_status, schedule_approval_status, count(*), round(sum(eir_effective_annual),10), round(sum(eir_nominal_annual),10), md5(group_concat(concat_ws('|', contract_id, round(eir_effective_annual,12), round(eir_period,12), calculation_status, round(opening_amortised_cost,2), round(drawn_amount,2)) ORDER BY contract_id SEPARATOR ';')) FROM contract_eir GROUP BY 1,2 ORDER BY 1,2",
    'contract_cashflow_schedule' => "SELECT count(*), round(sum(principal_due),2), round(sum(interest_due),2), round(sum(fee_due),2) FROM contract_cashflow_schedule",
    'contract_fees' => "SELECT source_system, classification_status, superseded_by_source IS NULL, count(*), round(sum(amount),2) FROM contract_fees GROUP BY 1,2,3 ORDER BY 1,2,3",
    'megafarm_ecl_runs' => "SELECT reporting_period, loans, round(gross,2), round(programme_ecl,2), round(maiic_ecl,2), declined FROM megafarm_ecl_runs ORDER BY id",
    'segment parameter results' => "SELECT count(*), md5(group_concat(concat_ws('|', r.id IS NOT NULL) SEPARATOR '')) FROM segment_parameter_results r",
    'ecl_pd_term_structures' => "SELECT count(*) FROM ecl_pd_term_structures",
    'fli_run_snapshots' => "SELECT reporting_period, round(booked_ecl,2), round(weighted_ecl,2) FROM fli_run_snapshots ORDER BY id",
    'ebanker_raw_rows by query' => "SELECT query_id, count(*), sum(superseded_at IS NULL), md5(group_concat(row_hash ORDER BY query_id, source_key, version SEPARATOR '')) FROM ebanker_raw_rows GROUP BY 1 ORDER BY 1",
    'governed_scenario_sets' => "SELECT reporting_period, status, version FROM governed_scenario_sets ORDER BY id",
    'ecl_scenario_results' => "SELECT count(*) FROM ecl_scenario_results",
];
$diff = 0;
foreach ($checks as $name => $sql) {
    $ra = q($pdo, $a, $sql); $rb = q($pdo, $b, $sql);
    $same = $ra === $rb;
    $diff += $same ? 0 : 1;
    echo ($same ? 'SAME ' : 'DIFF ') . $name . ' (' . count($ra) . ' rows)' . "\n";
    if (! $same) {
        $n = 0;
        foreach ($ra as $i => $row) {
            if (($rb[$i] ?? null) !== $row) {
                echo '   A: ' . implode(' | ', array_map('strval', $row)) . "\n   B: " . implode(' | ', array_map('strval', $rb[$i] ?? ['-'])) . "\n";
                if (++$n >= 5) break;
            }
        }
        if (count($rb) !== count($ra)) echo '   row counts ' . count($ra) . ' vs ' . count($rb) . "\n";
    }
}
echo $diff === 0 ? "IDENTICAL\n" : "{$diff} sections differ\n";
