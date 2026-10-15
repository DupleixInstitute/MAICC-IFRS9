R = 'C:/xampp/htdocs/MAICC-IFRS9/'

def patch(p, pairs):
    s = open(R + p, encoding='utf-8').read()
    for a, b in pairs:
        assert a in s, (p, a[:60])
        s = s.replace(a, b, 1)
    open(R + p, 'w', encoding='utf-8').write(s)

patch('app/Http/Controllers/AuditTrailController.php', [
("""        $perPage = 15;""", """        if ($request->query('export') === 'csv') {
            return $this->export($union);
        }

        $perPage = 15;"""),
("""                'source' => $row->source,
                'action' => $row->action,""", """                'source' => $row->source,
                'kind' => self::kind($row),
                'action' => $row->action,"""),
("""            ],
        ]);
    }
}""", """            ],
        ]);
    }

    /** A record change, an export or download, or a module action (a calculation, an approval, a setting). */
    private static function kind(object $row): string
    {
        if ($row->source === 'activity') {
            return 'Record change';
        }

        return preg_match('/export|download|pack|pdf|excel|csv/i', (string) $row->action) ? 'Export' : 'Module action';
    }

    /** The filtered trail as a CSV, newest first. */
    private function export($union)
    {
        $users = DB::table('users')->pluck('name', 'id');
        $file = 'audit_trail_' . now()->format('Y-m-d_His') . '.csv';

        return response()->streamDownload(function () use ($union, $users) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['When', 'User', 'Type', 'Action', 'Record', 'Record ID', 'Details']);
            DB::query()->fromSub($union, 'trail')->orderByDesc('created_at')->limit(100000)->get()
                ->each(function ($row) use ($out, $users) {
                    fputcsv($out, [
                        $row->created_at,
                        $row->user_id ? ($users[$row->user_id] ?? 'User #' . $row->user_id) : 'System',
                        self::kind($row),
                        $row->action,
                        class_basename((string) $row->entity),
                        $row->entity_id,
                        $row->details,
                    ]);
                });
            fclose($out);
        }, $file, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}"""),
])

patch('resources/js/Pages/AuditTrail/Index.vue', [
("""    <app-layout title="Audit Trail" description="Every recorded change and engine run in the system, newest first, with who made it">""",
 """    <app-layout title="Audit Trail" description="Every recorded change, module action and export in the system, newest first, with who made it">
        <template #actions>
            <a :href="exportUrl" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-bold text-gray-700 shadow-sm transition hover:bg-gray-50">
                <font-awesome-icon icon="download"/> Export CSV
            </a>
        </template>"""),
("""                    <option value="module">Engine runs</option>""", """                    <option value="module">Module actions and exports</option>"""),
("""                    <span><b class="text-sm text-gray-900">{{ counts.module.toLocaleString('en-GB') }}</b> engine runs</span>""",
 """                    <span><b class="text-sm text-gray-900">{{ counts.module.toLocaleString('en-GB') }}</b> module actions</span>"""),
("""                                <span class="maiic-badge" :class="e.source === 'module' ? 'maiic-badge-gold' : 'maiic-badge-green'">
                                    {{ e.source === 'module' ? 'Engine run' : 'Record change' }}
                                </span>""", """                                <span class="maiic-badge" :class="e.kind === 'Export' ? 'maiic-badge-gray' : e.source === 'module' ? 'maiic-badge-gold' : 'maiic-badge-green'">
                                    {{ e.kind }}
                                </span>"""),
("""        hasFilters() {""", """        // the filters on screen, as a CSV of the whole filtered trail
        exportUrl() {
            const query = pickBy({ ...this.form, export: 'csv' }, (v) => v !== null && v !== '' && v !== undefined)
            return this.route('audit-trail.index') + '?' + new URLSearchParams(query).toString()
        },
        hasFilters() {"""),
])
print('ok')
