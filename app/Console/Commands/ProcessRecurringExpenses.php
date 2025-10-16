<?php

namespace App\Console\Commands;

use App\Models\Expense;
use App\Models\JournalEntry;
use App\Models\PaymentDetail;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ProcessRecurringExpenses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'expenses:process-recurring';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Processes recurring expenses';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $expenses = Expense::where('recurring', 1)
            ->where(function ($query) {
                $query->where('recur_start_date', date('Y-m-d'));
                $query->orWhere('recur_next_date', date('Y-m-d'));
            })
            ->get();
        $systemUserID=Setting::where('setting_key', 'system_user')->first()->setting_value;
        foreach ($expenses as $expense) {
            if ($expense->recur_type === 'selected_days') {
                foreach ($expense->selected_days as $day) {
                    $date = Carbon::parse($day);
                    if ($date->greaterThan(Carbon::now())) {
                        $expense->recur_next_date = $date->format('Y-m-d');
                        break;
                    }
                }
            } else {
                $expense->recur_next_date = Carbon::now()->add($expense->recur_type, $expense->recur_frequency)->format('Y-m-d');
            }
            $expense->recur_last_run_date = date('Y-m-d');
            $expense->save();
            $newExpense = new Expense();
            $newExpense->created_by_id = $systemUserID;
            $newExpense->expense_type_id = $expense->expense_type_id;
            $newExpense->currency_id = $expense->currency_id;
            $newExpense->branch_id = $expense->branch_id;
            $newExpense->expense_chart_of_account_id = $expense->expense_chart_of_account_id;
            $newExpense->asset_chart_of_account_id = $expense->asset_chart_of_account_id;
            $newExpense->amount = $expense->amount;
            $newExpense->xrate = $expense->xrate;
            $newExpense->date = $expense->date;

            $newExpense->description = $expense->description;
            $newExpense->save();
            $payment_detail = new PaymentDetail();
            $payment_detail->created_by_id = $systemUserID;
            $payment_detail->payment_type_id = $expense->payment_type_id;
            $payment_detail->transaction_type = 'expense';
            $payment_detail->cheque_number = $expense->cheque_number;
            $payment_detail->receipt = $expense->receipt;
            $payment_detail->account_number = $expense->account_number;
            $payment_detail->bank_name = $expense->bank_name;
            $payment_detail->routing_code = $expense->routing_code;
            $payment_detail->save();
            //store journal entries
            if (!empty($expense->expense_chart_of_account_id)) {
                $journal_entry = new JournalEntry();
                $journal_entry->created_by_id = $systemUserID;
                $journal_entry->payment_detail_id = $payment_detail->id;
                $journal_entry->transaction_number = $newExpense->id;
                $journal_entry->branch_id = $expense->branch_id;
                $journal_entry->currency_id = $expense->currency_id;
                $journal_entry->chart_of_account_id = $expense->expense_chart_of_account_id;
                $journal_entry->transaction_type = 'expense';
                $journal_entry->date = $expense->date;
                $journal_entry->debit = $expense->amount;
                $journal_entry->xrate = $newExpense->xrate;
                $journal_entry->reference = $newExpense->id;
                $journal_entry->description = $expense->description;
                $journal_entry->save();
            }
            if (!empty($expense->asset_chart_of_account_id)) {
                $journal_entry = new JournalEntry();
                $journal_entry->created_by_id = $systemUserID;
                $journal_entry->payment_detail_id = $payment_detail->id;
                $journal_entry->transaction_number = $newExpense->id;
                $journal_entry->branch_id = $expense->branch_id;
                $journal_entry->currency_id = $expense->currency_id;
                $journal_entry->chart_of_account_id = $expense->asset_chart_of_account_id;
                $journal_entry->transaction_type = 'expense';
                $journal_entry->date = $expense->date;
                $journal_entry->credit = $expense->amount;
                $journal_entry->xrate = $newExpense->xrate;
                $journal_entry->reference = $newExpense->id;
                $journal_entry->description = $expense->description;
                $journal_entry->save();
            }
        }
        return 0;
    }
}
