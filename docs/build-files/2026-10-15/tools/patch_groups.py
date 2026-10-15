P = 'C:/xampp/htdocs/MAICC-IFRS9/'

def patch(p, pairs):
    s = open(P + p, encoding='utf-8').read()
    for a, b in pairs:
        assert a in s, (p, a[:60])
        s = s.replace(a, b, 1)
    open(P + p, 'w', encoding='utf-8').write(s)

patch('app/Http/Controllers/LoanProductCategoriesController.php', [
(r"""        return Inertia::render('LoanProductGroups/Index', [
            'filters' => \request()->all('search'),
            'groups' => $groups,
        ]);
    }""", r"""        // the loans each group carries in the latest loan book, and the groups the book uses that are not listed here
        $latest = DB::table('loan_books')->max('reporting_period');
        $inBook = DB::table('loan_books')->where('reporting_period', $latest)->whereNotNull('product_group')
            ->selectRaw('product_group, count(*) n')->groupBy('product_group')->pluck('n', 'product_group');
        $listed = LoanProductCategory::pluck('name')->map(fn ($n) => mb_strtolower(trim($n)))->all();

        return Inertia::render('LoanProductGroups/Index', [
            'filters' => \request()->all('search'),
            'groups' => $groups,
            'loansByGroup' => $inBook,
            'latestPeriod' => $latest,
            'unlisted' => $inBook->keys()->filter(fn ($g) => ! in_array(mb_strtolower(trim($g)), $listed, true))->values(),
        ]);
    }

    /** Add every product group the loan book uses that is not listed yet. */
    public function sync()
    {
        $listed = LoanProductCategory::pluck('name')->map(fn ($n) => mb_strtolower(trim($n)))->all();
        $added = 0;
        foreach (DB::table('loan_books')->whereNotNull('product_group')->distinct()->orderBy('product_group')->pluck('product_group') as $g) {
            if (trim((string) $g) === '' || in_array(mb_strtolower(trim($g)), $listed, true)) {
                continue;
            }
            LoanProductCategory::create(['name' => trim($g), 'description' => 'From the loan book (E-Banker product group)']);
            $listed[] = mb_strtolower(trim($g));
            $added++;
        }
        activity()->log("Product groups taken from the loan book: {$added}");

        return redirect()->route('groups.index')->with('success', $added ? "{$added} product group(s) added from the loan book." : 'Every product group in the loan book is already listed.');
    }"""),
("use Inertia\\Inertia;", "use Illuminate\\Support\\Facades\\DB;\nuse Inertia\\Inertia;"),
("'Loan Category created successfully.'", "'Product group created.'"),
("'Loan Category updated successfully.'", "'Product group updated.'"),
("'Loan Category deleted successfully.'", "'Product group deleted.'"),
])
patch('routes/web.php', [(r"""    Route::get('/create', [LoanProductCategoriesController::class, 'create'])->name('create');""",
 r"""    Route::get('/create', [LoanProductCategoriesController::class, 'create'])->name('create');
    Route::post('/sync', [LoanProductCategoriesController::class, 'sync'])->name('sync');""")])
patch('resources/js/Pages/LoanProductGroups/Index.vue', [
("""            <Link v-if="can('loans.products.create')" :href="route('groups.create')" class="primary-btn">New product group</Link>""",
 """            <button v-if="can('loans.products.create') && (unlisted || []).length" type="button" class="secondary-btn" @click="sync">Add the {{ unlisted.length }} from the loan book</button>
            <Link v-if="can('loans.products.create')" :href="route('groups.create')" class="primary-btn">New product group</Link>"""),
("""        <div class="maiic-panel">
            <div class="maiic-table-wrap">""",
 """        <p v-if="(unlisted || []).length" class="mb-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-900">The loan book of {{ latestPeriod }} uses {{ unlisted.length }} product group(s) not listed here: {{ unlisted.join(', ') }}.</p>
        <div class="maiic-panel">
            <div class="maiic-table-wrap">"""),
("""                        <th>Description</th>
                        <th class="text-right">Actions</th>""",
 """                        <th>Description</th>
                        <th class="num">Loans in {{ latestPeriod }}</th>
                        <th class="text-right">Actions</th>"""),
("""                        <td>{{ group.description || '-' }}</td>""",
 """                        <td>{{ group.description || '-' }}</td>
                        <td class="num">{{ (loansByGroup || {})[group.name] || 0 }}</td>"""),
("""<td class="maiic-empty" colspan="3">""", """<td class="maiic-empty" colspan="4">"""),
])
print('ok')
