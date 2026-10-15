#!/bin/bash
# Re-run the EIR revenue chain on maiic_ifrs9_gaps from 2024-07 to 2026-09; $1 = label (before/after)
cd C:/xampp/htdocs/MAICC-IFRS9
export DB_DATABASE=maiic_ifrs9_gaps
OUT="C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/gaps/run_$1.log"
: > "$OUT"
first=1
for y in 2024 2025 2026; do for m in 01 02 03 04 05 06 07 08 09 10 11 12; do
  p="$y-$m"
  [[ "$p" < "2024-07" ]] && continue
  [[ "$p" > "2026-09" ]] && continue
  if [ $first = 1 ]; then
    php artisan eir:run-revenue $p --user=1 --recalculate --reason="Documentation audit gap 4: $1 measure on the scratch clone" >> "$OUT" 2>&1
    first=0
  else
    php artisan eir:run-revenue $p --user=1 >> "$OUT" 2>&1
  fi
done; done
C:/xampp/mysql/bin/mysql -uroot maiic_ifrs9_gaps -e "SELECT CASE WHEN reporting_period LIKE '2025-%' THEN '2025' WHEN reporting_period BETWEEN '2026-01' AND '2026-09' THEN '2026 Jan-Sep' ELSE 'other' END span, COUNT(*) n_rows, SUM(interest_basis='NET') net_rows, ROUND(SUM(interest_accrued),2) interest, ROUND(SUM(unwind_amount),2) unwind, ROUND(SUM(interest_accrued+unwind_amount),2) total FROM eir_amortisation GROUP BY span ORDER BY span;" >> "$OUT"
