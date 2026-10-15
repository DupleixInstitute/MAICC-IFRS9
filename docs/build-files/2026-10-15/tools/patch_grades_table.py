C = 'C:/xampp/htdocs/MAICC-IFRS9/app/Http/Controllers/InternalGradingController.php'
s = open(C, encoding='utf-8').read()

def rep(src, a, b):
    assert src.count(a) == 1, a[:90]
    return src.replace(a, b, 1)

s = rep(s, """            $grades = $profile->mappings->map(function ($mapping) use ($maxTenor) {

                $pdMap = $mapping->tenorPds
                    ->keyBy('tenor_years')
                    ->map(fn ($pd) => $pd->pd_probability);

                return [
                    'id' => $mapping->id,
                    'grade_code' => $mapping->grade_code,
                    'grade_name' => $mapping->grade_name,
                    'pds' => collect(range(1, $maxTenor))->mapWithKeys(
                        fn ($year) => [$year => $pdMap[$year] ?? null]
                    ),
                ];
            });""", """            $grades = $profile->mappings->sortBy(fn ($m) => [is_numeric($m->grade_code) ? (int) $m->grade_code : PHP_INT_MAX, $m->grade_code])->values()->map(function ($mapping) use ($maxTenor) {

                $pdMap = $mapping->tenorPds
                    ->keyBy('tenor_years')
                    ->map(fn ($pd) => $pd->pd_probability);

                // the ECL model's combined label is "code-description-S&P rating-broad grade"; show the description on its own
                $label = (string) $mapping->grade_name;
                $description = $label;
                $prefix = $mapping->grade_code . '-';
                $suffix = '-' . $mapping->sp_rating . '-' . $mapping->rbm_class;
                if ($mapping->sp_rating && $mapping->rbm_class && str_starts_with($label, $prefix) && str_ends_with($label, $suffix)) {
                    $description = trim(substr($label, strlen($prefix), strlen($label) - strlen($prefix) - strlen($suffix)));
                }

                return [
                    'id' => $mapping->id,
                    'grade_code' => $mapping->grade_code,
                    'grade_name' => $mapping->grade_name,
                    'description' => $description,
                    'sp_rating' => $mapping->sp_rating,
                    'broad_grade' => $mapping->rbm_class,
                    'lower_bound' => $mapping->lower_bound,
                    'upper_bound' => $mapping->upper_bound,
                    'pds' => collect(range(1, $maxTenor))->mapWithKeys(
                        fn ($year) => [$year => $pdMap[$year] ?? null]
                    ),
                ];
            });""")
open(C, 'w', encoding='utf-8').write(s)

V = 'C:/xampp/htdocs/MAICC-IFRS9/resources/js/Pages/InternalGrading/Index.vue'
v = open(V, encoding='utf-8').read()
start = v.index('            <div v-if="profiles && profiles.length" class="grid')
end = v.index('            <!-- Empty State -->')
table = """            <div v-if="profiles && profiles.length" class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
                <table class="maiic-table">
                    <thead>
                        <tr>
                            <th>Profile</th>
                            <th class="num">Grades</th>
                            <th class="num">Max tenor</th>
                            <th>Status</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="profile in profiles" :key="profile.id" class="cursor-pointer" @click="openMatrix(profile)">
                            <td class="max-w-xl">
                                <p class="font-semibold text-gray-900 dark:text-slate-100">{{ profile.name }}</p>
                                <p v-if="profile.description" class="mt-0.5 line-clamp-2 text-xs text-gray-500 dark:text-slate-400" :title="profile.description">{{ profile.description }}</p>
                            </td>
                            <td class="num">{{ profile.mappings_count || 0 }}</td>
                            <td class="num whitespace-nowrap">{{ profile.max_tenor_years }} years</td>
                            <td class="whitespace-nowrap">
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="profile.is_active ? activeClass : draftClass">{{ profile.is_active ? 'Active' : 'Draft' }}</span>
                            </td>
                            <td class="whitespace-nowrap text-right" @click.stop>
                                <div class="inline-flex items-center gap-3">
                                    <button type="button" class="text-maiic-600 hover:text-maiic-700 dark:text-emerald-400" title="View the PD matrix" @click="openMatrix(profile)"><i class="fas fa-table"></i></button>
                                    <button v-if="!profile.is_active" type="button" class="text-gray-500 hover:text-maiic-600 dark:text-slate-400" title="Manage grades" @click="goToGrades(profile.id)"><i class="fas fa-list"></i></button>
                                    <button v-if="!profile.is_active" type="button" class="text-gray-500 hover:text-maiic-600 dark:text-slate-400" title="Edit profile" @click="openEditProfile(profile)"><i class="fas fa-edit"></i></button>
                                    <button v-if="!profile.is_active" type="button" class="text-gray-500 hover:text-red-600 dark:text-slate-400" title="Delete profile" @click="deleteProfile(profile)"><i class="fas fa-trash"></i></button>
                                    <button type="button" :title="profile.is_active ? 'Deactivate profile' : 'Activate profile'" @click="toggleStatus(profile)">
                                        <i v-if="profile.is_active" class="fas fa-unlock text-maiic-600 dark:text-emerald-400"></i>
                                        <i v-else class="fas fa-lock text-red-600 dark:text-red-400"></i>
                                    </button>
                                    <button v-if="profile.is_active" type="button" class="rounded bg-maiic-500 px-2 py-1 text-xs text-white hover:bg-maiic-600" title="Update the loan book with this profile's PDs" @click="openLoanBookModal(profile)">Update loan book</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

"""
v = v[:start] + table + v[end:]
v = rep(v, "const activeClass = 'bg-maiic-100 text-maiic-700'\nconst draftClass = 'bg-amber-100 text-amber-700'",
        "const activeClass = 'bg-maiic-100 text-maiic-700 dark:bg-emerald-900/40 dark:text-emerald-200'\nconst draftClass = 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-200'\nconst isSp = (p) => /S&P/.test(p.name || '') || /S&P/.test(p.description || '')")
open(V, 'w', encoding='utf-8').write(v)
print('ok')
