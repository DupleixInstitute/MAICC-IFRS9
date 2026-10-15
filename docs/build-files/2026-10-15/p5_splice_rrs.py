import re
p = r'C:\xampp\htdocs\MAICC-IFRS9\app\Services\Eir\RateResetService.php'
new = open(r'C:\Users\DELL\AppData\Local\Temp\claude\c--xampp-htdocs-Stress-Testing-App\c9a0f915-2064-4f18-9b98-77857ef71421\scratchpad\p5_detect.php.txt', encoding='utf-8').read()
s = open(p, encoding='utf-8').read()
a = s.index('/**\n * The writer of rate_reset_events')
b = s.index('    /** @param list<int> $ids */\n    public function propose(')
s = s[:a] + new + s[b:]
# the old upsertReset is replaced by the upserts above
c = s.index('    private function upsertReset(string $key, array $row): bool')
d = s.index('    /** @return array<string, list<string>> the dates each account')
s = s[:c] + s[d:]
s = s.replace('use App\\Models\\ReferenceRate;\n', 'use App\\Models\\ContractEir;\nuse App\\Models\\RateResetEvent;\nuse App\\Models\\ReferenceRate;\n', 1)
s = s.replace('use Carbon\\CarbonImmutable;\n', 'use Carbon\\CarbonImmutable;\nuse Carbon\\CarbonInterface;\n', 1)

# maker-checker state: classification_status (the pipeline owns status)
reps = [
    ("if ($r->status !== 'DETECTED') {\n                    throw new RuntimeException(\"Reset #{$r->id} is not awaiting a classification.\");",
     "if ($r->classification_status !== 'DETECTED') {\n                    throw new RuntimeException(\"Reset #{$r->id} is not awaiting a classification.\");"),
    ("->update(['status' => 'PROPOSED', 'classification' => $classification,",
     "->update(['classification_status' => 'PROPOSED', 'classification' => $classification,"),
    ("if ($r->status !== 'PROPOSED') {\n                    throw new RuntimeException(\"Reset #{$r->id} has no proposed classification to approve.\");",
     "if ($r->classification_status !== 'PROPOSED') {\n                    throw new RuntimeException(\"Reset #{$r->id} has no proposed classification to approve.\");"),
    ("->update(['status' => 'APPROVED', 'approved_by' => $userId,",
     "->update(['classification_status' => 'APPROVED', 'approved_by' => $userId,"),
    ("if ($r && $r->status === 'PROPOSED' && $action === 'reject'",
     "if ($r && $r->classification_status === 'PROPOSED' && $action === 'reject'"),
    ("if (! $r || $r->status !== 'PROPOSED' ||",
     "if (! $r || $r->classification_status !== 'PROPOSED' ||"),
    ("->update(['status' => 'DETECTED', 'classification' => null, 'proposed_by' => null,",
     "->update(['classification_status' => 'DETECTED', 'classification' => null, 'proposed_by' => null,"),
]
for old, newtxt in reps:
    if old not in s:
        raise SystemExit('missing: ' + old[:80])
    s = s.replace(old, newtxt)
open(p, 'w', encoding='utf-8', newline='\n').write(s)
print('ok', s.count("'status'"), s.count('->status'))
