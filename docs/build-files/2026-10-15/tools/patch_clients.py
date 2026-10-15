P = 'C:/xampp/htdocs/MAICC-IFRS9/'

def patch(p, pairs):
    s = open(P + p, encoding='utf-8').read()
    for a, b in pairs:
        assert a in s, (p, a[:60])
        s = s.replace(a, b, 1)
    open(P + p, 'w', encoding='utf-8').write(s)

patch('routes/web.php', [(r"""    Route::get('/', [ClientsController::class, 'index'])->name('index');""",
 r"""    Route::get('/', [ClientsController::class, 'index'])->name('index');
    Route::post('/link-loan-book', [ClientsController::class, 'linkLoanBook'])->name('link-loan-book');""")])
patch('app/Http/Controllers/ClientsController.php', [(r"""    public function destroy(Client $client)""",
 r"""    /** One client per loan-book customer number, and every loan linked to it. */
    public function linkLoanBook(Request $request, \App\Services\ClientLinkService $service)
    {
        abort_unless($request->user()?->can('clients.create'), 403);
        $r = $service->link((int) $request->user()->id);

        return redirect()->route('clients.index')->with('success', "{$r['created']} new client(s) from the loan book; {$r['loans_linked']} loan(s) linked to their client.");
    }

    public function destroy(Client $client)""")])
patch('app/Console/Commands/MaintenanceTask.php', [("""            'month-end' => [
""", """            'month-end' => [
                ['Clients for new loan-book customers', 'clients:link-loan-book', $u],
""")])
patch('resources/js/Pages/Clients/Index.vue', [
('description="The borrower records kept for credit assessment: added here or imported. The loan book carries each borrower\'s name and customer number from E-Banker"',
 'description="Every borrower in the loan book, one client per E-Banker customer number, and those added here or imported. Each loan is linked to its client"'),
("""            <Link v-if="can.create" :href="route('clients.import.create')" class="secondary-btn">Import clients</Link>""",
 """            <button v-if="can.create" type="button" class="secondary-btn" @click="linkLoanBook" title="Create a client for every loan-book customer not yet held, and link every loan to its client">Link the loan book</button>
            <Link v-if="can.create" :href="route('clients.import.create')" class="secondary-btn">Import clients</Link>"""),
("""        return { form, destroy }""", """        const linkLoanBook = () => router.post(route('clients.link-loan-book'), {}, { preserveScroll: true })

        return { form, destroy, linkLoanBook }"""),
])
print('ok')
