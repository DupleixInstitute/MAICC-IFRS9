<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Links every loan in the loan book to its client (audit of 13 October 2026).
 *
 * The loan book carries the borrower's E-Banker customer number and name on
 * every loan, but no client record: the Clients screen held 82 borrowers
 * typed or imported by hand, and no loan pointed at any of them. This makes
 * one client per E-Banker customer number, by that number:
 *
 *  - a client that already has the number is kept as it is (its details are
 *    never overwritten by the loan book);
 *  - a customer with no client gets one, named as on its latest loan, typed
 *    corporate when the name says so (limited, SACCO, cooperative, trust,
 *    association, company, enterprises...), individual otherwise;
 *  - every loan then carries the client of its customer number.
 *
 * Idempotent: running it again adds only new customers and links only new
 * loans. A loan with no customer number is left unlinked and counted.
 */
class ClientLinkService
{
    private const CORPORATE = '/\b(limited|ltd|plc|sacco|co-?operative|coop|trust|association|company|enterprises?|group|holdings|farms?|investments?|industries|centre|center|school|church|clinic|hotel|lodge|ministry|council|club|union|foundation|services|trading|processors|resources)\b/i';

    /** @return array{customers:int,created:int,existing:int,loans_linked:int,loans_without_customer:int} */
    public function link(?int $userId = null): array
    {
        $customers = DB::table('loan_books')->whereNotNull('customer_id')->where('customer_id', '!=', '')
            ->selectRaw('customer_id, MAX(reporting_period) latest')->groupBy('customer_id')->get();
        $names = DB::table('loan_books as l')
            ->joinSub(DB::table('loan_books')->whereNotNull('customer_id')->selectRaw('customer_id, MAX(reporting_period) p')->groupBy('customer_id'), 'x', fn ($j) => $j->on('x.customer_id', '=', 'l.customer_id')->on('x.p', '=', 'l.reporting_period'))
            ->select('l.customer_id', 'l.customer_name', 'l.industry_type', 'l.industry_code')->get()->keyBy('customer_id');
        $known = DB::table('clients')->whereNotNull('customer_id')->whereNull('deleted_at')->pluck('id', 'customer_id')->all();

        $created = 0;
        $batch = [];
        foreach ($customers as $c) {
            $id = trim((string) $c->customer_id);
            if (isset($known[$id])) {
                continue;
            }
            $row = $names[$c->customer_id] ?? null;
            $name = trim((string) ($row->customer_name ?? '')) ?: 'Customer ' . $id;
            $batch[] = ['customer_id' => $id, 'name' => mb_substr($name, 0, 199), 'type' => preg_match(self::CORPORATE, $name) ? 'corporate' : 'individual',
                'industry_type' => $row->industry_type ?? null, 'industry_code' => $row->industry_code ?? null, 'is_active' => 1, 'status' => 'active',
                'description' => 'Created from the loan book (E-Banker customer ' . $id . ')', 'created_by_id' => $userId, 'created_at' => now(), 'updated_at' => now()];
            $created++;
        }
        foreach (array_chunk($batch, 500) as $chunk) {
            DB::table('clients')->insert($chunk);
        }

        // every loan carries the client of its customer number
        $linked = DB::update('UPDATE loan_books l JOIN clients c ON c.customer_id = l.customer_id AND c.deleted_at IS NULL SET l.client_id = c.id WHERE l.client_id IS NULL OR l.client_id <> c.id');
        $without = DB::table('loan_books')->where(fn ($q) => $q->whereNull('customer_id')->orWhere('customer_id', ''))->count();
        $result = ['customers' => $customers->count(), 'created' => $created, 'existing' => $customers->count() - $created, 'loans_linked' => $linked, 'loans_without_customer' => $without];
        AuditLoggerService::log('Clients Linked To Loan Book', 'clients', null, ['rows_affected' => $created + $linked, 'new_values' => $result, 'meta' => ['user' => $userId]]);

        return $result;
    }
}
