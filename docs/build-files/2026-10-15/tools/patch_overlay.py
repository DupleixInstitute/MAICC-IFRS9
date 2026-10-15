import sys
p = r'c:\xampp\htdocs\MAICC-IFRS9\app\Services\Fli\OverlayService.php'
s = open(p, encoding='utf-8').read()
def rep(a, b):
    global s
    if a not in s: sys.exit('NOT FOUND ' + a[:70])
    s = s.replace(a, b, 1)
rep("""            'proposed_by' => $userId, 'proposed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        AuditLoggerService::log('FLI Overlay Proposed'""", """            'proposed_by' => $userId, 'proposed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ] + $this->doubleCountFields($data));
        AuditLoggerService::log('FLI Overlay Proposed'""")
rep("""        DB::table('fli_overlays')->where('id', $overlayId)->update(['status' => 'APPROVED', 'approved_by' => $approverId,""", """        if ($clash = $this->doubleCount($o)) {
            throw new RuntimeException($clash);
        }
        DB::table('fli_overlays')->where('id', $overlayId)->update(['status' => 'APPROVED', 'approved_by' => $approverId,""")
rep("""    public function reject(int $overlayId, ?int $userId, string $reason): void""", """    /**
     * The double-counting check (FLI module D-F9): an overlay that names, as
     * the risk it covers, the driver of the model version in force for its
     * month repeats a risk the model already carries. It cannot be approved
     * unless the proposer recorded why the model does not capture it. Returns
     * the reason it is refused, or null.
     */
    public function doubleCount(object $o): ?string
    {
        $driver = strtoupper(trim((string) ($o->risk_driver ?? '')));
        if ($driver === '') {
            return null;
        }
        $version = (new FliModelRegistry())->inForce((string) $o->reporting_period);
        if ($version === null || strtoupper((string) $version->statistic_code) !== $driver) {
            return null;
        }
        if (trim((string) ($o->double_count_review ?? '')) !== '') {
            return null;
        }

        return "Overlay {$o->id} covers {$driver}, which is already the driver of model version {$version->version} in force for {$o->reporting_period}: approving it would count that risk twice. Record on the overlay why the model does not capture it, or withdraw it.";
    }

    /** @return array<string,?string> the double-counting fields of a proposal, where the table has them */
    private function doubleCountFields(array $data): array
    {
        if (! DB::getSchemaBuilder()->hasColumn('fli_overlays', 'risk_driver')) {
            return [];
        }
        $driver = trim((string) ($data['risk_driver'] ?? ''));
        $review = trim((string) ($data['double_count_review'] ?? ''));

        return ['risk_driver' => $driver === '' ? null : mb_substr(strtoupper($driver), 0, 64), 'double_count_review' => $review === '' ? null : $review];
    }

    public function reject(int $overlayId, ?int $userId, string $reason): void""")
open(p, 'w', encoding='utf-8').write(s)
print('ok')
