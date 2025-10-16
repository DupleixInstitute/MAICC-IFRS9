<?php


namespace App\Actions\Reports;


use App\Models\Event;
use App\Models\Claim;
use App\Models\Consultation;
use App\Models\CourseMaterial;
use App\Models\Currency;
use App\Models\FinancialPeriod;
use App\Models\InventoryProduct;
use App\Models\InventoryProductPurchase;
use App\Models\InventoryProductPurchasePayment;
use App\Models\InventoryProductPurchaseReturn;
use App\Models\InventoryProductSale;
use App\Models\InventoryProductSaleItem;
use App\Models\InventoryProductSaleReturn;
use App\Models\InventoryProductVariation;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\JournalEntry;
use App\Models\Client;
use App\Models\ReferringPractitioner;
use App\Models\Setting;
use App\Models\Message;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\Activitylog\Models\Activity;

class Reports
{
    public function getAppointmentsByStatus($data = [])
    {
        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? '';
        $currencyID = $data['currency_id'] ?? '';
        $referringPractitionerID = $data['referring_practitioner_id'] ?? '';
        $createdByType = $data['created_by_type'] ?? '';
        $patientID = $data['patient_id'] ?? '';
        $doctorID = $data['doctor_id'] ?? '';
        $appointmentType = $data['appointment_type'] ?? '';
        $status = $data['status'] ?? '';
        $branchID = $data['branch_id'] ?? '';
        return Event::when(($startDate && $endDate), function (Builder $query) use ($startDate, $endDate) {
            $query->whereBetween('start_date', [$startDate, $endDate]);
        })
            ->when($doctorID, function ($query) use ($doctorID) {
                $query->where('appointments.doctor_id', $doctorID);
            })
            ->when($createdByType, function ($query) use ($createdByType) {
                $query->where('appointments.created_by_type', $createdByType);
            })
            ->when($referringPractitionerID, function ($query) use ($referringPractitionerID) {
                $query->where('appointments.referring_practitioner_id', $referringPractitionerID);
            })
            ->when($patientID, function ($query) use ($patientID) {
                $query->where('appointments.patient_id', $patientID);
            })
            ->when($appointmentType, function ($query) use ($appointmentType) {
                $query->where('appointments.appointment_type', $appointmentType);
            })
            ->when($branchID, function ($query) use ($branchID) {
                $query->where('appointments.branch_id', $branchID);
            })
            ->selectRaw('count(*) as total,status')
            ->groupBy('status')
            ->get();
    }

    public function getAppointmentsByPeriod($data = [])
    {
        $period = $data['period'] ?? 'week';
        $startDate = !empty($data['start_date']) ? Carbon::parse($data['start_date']) : Carbon::today();
        $chartData = [];
        $limit = 7;
        $add = 'day';
        if ($period === 'week') {
            $startDate = $startDate->startOf('week');
            $limit = 7;
            $add = 'day';
            $label = $startDate->format('D');
        }
        if ($period === 'month') {
            $startDate = $startDate->startOf('month');
            $limit = 31;
            $add = 'day';
            $label = $startDate->format('Y-m-d');
        }
        if ($period === 'year') {
            $startDate = $startDate->startOf('year');
            $limit = 12;
            $add = 'month';
            $label = $startDate->format('Y-m-d');
        }
        for ($i = 0; $i < $limit; $i++) {
            if ($period === 'week') {
                $label = $startDate->format('D');
            }
            if ($period === 'month') {
                $label = $startDate->format('Y-m-d');
            }
            if ($period === 'year') {
                $label = $startDate->format('M Y');
            }
            $chartData[] = [
                'value' => Event::when($period, function ($query) use ($period, $startDate) {
                    if ($period === 'week') {
                        $query->where('start_date', $startDate->format('Y-m-d'));
                    }
                    if ($period === 'month') {
                        $query->where('start_date', $startDate->format('Y-m-d'));
                    }
                    if ($period === 'year') {
                        $query->whereBetween('start_date', [$startDate->startOfMonth()->format('Y-m-d'), $startDate->endOfMonth()->format('Y-m-d')]);
                    }
                })
                    ->count(),

                'label' => $label
            ];
            $startDate = $startDate->add($add, 1, false);
        }
        return $chartData;
    }

    public function getPaymentsByPaymentType($data = [])
    {
        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? '';
        $currencyID = $data['currency_id'] ?? '';
        $paidBy = $data['paid_by'] ?? '';
        $coPayerID = $data['co_payer_id'] ?? '';
        $branchID = $data['branch_id'] ?? '';
        $baseCurrency = Currency::find(Setting::where('setting_key', 'currency')->first()->setting_value);
        $operator = '/';
        if ($currencyID) {
            $currency = Currency::find($currencyID);
            if ($currency->xrate > $baseCurrency->xrate) {
                $operator = '/';
            } else {
                $operator = '*';
            }
            $currencyFromBase = false;
        } else {
            $currencyID = $baseCurrency->id;
            $currencyFromBase = true;
        }
        return InvoicePayment::leftJoin('payment_types', 'payment_types.id', 'invoice_payments.payment_type_id')
            ->when(($startDate && $endDate), function (Builder $query) use ($startDate, $endDate) {
                $query->whereBetween('date', [$startDate, $endDate]);
            })
            ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                $query->where('invoice_payments.currency_id', $currencyID);
            })
            ->when($paidBy, function ($query) use ($paidBy) {
                $query->where('invoice_payments.paid_by', $paidBy);
            })
            ->when($coPayerID, function ($query) use ($coPayerID) {
                $query->where('invoice_payments.co_payer_id', $coPayerID);
            })
            ->when($branchID, function ($query) use ($branchID) {
                $query->where('invoice_payments.branch_id', $branchID);
            })
            ->selectRaw('sum(coalesce(if(invoice_payments.xrate>1,amount*xrate,amount/xrate),0)) as amount,payment_types.name payment_type,payment_types.report_color')
            ->groupBy('invoice_payments.payment_type_id')
            ->get();
    }

    public function getPaymentsByPeriod($data = [])
    {
        $period = $data['period'] ?? 'week';
        $startDate = !empty($data['start_date']) ? Carbon::parse($data['start_date']) : Carbon::today();
        $endDate = $data['end_date'] ?? '';
        $currencyID = $data['currency_id'] ?? '';
        $paidBy = $data['paid_by'] ?? '';
        $coPayerID = $data['co_payer_id'] ?? '';
        $branchID = $data['branch_id'] ?? '';
        $paymentTypeID = $data['payment_type_id'] ?? '';
        $baseCurrency = Currency::find(Setting::where('setting_key', 'currency')->first()->setting_value);
        $operator = '/';
        if ($currencyID) {
            $currency = Currency::find($currencyID);
            if ($currency->xrate > $baseCurrency->xrate) {
                $operator = '/';
            } else {
                $operator = '*';
            }
            $currencyFromBase = false;
        } else {
            $currencyID = $baseCurrency->id;
            $currencyFromBase = true;
        }
        $chartData = [];
        $limit = 7;
        $add = 'day';
        if ($period === 'week') {
            $startDate = $startDate->startOf('week');
            $limit = 7;
            $add = 'day';
            $label = $startDate->format('D');
        }
        if ($period === 'month') {
            $startDate = $startDate->startOf('month');
            $limit = 31;
            $add = 'day';
            $label = $startDate->format('Y-m-d');
        }
        if ($period === 'year') {
            $startDate = $startDate->startOf('year');
            $limit = 12;
            $add = 'month';
            $label = $startDate->format('Y-m-d');
        }
        for ($i = 0; $i < $limit; $i++) {
            if ($period === 'week') {
                $label = $startDate->format('D');
            }
            if ($period === 'month') {
                $label = $startDate->format('Y-m-d');
            }
            if ($period === 'year') {
                $label = $startDate->format('M Y');
            }
            $chartData[] = [
                'value' => InvoicePayment::leftJoin('payment_types', 'payment_types.id', 'invoice_payments.payment_type_id')
                    ->when($period, function ($query) use ($period, $startDate) {
                        if ($period === 'week') {
                            $query->where('date', $startDate->format('Y-m-d'));
                        }
                        if ($period === 'month') {
                            $query->where('date', $startDate->format('Y-m-d'));
                        }
                        if ($period === 'year') {
                            $query->whereBetween('date', [$startDate->startOfMonth()->format('Y-m-d'), $startDate->endOfMonth()->format('Y-m-d')]);
                        }
                    })
                    ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                        $query->where('invoice_payments.currency_id', $currencyID);
                    })
                    ->when($paidBy, function ($query) use ($paidBy) {
                        $query->where('invoice_payments.paid_by', $paidBy);
                    })
                    ->when($coPayerID, function ($query) use ($coPayerID) {
                        $query->where('invoice_payments.co_payer_id', $coPayerID);
                    })
                    ->when($branchID, function ($query) use ($branchID) {
                        $query->where('invoice_payments.branch_id', $branchID);
                    })
                    ->when($paymentTypeID, function ($query) use ($paymentTypeID) {
                        $query->where('invoice_payments.payment_type_id', $paymentTypeID);
                    })
                    ->selectRaw('coalesce(sum(if(currency_id=' . $currencyID . ',amount,amount' . $operator . 'xrate)),0) as amount')
                    ->first()->amount,

                'label' => $label
            ];
            $startDate = $startDate->add($add, 1, false);
        }
        return $chartData;
    }

    public function getIncomeExpenses($data = [])
    {
        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? '';
        $currencyID = $data['currency_id'] ?? '';
        $paidBy = $data['paid_by'] ?? '';
        $coPayerID = $data['co_payer_id'] ?? '';
        $branchID = $data['branch_id'] ?? '';
        $baseCurrency = Currency::find(Setting::where('setting_key', 'currency')->first()->setting_value);
        $operator = '/';
        if ($currencyID) {
            $currency = Currency::find($currencyID);
            if ($currency->xrate > $baseCurrency->xrate) {
                $operator = '/';
            } else {
                $operator = '*';
            }
            $currencyFromBase = false;
        } else {
            $currencyID = $baseCurrency->id;
            $currencyFromBase = true;
        }
        $expenses = JournalEntry::leftJoin('chart_of_accounts', 'chart_of_accounts.id', 'journal_entries.chart_of_account_id')
            ->where('chart_of_accounts.account_type', 'expense')
            ->when(($startDate && $endDate), function (Builder $query) use ($startDate, $endDate) {
                $query->whereBetween('journal_entries.date', [$startDate, $endDate]);
            })
            ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                $query->where('journal_entries.currency_id', $currencyID);
            })
            ->when($branchID, function ($query) use ($branchID) {
                $query->where('journal_entries.branch_id', $branchID);
            })
            ->selectRaw('coalesce(sum(if(currency_id=' . $currencyID . ',(coalesce(debit,0)-coalesce(credit,0)),(coalesce(debit,0)-coalesce(credit,0))' . $operator . 'xrate)),0) as amount')
            ->first()->amount;
        $income = JournalEntry::leftJoin('chart_of_accounts', 'chart_of_accounts.id', 'journal_entries.chart_of_account_id')
            ->where('chart_of_accounts.account_type', 'income')
            ->when(($startDate && $endDate), function (Builder $query) use ($startDate, $endDate) {
                $query->whereBetween('journal_entries.date', [$startDate, $endDate]);
            })
            ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                $query->where('journal_entries.currency_id', $currencyID);
            })
            ->when($branchID, function ($query) use ($branchID) {
                $query->where('journal_entries.branch_id', $branchID);
            })
            ->selectRaw('coalesce(sum(if(currency_id=' . $currencyID . ',(coalesce(credit,0)-coalesce(debit,0)),(coalesce(credit,0)-coalesce(debit,0))' . $operator . 'xrate)),0) as amount')
            ->first()->amount;
        return [
            'expenses' => $expenses,
            'income' => $income,
        ];
    }

    public function getPeriodIncomeExpenses($data = [])
    {
        $period = $data['period'] ?? 'week';
        $startDate = !empty($data['start_date']) ? Carbon::parse($data['start_date']) : Carbon::today();
        $endDate = $data['end_date'] ?? '';
        $currencyID = $data['currency_id'] ?? '';
        $paidBy = $data['paid_by'] ?? '';
        $coPayerID = $data['co_payer_id'] ?? '';
        $branchID = $data['branch_id'] ?? '';
        $paymentTypeID = $data['payment_type_id'] ?? '';
        $baseCurrency = Currency::find(Setting::where('setting_key', 'currency')->first()->setting_value);
        $operator = '/';
        if ($currencyID) {
            $currency = Currency::find($currencyID);
            if ($currency->xrate > $baseCurrency->xrate) {
                $operator = '/';
            } else {
                $operator = '*';
            }
            $currencyFromBase = false;
        } else {
            $currencyID = $baseCurrency->id;
            $currencyFromBase = true;
        }
        $chartData = [];
        $limit = 7;
        $add = 'day';
        if ($period === 'week') {
            $startDate = $startDate->startOf('week');
            $limit = 7;
            $add = 'day';
            $label = $startDate->format('D');
        }
        if ($period === 'month') {
            $startDate = $startDate->startOf('month');
            $limit = 31;
            $add = 'day';
            $label = $startDate->format('Y-m-d');
        }
        if ($period === 'year') {
            $startDate = $startDate->startOf('year');
            $limit = 12;
            $add = 'month';
            $label = $startDate->format('Y-m-d');
        }
        for ($i = 0; $i < $limit; $i++) {
            if ($period === 'week') {
                $label = $startDate->format('D');
            }
            if ($period === 'month') {
                $label = $startDate->format('Y-m-d');
            }
            if ($period === 'year') {
                $label = $startDate->format('M Y');
            }
            $expenses = JournalEntry::leftJoin('chart_of_accounts', 'chart_of_accounts.id', 'journal_entries.chart_of_account_id')
                ->where('chart_of_accounts.account_type', 'expense')
                ->when($period, function ($query) use ($period, $startDate) {
                    if ($period === 'week') {
                        $query->where('journal_entries.date', $startDate->format('Y-m-d'));
                    }
                    if ($period === 'month') {
                        $query->where('journal_entries.date', $startDate->format('Y-m-d'));
                    }
                    if ($period === 'year') {
                        $query->whereBetween('journal_entries.date', [$startDate->startOfMonth()->format('Y-m-d'), $startDate->endOfMonth()->format('Y-m-d')]);
                    }
                })
                ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                    $query->where('journal_entries.currency_id', $currencyID);
                })
                ->when($branchID, function ($query) use ($branchID) {
                    $query->where('journal_entries.branch_id', $branchID);
                })
                ->selectRaw('coalesce(sum(if(currency_id=' . $currencyID . ',(coalesce(debit,0)-coalesce(credit,0)),(coalesce(debit,0)-coalesce(credit,0))' . $operator . 'xrate)),0) as amount')
                ->first()->amount;
            $income = JournalEntry::leftJoin('chart_of_accounts', 'chart_of_accounts.id', 'journal_entries.chart_of_account_id')
                ->where('chart_of_accounts.account_type', 'income')
                ->when($period, function ($query) use ($period, $startDate) {
                    if ($period === 'week') {
                        $query->where('journal_entries.date', $startDate->format('Y-m-d'));
                    }
                    if ($period === 'month') {
                        $query->where('journal_entries.date', $startDate->format('Y-m-d'));
                    }
                    if ($period === 'year') {
                        $query->whereBetween('journal_entries.date', [$startDate->startOfMonth()->format('Y-m-d'), $startDate->endOfMonth()->format('Y-m-d')]);
                    }
                })
                ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                    $query->where('journal_entries.currency_id', $currencyID);
                })
                ->when($branchID, function ($query) use ($branchID) {
                    $query->where('journal_entries.branch_id', $branchID);
                })
                ->selectRaw('coalesce(sum(if(currency_id=' . $currencyID . ',(coalesce(credit,0)-coalesce(debit,0)),if(xrate<1,coalesce((coalesce(credit,0)-coalesce(debit,0))*xrate,0),coalesce((coalesce(credit,0)-coalesce(debit,0))/xrate,0)))),0) as amount')
                ->first()->amount;
            $chartData[] = [
                'expenses' => $expenses,
                'income' => $income,
                'label' => $label
            ];
            $startDate = $startDate->add($add, 1, false);
        }
        return $chartData;
    }

    public function getAppointments($data = []): Collection|array
    {
        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? '';
        $startTime = $data['start_time'] ?? '';
        $endTime = $data['end_time'] ?? '';
        $currencyID = $data['currency_id'] ?? '';
        $referringPractitionerID = $data['referring_practitioner_id'] ?? '';
        $createdByType = $data['created_by_type'] ?? '';
        $patientID = $data['patient_id'] ?? '';
        $doctorID = $data['doctor_id'] ?? '';
        $appointmentType = $data['appointment_type'] ?? '';
        $status = $data['status'] ?? '';
        $branchID = $data['branch_id'] ?? '';
        $baseCurrency = Currency::find(Setting::where('setting_key', 'currency')->first()->setting_value);
        $operator = '/';
        if ($currencyID) {
            $currency = Currency::find($currencyID);
            if ($currency->xrate > $baseCurrency->xrate) {
                $operator = '/';
            } else {
                $operator = '*';
            }
            $currencyFromBase = false;
        } else {
            $currencyID = $baseCurrency->id;
            $currencyFromBase = true;
        }

        return Event::with(['doctor', 'patient'])
            ->when(($startDate && $endDate), function (Builder $query) use ($startDate, $endDate) {
                $query->whereBetween('appointments.start_date', [$startDate, $endDate]);
            })
            ->when(($startTime && $endTime), function (Builder $query) use ($startTime, $endTime) {
                $query->where('appointments.start_time', '>=', $startTime);
                $query->where('appointments.end_date', '<=', $startTime);
            })
            ->when(($startDate && !$endDate), function (Builder $query) use ($startDate) {
                $query->where('appointments.start_date', $startDate);
            })
            ->when($doctorID, function ($query) use ($doctorID) {
                $query->where('appointments.doctor_id', $doctorID);
            })
            ->when($createdByType, function ($query) use ($createdByType) {
                $query->where('appointments.created_by_type', $createdByType);
            })
            ->when($status, function ($query) use ($status) {
                if (is_array($status)) {
                    $query->whereIn('appointments.status', $status);
                } else {
                    $query->where('appointments.status', $status);
                }
            })
            ->when($referringPractitionerID, function ($query) use ($referringPractitionerID) {
                $query->where('appointments.referring_practitioner_id', $referringPractitionerID);
            })
            ->when($patientID, function ($query) use ($patientID) {
                $query->where('appointments.patient_id', $patientID);
            })
            ->when($appointmentType, function ($query) use ($appointmentType) {
                $query->where('appointments.appointment_type', $appointmentType);
            })
            ->when($branchID, function ($query) use ($branchID) {
                $query->where('appointments.branch_id', $branchID);
            })
            ->selectRaw('appointments.*,(select date from consultations where patient_id=appointments.patient_id order by date desc limit 1) as last_consultation_date')
            ->orderBy('start_date', 'desc')
            ->get();
    }

    public function getAgingData($data = []): Collection|array
    {
        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? '';
        $startTime = $data['start_time'] ?? '';
        $endTime = $data['end_time'] ?? '';
        $currencyID = $data['currency_id'] ?? '';
        $referringPractitionerID = $data['referring_practitioner_id'] ?? '';
        $createdByType = $data['created_by_type'] ?? '';
        $patientID = $data['patient_id'] ?? '';
        $doctorID = $data['doctor_id'] ?? '';
        $coPayerID = $data['co_payer_id'] ?? '';
        $status = $data['status'] ?? '';
        $branchID = $data['branch_id'] ?? '';
        $baseCurrency = Currency::find(Setting::where('setting_key', 'currency')->first()->setting_value);
        $operator = '/';
        if ($currencyID) {
            $currency = Currency::find($currencyID);
            if ($currency->xrate > $baseCurrency->xrate) {
                $operator = '/';
            } else {
                $operator = '*';
            }
            $currencyFromBase = false;
        } else {
            $currencyID = $baseCurrency->id;
            $currencyFromBase = true;
        }
        $currentBalance = Invoice::where('balance', '>', 0)
            ->whereRaw('DATEDIFF(NOW(),invoices.date)<=30')
            ->when($doctorID, function ($query) use ($doctorID) {
                $query->where('invoices.doctor_id', $doctorID);
            })
            ->when($patientID, function ($query) use ($patientID) {
                $query->where('invoices.patient_id', $patientID);
            })
            ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                $query->where('invoices.currency_id', $currencyID);
            })
            ->when($branchID, function ($query) use ($branchID) {
                $query->where('invoices.branch_id', $branchID);
            })
            ->when($coPayerID, function ($query) use ($coPayerID) {
                $query->where('invoices.co_payer_id', $coPayerID);
            })
            ->selectRaw('coalesce(sum(if(currency_id=' . $currencyID . ',coalesce(balance,0),if(xrate<1,coalesce(balance*xrate,0),coalesce(balance/xrate,0)))),0) as balance,sponsor')
            ->groupBy('sponsor')
            ->get();
        $thirtyBalance = Invoice::where('balance', '>', 0)
            ->whereRaw('DATEDIFF(NOW(),invoices.date)>30 && DATEDIFF(NOW(),invoices.date)<=60')
            ->when($doctorID, function ($query) use ($doctorID) {
                $query->where('invoices.doctor_id', $doctorID);
            })
            ->when($patientID, function ($query) use ($patientID) {
                $query->where('invoices.patient_id', $patientID);
            })
            ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                $query->where('invoices.currency_id', $currencyID);
            })
            ->when($branchID, function ($query) use ($branchID) {
                $query->where('invoices.branch_id', $branchID);
            })
            ->when($coPayerID, function ($query) use ($coPayerID) {
                $query->where('invoices.co_payer_id', $coPayerID);
            })
            ->selectRaw('coalesce(sum(if(currency_id=' . $currencyID . ',coalesce(balance,0),if(xrate<1,coalesce(balance*xrate,0),coalesce(balance/xrate,0)))),0) as balance,sponsor')
            ->groupBy('sponsor')
            ->get();
        $sixtyBalance = Invoice::where('balance', '>', 0)
            ->whereRaw('DATEDIFF(NOW(),invoices.date)>60 && DATEDIFF(NOW(),invoices.date)<=90')
            ->when($doctorID, function ($query) use ($doctorID) {
                $query->where('invoices.doctor_id', $doctorID);
            })
            ->when($patientID, function ($query) use ($patientID) {
                $query->where('invoices.patient_id', $patientID);
            })
            ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                $query->where('invoices.currency_id', $currencyID);
            })
            ->when($branchID, function ($query) use ($branchID) {
                $query->where('invoices.branch_id', $branchID);
            })
            ->when($coPayerID, function ($query) use ($coPayerID) {
                $query->where('invoices.co_payer_id', $coPayerID);
            })
            ->selectRaw('coalesce(sum(if(currency_id=' . $currencyID . ',coalesce(balance,0),if(xrate<1,coalesce(balance*xrate,0),coalesce(balance/xrate,0)))),0) as balance,sponsor')
            ->groupBy('sponsor')
            ->get();
        $ninetyBalance = Invoice::where('balance', '>', 0)
            ->whereRaw('DATEDIFF(NOW(),invoices.date)>90 && DATEDIFF(NOW(),invoices.date)<=120')
            ->when($doctorID, function ($query) use ($doctorID) {
                $query->where('invoices.doctor_id', $doctorID);
            })
            ->when($patientID, function ($query) use ($patientID) {
                $query->where('invoices.patient_id', $patientID);
            })
            ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                $query->where('invoices.currency_id', $currencyID);
            })
            ->when($branchID, function ($query) use ($branchID) {
                $query->where('invoices.branch_id', $branchID);
            })
            ->when($coPayerID, function ($query) use ($coPayerID) {
                $query->where('invoices.co_payer_id', $coPayerID);
            })
            ->selectRaw('coalesce(sum(if(currency_id=' . $currencyID . ',coalesce(balance,0),if(xrate<1,coalesce(balance*xrate,0),coalesce(balance/xrate,0)))),0) as balance,sponsor')
            ->groupBy('sponsor')
            ->get();
        $oneHundredTwentyBalance = Invoice::where('balance', '>', 0)
            ->whereRaw('DATEDIFF(NOW(),invoices.date)>120 && DATEDIFF(NOW(),invoices.date)<=150')
            ->when($doctorID, function ($query) use ($doctorID) {
                $query->where('invoices.doctor_id', $doctorID);
            })
            ->when($patientID, function ($query) use ($patientID) {
                $query->where('invoices.patient_id', $patientID);
            })
            ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                $query->where('invoices.currency_id', $currencyID);
            })
            ->when($branchID, function ($query) use ($branchID) {
                $query->where('invoices.branch_id', $branchID);
            })
            ->when($coPayerID, function ($query) use ($coPayerID) {
                $query->where('invoices.co_payer_id', $coPayerID);
            })
            ->selectRaw('coalesce(sum(if(currency_id=' . $currencyID . ',coalesce(balance,0),if(xrate<1,coalesce(balance*xrate,0),coalesce(balance/xrate,0)))),0) as balance,sponsor')
            ->groupBy('sponsor')
            ->get();
        $oneHundredFiftyBalance = Invoice::where('balance', '>', 0)
            ->whereRaw('DATEDIFF(NOW(),invoices.date)>150 && DATEDIFF(NOW(),invoices.date)<=180')
            ->when($doctorID, function ($query) use ($doctorID) {
                $query->where('invoices.doctor_id', $doctorID);
            })
            ->when($patientID, function ($query) use ($patientID) {
                $query->where('invoices.patient_id', $patientID);
            })
            ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                $query->where('invoices.currency_id', $currencyID);
            })
            ->when($branchID, function ($query) use ($branchID) {
                $query->where('invoices.branch_id', $branchID);
            })
            ->when($coPayerID, function ($query) use ($coPayerID) {
                $query->where('invoices.co_payer_id', $coPayerID);
            })
            ->selectRaw('coalesce(sum(if(currency_id=' . $currencyID . ',coalesce(balance,0),if(xrate<1,coalesce(balance*xrate,0),coalesce(balance/xrate,0)))),0) as balance,sponsor')
            ->groupBy('sponsor')
            ->get();
        $oneHundredEightyBalance = Invoice::where('balance', '>', 0)
            ->whereRaw('DATEDIFF(NOW(),invoices.date)>180 && DATEDIFF(NOW(),invoices.date)<=355')
            ->when($doctorID, function ($query) use ($doctorID) {
                $query->where('invoices.doctor_id', $doctorID);
            })
            ->when($patientID, function ($query) use ($patientID) {
                $query->where('invoices.patient_id', $patientID);
            })
            ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                $query->where('invoices.currency_id', $currencyID);
            })
            ->when($branchID, function ($query) use ($branchID) {
                $query->where('invoices.branch_id', $branchID);
            })
            ->when($coPayerID, function ($query) use ($coPayerID) {
                $query->where('invoices.co_payer_id', $coPayerID);
            })
            ->selectRaw('coalesce(sum(if(currency_id=' . $currencyID . ',coalesce(balance,0),if(xrate<1,coalesce(balance*xrate,0),coalesce(balance/xrate,0)))),0) as balance,sponsor')
            ->groupBy('sponsor')
            ->get();
        $threeHundredFiftyFiveBalance = Invoice::where('balance', '>', 0)
            ->whereRaw('DATEDIFF(NOW(),invoices.date)>355')
            ->when($doctorID, function ($query) use ($doctorID) {
                $query->where('invoices.doctor_id', $doctorID);
            })
            ->when($patientID, function ($query) use ($patientID) {
                $query->where('invoices.patient_id', $patientID);
            })
            ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                $query->where('invoices.currency_id', $currencyID);
            })
            ->when($branchID, function ($query) use ($branchID) {
                $query->where('invoices.branch_id', $branchID);
            })
            ->when($coPayerID, function ($query) use ($coPayerID) {
                $query->where('invoices.co_payer_id', $coPayerID);
            })
            ->selectRaw('coalesce(sum(if(currency_id=' . $currencyID . ',coalesce(balance,0),if(xrate<1,coalesce(balance*xrate,0),coalesce(balance/xrate,0)))),0) as balance,sponsor')
            ->groupBy('sponsor')
            ->get();

        return [
            'current_balance' => [
                'cash' => (double)($currentBalance->where('sponsor', 'cash')->first()->balance ?? 0),
                'co_payer' => (double)($currentBalance->where('sponsor', 'co_payer')->first()->balance ?? 0),
            ],
            'thirty_balance' => [
                'cash' => (double)($thirtyBalance->where('sponsor', 'cash')->first()->balance ?? 0),
                'co_payer' => (double)($thirtyBalance->where('sponsor', 'co_payer')->first()->balance ?? 0),
            ],
            'sixty_balance' => [
                'cash' => (double)($sixtyBalance->where('sponsor', 'cash')->first()->balance ?? 0),
                'co_payer' => (double)($sixtyBalance->where('sponsor', 'co_payer')->first()->balance ?? 0),
            ],
            'ninety_balance' => [
                'cash' => (double)($ninetyBalance->where('sponsor', 'cash')->first()->balance ?? 0),
                'co_payer' => (double)($ninetyBalance->where('sponsor', 'co_payer')->first()->balance ?? 0),
            ],
            'one_hundred_twenty_balance' => [
                'cash' => (double)($oneHundredTwentyBalance->where('sponsor', 'cash')->first()->balance ?? 0),
                'co_payer' => (double)($oneHundredTwentyBalance->where('sponsor', 'co_payer')->first()->balance ?? 0),
            ],
            'one_hundred_fifty_balance' => [
                'cash' => (double)($oneHundredFiftyBalance->where('sponsor', 'cash')->first()->balance ?? 0),
                'co_payer' => (double)($oneHundredFiftyBalance->where('sponsor', 'co_payer')->first()->balance ?? 0),
            ],
            'one_hundred_eighty_balance' => [
                'cash' => (double)($oneHundredEightyBalance->where('sponsor', 'cash')->first()->balance ?? 0),
                'co_payer' => (double)($oneHundredEightyBalance->where('sponsor', 'co_payer')->first()->balance ?? 0),
            ],
            'three_hundred_fifty_five_balance' => [
                'cash' => (double)($threeHundredFiftyFiveBalance->where('sponsor', 'cash')->first()->balance ?? 0),
                'co_payer' => (double)($threeHundredFiftyFiveBalance->where('sponsor', 'co_payer')->first()->balance ?? 0),
            ],
        ];
    }

    public function getSummaryInsuranceAgingData($data = []): Collection|array|LengthAwarePaginator
    {
        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? '';
        $startTime = $data['start_time'] ?? '';
        $endTime = $data['end_time'] ?? '';
        $currencyID = $data['currency_id'] ?? '';
        $paginate = $data['paginate'] ?? false;
        $referringPractitionerID = $data['referring_practitioner_id'] ?? '';
        $createdByType = $data['created_by_type'] ?? '';
        $patientID = $data['patient_id'] ?? '';
        $doctorID = $data['doctor_id'] ?? '';
        $coPayerID = $data['co_payer_id'] ?? '';
        $status = $data['status'] ?? '';
        $branchID = $data['branch_id'] ?? '';
        $baseCurrency = Currency::find(Setting::where('setting_key', 'currency')->first()->setting_value);
        $operator = '/';
        if ($currencyID) {
            $currency = Currency::find($currencyID);
            if ($currency->xrate > $baseCurrency->xrate) {
                $operator = '/';
            } else {
                $operator = '*';
            }
            $currencyFromBase = false;
        } else {
            $currencyID = $baseCurrency->id;
            $currencyFromBase = true;
        }
        $query = CourseMaterial::selectSub(function ($query) use ($doctorID, $patientID, $currencyID, $currencyFromBase, $branchID, $coPayerID) {
            $query->selectRaw('coalesce(sum(if(currency_id=' . $currencyID . ',coalesce(balance,0),if(xrate<1,coalesce(balance*xrate,0),coalesce(balance/xrate,0)))),0)')
                ->from('invoices')
                ->whereColumn('invoices.co_payer_id', 'co_payers.id')
                ->whereRaw('DATEDIFF(NOW(),invoices.date)<=30')
                ->where('balance', '>', 0)
                ->when($doctorID, function ($query) use ($doctorID) {
                    $query->where('invoices.doctor_id', $doctorID);
                })
                ->when($patientID, function ($query) use ($patientID) {
                    $query->where('invoices.patient_id', $patientID);
                })
                ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                    $query->where('invoices.currency_id', $currencyID);
                })
                ->when($branchID, function ($query) use ($branchID) {
                    $query->where('invoices.branch_id', $branchID);
                })
                ->when($coPayerID, function ($query) use ($coPayerID) {
                    $query->where('invoices.co_payer_id', $coPayerID);
                })
                ->groupBy('co_payer_id')
                ->limit(1);
        }, 'current_balance')
            ->selectSub(function ($query) use ($doctorID, $patientID, $currencyID, $currencyFromBase, $branchID, $coPayerID) {
                $query->selectRaw('coalesce(sum(if(currency_id=' . $currencyID . ',coalesce(balance,0),if(xrate<1,coalesce(balance*xrate,0),coalesce(balance/xrate,0)))),0)')
                    ->from('invoices')
                    ->whereColumn('invoices.co_payer_id', 'co_payers.id')
                    ->where('balance', '>', 0)
                    ->whereRaw('DATEDIFF(NOW(),invoices.date)>30 && DATEDIFF(NOW(),invoices.date)<=60')
                    ->when($doctorID, function ($query) use ($doctorID) {
                        $query->where('invoices.doctor_id', $doctorID);
                    })
                    ->when($patientID, function ($query) use ($patientID) {
                        $query->where('invoices.patient_id', $patientID);
                    })
                    ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                        $query->where('invoices.currency_id', $currencyID);
                    })
                    ->when($branchID, function ($query) use ($branchID) {
                        $query->where('invoices.branch_id', $branchID);
                    })
                    ->groupBy('co_payer_id')
                    ->limit(1);
            }, 'thirty_balance')
            ->selectSub(function ($query) use ($doctorID, $patientID, $currencyID, $currencyFromBase, $branchID, $coPayerID) {
                $query->selectRaw('coalesce(sum(if(currency_id=' . $currencyID . ',coalesce(balance,0),if(xrate<1,coalesce(balance*xrate,0),coalesce(balance/xrate,0)))),0)')
                    ->from('invoices')
                    ->whereColumn('invoices.co_payer_id', 'co_payers.id')
                    ->where('balance', '>', 0)
                    ->whereRaw('DATEDIFF(NOW(),invoices.date)>60 && DATEDIFF(NOW(),invoices.date)<=90')
                    ->when($doctorID, function ($query) use ($doctorID) {
                        $query->where('invoices.doctor_id', $doctorID);
                    })
                    ->when($patientID, function ($query) use ($patientID) {
                        $query->where('invoices.patient_id', $patientID);
                    })
                    ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                        $query->where('invoices.currency_id', $currencyID);
                    })
                    ->when($branchID, function ($query) use ($branchID) {
                        $query->where('invoices.branch_id', $branchID);
                    })
                    ->when($coPayerID, function ($query) use ($coPayerID) {
                        $query->where('invoices.co_payer_id', $coPayerID);
                    })
                    ->groupBy('co_payer_id')
                    ->limit(1);
            }, 'sixty_balance')
            ->selectSub(function ($query) use ($doctorID, $patientID, $currencyID, $currencyFromBase, $branchID, $coPayerID) {
                $query->selectRaw('coalesce(sum(if(currency_id=' . $currencyID . ',coalesce(balance,0),if(xrate<1,coalesce(balance*xrate,0),coalesce(balance/xrate,0)))),0)')
                    ->from('invoices')
                    ->whereColumn('invoices.co_payer_id', 'co_payers.id')
                    ->where('balance', '>', 0)
                    ->whereRaw('DATEDIFF(NOW(),invoices.date)>90 && DATEDIFF(NOW(),invoices.date)<=120')
                    ->when($doctorID, function ($query) use ($doctorID) {
                        $query->where('invoices.doctor_id', $doctorID);
                    })
                    ->when($patientID, function ($query) use ($patientID) {
                        $query->where('invoices.patient_id', $patientID);
                    })
                    ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                        $query->where('invoices.currency_id', $currencyID);
                    })
                    ->when($branchID, function ($query) use ($branchID) {
                        $query->where('invoices.branch_id', $branchID);
                    })
                    ->when($coPayerID, function ($query) use ($coPayerID) {
                        $query->where('invoices.co_payer_id', $coPayerID);
                    })
                    ->groupBy('co_payer_id')
                    ->limit(1);
            }, 'ninety_balance')
            ->selectSub(function ($query) use ($doctorID, $patientID, $currencyID, $currencyFromBase, $branchID, $coPayerID) {
                $query->selectRaw('coalesce(sum(if(currency_id=' . $currencyID . ',coalesce(balance,0),if(xrate<1,coalesce(balance*xrate,0),coalesce(balance/xrate,0)))),0)')
                    ->from('invoices')
                    ->whereColumn('invoices.co_payer_id', 'co_payers.id')
                    ->where('balance', '>', 0)
                    ->whereRaw('DATEDIFF(NOW(),invoices.date)>120 && DATEDIFF(NOW(),invoices.date)<=150')
                    ->when($doctorID, function ($query) use ($doctorID) {
                        $query->where('invoices.doctor_id', $doctorID);
                    })
                    ->when($patientID, function ($query) use ($patientID) {
                        $query->where('invoices.patient_id', $patientID);
                    })
                    ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                        $query->where('invoices.currency_id', $currencyID);
                    })
                    ->when($branchID, function ($query) use ($branchID) {
                        $query->where('invoices.branch_id', $branchID);
                    })
                    ->when($coPayerID, function ($query) use ($coPayerID) {
                        $query->where('invoices.co_payer_id', $coPayerID);
                    })
                    ->groupBy('co_payer_id')
                    ->limit(1);
            }, 'one_hundred_twenty_balance')
            ->selectSub(function ($query) use ($doctorID, $patientID, $currencyID, $currencyFromBase, $branchID, $coPayerID) {
                $query->selectRaw('coalesce(sum(if(currency_id=' . $currencyID . ',coalesce(balance,0),if(xrate<1,coalesce(balance*xrate,0),coalesce(balance/xrate,0)))),0)')
                    ->from('invoices')
                    ->whereColumn('invoices.co_payer_id', 'co_payers.id')
                    ->where('balance', '>', 0)
                    ->whereRaw('DATEDIFF(NOW(),invoices.date)>150 && DATEDIFF(NOW(),invoices.date)<=180')
                    ->when($doctorID, function ($query) use ($doctorID) {
                        $query->where('invoices.doctor_id', $doctorID);
                    })
                    ->when($patientID, function ($query) use ($patientID) {
                        $query->where('invoices.patient_id', $patientID);
                    })
                    ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                        $query->where('invoices.currency_id', $currencyID);
                    })
                    ->when($branchID, function ($query) use ($branchID) {
                        $query->where('invoices.branch_id', $branchID);
                    })
                    ->when($coPayerID, function ($query) use ($coPayerID) {
                        $query->where('invoices.co_payer_id', $coPayerID);
                    })
                    ->groupBy('co_payer_id')
                    ->limit(1);
            }, 'one_hundred_fifty_balance')
            ->selectSub(function ($query) use ($doctorID, $patientID, $currencyID, $currencyFromBase, $branchID, $coPayerID) {
                $query->selectRaw('coalesce(sum(if(currency_id=' . $currencyID . ',coalesce(balance,0),if(xrate<1,coalesce(balance*xrate,0),coalesce(balance/xrate,0)))),0)')
                    ->from('invoices')
                    ->whereColumn('invoices.co_payer_id', 'co_payers.id')
                    ->where('balance', '>', 0)
                    ->whereRaw('DATEDIFF(NOW(),invoices.date)>180 && DATEDIFF(NOW(),invoices.date)<=355')
                    ->when($doctorID, function ($query) use ($doctorID) {
                        $query->where('invoices.doctor_id', $doctorID);
                    })
                    ->when($patientID, function ($query) use ($patientID) {
                        $query->where('invoices.patient_id', $patientID);
                    })
                    ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                        $query->where('invoices.currency_id', $currencyID);
                    })
                    ->when($branchID, function ($query) use ($branchID) {
                        $query->where('invoices.branch_id', $branchID);
                    })
                    ->when($coPayerID, function ($query) use ($coPayerID) {
                        $query->where('invoices.co_payer_id', $coPayerID);
                    })
                    ->groupBy('co_payer_id')
                    ->limit(1);
            }, 'one_hundred_eighty_balance')
            ->selectSub(function ($query) use ($doctorID, $patientID, $currencyID, $currencyFromBase, $branchID, $coPayerID) {
                $query->selectRaw('coalesce(sum(if(currency_id=' . $currencyID . ',coalesce(balance,0),if(xrate<1,coalesce(balance*xrate,0),coalesce(balance/xrate,0)))),0)')
                    ->from('invoices')
                    ->whereColumn('invoices.co_payer_id', 'co_payers.id')
                    ->where('balance', '>', 0)
                    ->whereRaw('DATEDIFF(NOW(),invoices.date)>355')
                    ->when($doctorID, function ($query) use ($doctorID) {
                        $query->where('invoices.doctor_id', $doctorID);
                    })
                    ->when($patientID, function ($query) use ($patientID) {
                        $query->where('invoices.patient_id', $patientID);
                    })
                    ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                        $query->where('invoices.currency_id', $currencyID);
                    })
                    ->when($branchID, function ($query) use ($branchID) {
                        $query->where('invoices.branch_id', $branchID);
                    })
                    ->when($coPayerID, function ($query) use ($coPayerID) {
                        $query->where('invoices.co_payer_id', $coPayerID);
                    })
                    ->groupBy('co_payer_id')
                    ->limit(1);
            }, 'three_hundred_fifty_five_balance')
            ->selectRaw('co_payers.*')
            ->when($coPayerID, function ($query) use ($coPayerID) {
                $query->where('co_payers.id', $coPayerID);
            });

        if (!empty($paginate)) {
            $results = $query->paginate();

        } else {

            $results = $query->get();
        }
        return $results;
    }

    public function getSummaryPatientBalanceAgingData($data = []): Collection|array|LengthAwarePaginator
    {
        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? '';
        $startTime = $data['start_time'] ?? '';
        $endTime = $data['end_time'] ?? '';
        $currencyID = $data['currency_id'] ?? '';
        $paginate = $data['paginate'] ?? false;
        $referringPractitionerID = $data['referring_practitioner_id'] ?? '';
        $createdByType = $data['created_by_type'] ?? '';
        $patientID = $data['patient_id'] ?? '';
        $doctorID = $data['doctor_id'] ?? '';
        $coPayerID = $data['co_payer_id'] ?? '';
        $status = $data['status'] ?? '';
        $branchID = $data['branch_id'] ?? '';
        $baseCurrency = Currency::find(Setting::where('setting_key', 'currency')->first()->setting_value);
        $operator = '/';
        if ($currencyID) {
            $currency = Currency::find($currencyID);
            if ($currency->xrate > $baseCurrency->xrate) {
                $operator = '/';
            } else {
                $operator = '*';
            }
            $currencyFromBase = false;
        } else {
            $currencyID = $baseCurrency->id;
            $currencyFromBase = true;
        }
        $query = Client::selectSub(function ($query) use ($doctorID, $patientID, $currencyID, $currencyFromBase, $branchID, $coPayerID) {
            $query->selectRaw('coalesce(sum(if(currency_id=' . $currencyID . ',coalesce(balance,0),if(xrate<1,coalesce(balance*xrate,0),coalesce(balance/xrate,0)))),0)')
                ->from('invoices')
                ->whereColumn('invoices.patient_id', 'patients.id')
                ->whereRaw('DATEDIFF(NOW(),invoices.date)<=30')
                ->where('balance', '>', 0)
                ->when($doctorID, function ($query) use ($doctorID) {
                    $query->where('invoices.doctor_id', $doctorID);
                })
                ->when($patientID, function ($query) use ($patientID) {
                    $query->where('invoices.patient_id', $patientID);
                })
                ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                    $query->where('invoices.currency_id', $currencyID);
                })
                ->when($branchID, function ($query) use ($branchID) {
                    $query->where('invoices.branch_id', $branchID);
                })
                ->when($coPayerID, function ($query) use ($coPayerID) {
                    $query->where('invoices.co_payer_id', $coPayerID);
                })
                ->groupBy('patient_id')
                ->limit(1);
        }, 'current_balance')
            ->selectSub(function ($query) use ($doctorID, $patientID, $currencyID, $currencyFromBase, $branchID, $coPayerID) {
                $query->selectRaw('coalesce(sum(if(currency_id=' . $currencyID . ',coalesce(balance,0),if(xrate<1,coalesce(balance*xrate,0),coalesce(balance/xrate,0)))),0)')
                    ->from('invoices')
                    ->whereColumn('invoices.patient_id', 'patients.id')
                    ->where('balance', '>', 0)
                    ->whereRaw('DATEDIFF(NOW(),invoices.date)>30 && DATEDIFF(NOW(),invoices.date)<=60')
                    ->when($doctorID, function ($query) use ($doctorID) {
                        $query->where('invoices.doctor_id', $doctorID);
                    })
                    ->when($patientID, function ($query) use ($patientID) {
                        $query->where('invoices.patient_id', $patientID);
                    })
                    ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                        $query->where('invoices.currency_id', $currencyID);
                    })
                    ->when($branchID, function ($query) use ($branchID) {
                        $query->where('invoices.branch_id', $branchID);
                    })
                    ->groupBy('patient_id')
                    ->limit(1);
            }, 'thirty_balance')
            ->selectSub(function ($query) use ($doctorID, $patientID, $currencyID, $currencyFromBase, $branchID, $coPayerID) {
                $query->selectRaw('coalesce(sum(if(currency_id=' . $currencyID . ',coalesce(balance,0),if(xrate<1,coalesce(balance*xrate,0),coalesce(balance/xrate,0)))),0)')
                    ->from('invoices')
                    ->whereColumn('invoices.patient_id', 'patients.id')
                    ->where('balance', '>', 0)
                    ->whereRaw('DATEDIFF(NOW(),invoices.date)>60 && DATEDIFF(NOW(),invoices.date)<=90')
                    ->when($doctorID, function ($query) use ($doctorID) {
                        $query->where('invoices.doctor_id', $doctorID);
                    })
                    ->when($patientID, function ($query) use ($patientID) {
                        $query->where('invoices.patient_id', $patientID);
                    })
                    ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                        $query->where('invoices.currency_id', $currencyID);
                    })
                    ->when($branchID, function ($query) use ($branchID) {
                        $query->where('invoices.branch_id', $branchID);
                    })
                    ->when($coPayerID, function ($query) use ($coPayerID) {
                        $query->where('invoices.co_payer_id', $coPayerID);
                    })
                    ->groupBy('patient_id')
                    ->limit(1);
            }, 'sixty_balance')
            ->selectSub(function ($query) use ($doctorID, $patientID, $currencyID, $currencyFromBase, $branchID, $coPayerID) {
                $query->selectRaw('coalesce(sum(if(currency_id=' . $currencyID . ',coalesce(balance,0),if(xrate<1,coalesce(balance*xrate,0),coalesce(balance/xrate,0)))),0)')
                    ->from('invoices')
                    ->whereColumn('invoices.patient_id', 'patients.id')
                    ->where('balance', '>', 0)
                    ->whereRaw('DATEDIFF(NOW(),invoices.date)>90 && DATEDIFF(NOW(),invoices.date)<=120')
                    ->when($doctorID, function ($query) use ($doctorID) {
                        $query->where('invoices.doctor_id', $doctorID);
                    })
                    ->when($patientID, function ($query) use ($patientID) {
                        $query->where('invoices.patient_id', $patientID);
                    })
                    ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                        $query->where('invoices.currency_id', $currencyID);
                    })
                    ->when($branchID, function ($query) use ($branchID) {
                        $query->where('invoices.branch_id', $branchID);
                    })
                    ->when($coPayerID, function ($query) use ($coPayerID) {
                        $query->where('invoices.co_payer_id', $coPayerID);
                    })
                    ->groupBy('patient_id')
                    ->limit(1);
            }, 'ninety_balance')
            ->selectSub(function ($query) use ($doctorID, $patientID, $currencyID, $currencyFromBase, $branchID, $coPayerID) {
                $query->selectRaw('coalesce(sum(if(currency_id=' . $currencyID . ',coalesce(balance,0),if(xrate<1,coalesce(balance*xrate,0),coalesce(balance/xrate,0)))),0)')
                    ->from('invoices')
                    ->whereColumn('invoices.patient_id', 'patients.id')
                    ->where('balance', '>', 0)
                    ->whereRaw('DATEDIFF(NOW(),invoices.date)>120 && DATEDIFF(NOW(),invoices.date)<=150')
                    ->when($doctorID, function ($query) use ($doctorID) {
                        $query->where('invoices.doctor_id', $doctorID);
                    })
                    ->when($patientID, function ($query) use ($patientID) {
                        $query->where('invoices.patient_id', $patientID);
                    })
                    ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                        $query->where('invoices.currency_id', $currencyID);
                    })
                    ->when($branchID, function ($query) use ($branchID) {
                        $query->where('invoices.branch_id', $branchID);
                    })
                    ->when($coPayerID, function ($query) use ($coPayerID) {
                        $query->where('invoices.co_payer_id', $coPayerID);
                    })
                    ->groupBy('patient_id')
                    ->limit(1);
            }, 'one_hundred_twenty_balance')
            ->selectSub(function ($query) use ($doctorID, $patientID, $currencyID, $currencyFromBase, $branchID, $coPayerID) {
                $query->selectRaw('coalesce(sum(if(currency_id=' . $currencyID . ',coalesce(balance,0),if(xrate<1,coalesce(balance*xrate,0),coalesce(balance/xrate,0)))),0)')
                    ->from('invoices')
                    ->whereColumn('invoices.patient_id', 'patients.id')
                    ->where('balance', '>', 0)
                    ->whereRaw('DATEDIFF(NOW(),invoices.date)>150 && DATEDIFF(NOW(),invoices.date)<=180')
                    ->when($doctorID, function ($query) use ($doctorID) {
                        $query->where('invoices.doctor_id', $doctorID);
                    })
                    ->when($patientID, function ($query) use ($patientID) {
                        $query->where('invoices.patient_id', $patientID);
                    })
                    ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                        $query->where('invoices.currency_id', $currencyID);
                    })
                    ->when($branchID, function ($query) use ($branchID) {
                        $query->where('invoices.branch_id', $branchID);
                    })
                    ->when($coPayerID, function ($query) use ($coPayerID) {
                        $query->where('invoices.co_payer_id', $coPayerID);
                    })
                    ->groupBy('patient_id')
                    ->limit(1);
            }, 'one_hundred_fifty_balance')
            ->selectSub(function ($query) use ($doctorID, $patientID, $currencyID, $currencyFromBase, $branchID, $coPayerID) {
                $query->selectRaw('coalesce(sum(if(currency_id=' . $currencyID . ',coalesce(balance,0),if(xrate<1,coalesce(balance*xrate,0),coalesce(balance/xrate,0)))),0)')
                    ->from('invoices')
                    ->whereColumn('invoices.patient_id', 'patients.id')
                    ->where('balance', '>', 0)
                    ->whereRaw('DATEDIFF(NOW(),invoices.date)>180 && DATEDIFF(NOW(),invoices.date)<=355')
                    ->when($doctorID, function ($query) use ($doctorID) {
                        $query->where('invoices.doctor_id', $doctorID);
                    })
                    ->when($patientID, function ($query) use ($patientID) {
                        $query->where('invoices.patient_id', $patientID);
                    })
                    ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                        $query->where('invoices.currency_id', $currencyID);
                    })
                    ->when($branchID, function ($query) use ($branchID) {
                        $query->where('invoices.branch_id', $branchID);
                    })
                    ->when($coPayerID, function ($query) use ($coPayerID) {
                        $query->where('invoices.co_payer_id', $coPayerID);
                    })
                    ->groupBy('patient_id')
                    ->limit(1);
            }, 'one_hundred_eighty_balance')
            ->selectSub(function ($query) use ($doctorID, $patientID, $currencyID, $currencyFromBase, $branchID, $coPayerID) {
                $query->selectRaw('coalesce(sum(if(currency_id=' . $currencyID . ',coalesce(balance,0),if(xrate<1,coalesce(balance*xrate,0),coalesce(balance/xrate,0)))),0)')
                    ->from('invoices')
                    ->whereColumn('invoices.patient_id', 'patients.id')
                    ->where('balance', '>', 0)
                    ->whereRaw('DATEDIFF(NOW(),invoices.date)>355')
                    ->when($doctorID, function ($query) use ($doctorID) {
                        $query->where('invoices.doctor_id', $doctorID);
                    })
                    ->when($patientID, function ($query) use ($patientID) {
                        $query->where('invoices.patient_id', $patientID);
                    })
                    ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                        $query->where('invoices.currency_id', $currencyID);
                    })
                    ->when($branchID, function ($query) use ($branchID) {
                        $query->where('invoices.branch_id', $branchID);
                    })
                    ->when($coPayerID, function ($query) use ($coPayerID) {
                        $query->where('invoices.co_payer_id', $coPayerID);
                    })
                    ->groupBy('patient_id')
                    ->limit(1);
            }, 'three_hundred_fifty_five_balance')
            ->selectRaw('patients.*')
            ->when($patientID, function ($query) use ($patientID) {
                $query->where('patients.id', $patientID);
            })
            ->havingRaw("three_hundred_fifty_five_balance>0 or one_hundred_eighty_balance>0 or one_hundred_fifty_balance>0 or one_hundred_twenty_balance>0 or ninety_balance>0 or sixty_balance>0 or thirty_balance>0 or current_balance>0");

        if (!empty($paginate)) {
            $results = $query->paginate();

        } else {

            $results = $query->get();
        }
        return $results;
    }

    public function getProductsReport($data = []): Collection|array|LengthAwarePaginator
    {
        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? '';
        $inventoryWarehouseID = $data['inventory_warehouse_id'] ?? '';
        $inventoryCategoryID = $data['inventory_category_id'] ?? '';
        $inventorySubCategoryID = $data['inventory_sub_category_id'] ?? '';
        $currencyID = $data['currency_id'] ?? '';
        $productType = $data['product_type'] ?? '';
        $brandID = $data['inventory_product_brand_id'] ?? '';
        $featured = $data['featured'] ?? '';
        $active = $data['active'] ?? '';
        $branchID = $data['branch_id'] ?? '';
        $search = $data['search'] ?? '';
        $paginate = $data['paginate'] ?? false;
        $baseCurrency = Currency::find(Setting::where('setting_key', 'currency')->first()->setting_value);
        $operator = '/';
        if ($currencyID) {
            $currency = Currency::find($currencyID);
            if ($currency->xrate > $baseCurrency->xrate) {
                $operator = '/';
            } else {
                $operator = '*';
            }
            $currencyFromBase = false;
        } else {
            $currencyID = $baseCurrency->id;
            $currencyFromBase = true;
        }
        $query = InventoryProduct::leftJoin('inventory_product_brands', 'inventory_product_brands.id', 'inventory_products.inventory_product_brand_id')
            ->leftJoin('inventory_product_categories', 'inventory_product_categories.id', 'inventory_products.inventory_product_category_id')
            ->leftJoin('inventory_product_categories as subcategories', 'subcategories.id', 'inventory_products.inventory_product_subcategory_id')
            ->leftJoin('inventory_product_units', 'inventory_product_units.id', 'inventory_products.inventory_product_unit_id')
            ->leftJoin('inventory_product_barcode_types', 'inventory_product_barcode_types.id', 'inventory_products.inventory_product_barcode_type_id')
            ->leftJoin('tax_rates', 'tax_rates.id', 'inventory_products.tax_rate_id')
            ->leftJoin('inventory_product_variations', 'inventory_products.id', 'inventory_product_variations.inventory_product_id')
            ->selectRaw("inventory_products.*,inventory_product_categories.name category,subcategories.name subcategory,inventory_product_brands.name brand,inventory_product_units.name unit,inventory_product_barcode_types.name barcode_type,tax_rates.name tax_rate,min(inventory_product_variations.purchase_price) min_purchase_price,max(inventory_product_variations.purchase_price) max_purchase_price,min(inventory_product_variations.default_selling_price) min_default_selling_price,max(inventory_product_variations.default_selling_price) max_default_selling_price")
            ->selectSub(function ($query) use ($inventoryWarehouseID) {
                $query->selectRaw('coalesce(sum(quantity),0)')
                    ->from('inventory_product_stock')
                    ->whereColumn('inventory_product_stock.inventory_product_id', 'inventory_products.id')
                    ->when($inventoryWarehouseID, function ($query) use ($inventoryWarehouseID) {
                        $query->where('inventory_product_stock.inventory_warehouse_id', $inventoryWarehouseID);
                    })
                    ->limit(1);
            }, 'stock_quantity')
            ->selectSub(function ($query) use ($inventoryWarehouseID, $startDate, $endDate, $currencyID) {
                $query->selectRaw('coalesce(sum(quantity),0)')
                    ->from('inventory_product_purchase_items')
                    ->leftJoin('inventory_product_purchases', 'inventory_product_purchases.id', 'inventory_product_purchase_items.inventory_product_purchase_id')
                    ->whereColumn('inventory_product_purchase_items.inventory_product_id', 'inventory_products.id')
                    ->when($inventoryWarehouseID, function ($query) use ($inventoryWarehouseID) {
                        $query->where('inventory_product_purchases.inventory_warehouse_id', $inventoryWarehouseID);
                    })
                    ->when($currencyID, function ($query) use ($currencyID) {
                        $query->where('inventory_product_purchases.currency_id', $currencyID);
                    })
                    ->when(($startDate && $endDate), function ($query) use ($startDate, $endDate) {
                        $query->whereBetween('inventory_product_purchases.purchase_date', [$startDate, $endDate]);
                    })
                    ->limit(1);
            }, 'purchased_quantity')
            ->selectSub(function ($query) use ($inventoryWarehouseID, $startDate, $endDate, $currencyID) {
                $query->selectRaw('coalesce(sum(if(inventory_product_purchase_items.xrate>1,coalesce(inventory_product_purchase_items.total/inventory_product_purchase_items.xrate,0),coalesce(inventory_product_purchase_items.total*inventory_product_purchase_items.xrate,0))),0)')
                    ->from('inventory_product_purchase_items')
                    ->leftJoin('inventory_product_purchases', 'inventory_product_purchases.id', 'inventory_product_purchase_items.inventory_product_purchase_id')
                    ->whereColumn('inventory_product_purchase_items.inventory_product_id', 'inventory_products.id')
                    ->when($inventoryWarehouseID, function ($query) use ($inventoryWarehouseID) {
                        $query->where('inventory_product_purchases.inventory_warehouse_id', $inventoryWarehouseID);
                    })
                    ->when($currencyID, function ($query) use ($currencyID) {
                        $query->where('inventory_product_purchases.currency_id', $currencyID);
                    })
                    ->when(($startDate && $endDate), function ($query) use ($startDate, $endDate) {
                        $query->whereBetween('inventory_product_purchases.purchase_date', [$startDate, $endDate]);
                    })
                    ->limit(1);
            }, 'total_purchase_price')
            ->selectSub(function ($query) use ($inventoryWarehouseID, $startDate, $endDate, $currencyID) {
                $query->selectRaw('coalesce(sum(quantity),0)')
                    ->from('inventory_product_sale_items')
                    ->leftJoin('inventory_product_sales', 'inventory_product_sales.id', 'inventory_product_sale_items.inventory_product_sale_id')
                    ->whereColumn('inventory_product_sale_items.inventory_product_id', 'inventory_products.id')
                    ->when($inventoryWarehouseID, function ($query) use ($inventoryWarehouseID) {
                        $query->where('inventory_product_sales.inventory_warehouse_id', $inventoryWarehouseID);
                    })
                    ->when($currencyID, function ($query) use ($currencyID) {
                        $query->where('inventory_product_sales.currency_id', $currencyID);
                    })
                    ->when(($startDate && $endDate), function ($query) use ($startDate, $endDate) {
                        $query->whereBetween('inventory_product_sales.sale_date', [$startDate, $endDate]);
                    })
                    ->limit(1);
            }, 'sales_quantity')
            ->selectSub(function ($query) use ($inventoryWarehouseID, $startDate, $endDate, $currencyID) {
                $query->selectRaw('coalesce(sum(if(inventory_product_sale_items.xrate>1,coalesce(inventory_product_sale_items.total/inventory_product_sale_items.xrate,0),coalesce(inventory_product_sale_items.total*inventory_product_sale_items.xrate,0))),0)')
                    ->from('inventory_product_sale_items')
                    ->leftJoin('inventory_product_sales', 'inventory_product_sales.id', 'inventory_product_sale_items.inventory_product_sale_id')
                    ->whereColumn('inventory_product_sale_items.inventory_product_id', 'inventory_products.id')
                    ->when($inventoryWarehouseID, function ($query) use ($inventoryWarehouseID) {
                        $query->where('inventory_product_sales.inventory_warehouse_id', $inventoryWarehouseID);
                    })
                    ->when($currencyID, function ($query) use ($currencyID) {
                        $query->where('inventory_product_sales.currency_id', $currencyID);
                    })
                    ->when(($startDate && $endDate), function ($query) use ($startDate, $endDate) {
                        $query->whereBetween('inventory_product_sales.sale_date', [$startDate, $endDate]);
                    })
                    ->limit(1);
            }, 'total_sales_price')
            ->when($inventoryCategoryID, function ($query) use ($inventoryCategoryID) {
                $query->where('inventory_products.inventory_product_category_id', $inventoryCategoryID);
            })
            ->when($productType, function ($query) use ($productType) {
                $query->where('inventory_products.product_type', $productType);
            })
            ->when($inventorySubCategoryID, function ($query) use ($inventorySubCategoryID) {
                $query->where('inventory_products.inventory_product_subcategory_id', $inventorySubCategoryID);
            })
            ->when($brandID, function ($query) use ($brandID) {
                $query->where('inventory_products.inventory_product_brand_id', $brandID);
            })
            ->when($search, function ($query) use ($search) {
                $query->where('inventory_products.name', 'like', '%' . $search . '%');
                $query->orWhere('inventory_products.sku', 'like', '%' . $search . '%');
                $query->orWhere('inventory_products.description', 'like', '%' . $search . '%');
                $query->orWhere('inventory_product_variations.name', 'like', '%' . $search . '%');
                $query->orWhere('inventory_product_variations.sku', 'like', '%' . $search . '%');
            })
            ->groupBy('inventory_products.id');
        if (!empty($paginate)) {
            $results = $query->paginate();

        } else {

            $results = $query->get();
        }
        return $results;
    }

    public function getTopSellingProductsReport($data = []): Collection|array|LengthAwarePaginator
    {
        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? '';
        $inventoryWarehouseID = $data['inventory_warehouse_id'] ?? '';
        $inventoryCategoryID = $data['inventory_category_id'] ?? '';
        $inventorySubCategoryID = $data['inventory_sub_category_id'] ?? '';
        $currencyID = $data['currency_id'] ?? '';
        $productType = $data['product_type'] ?? '';
        $brandID = $data['inventory_product_brand_id'] ?? '';
        $featured = $data['featured'] ?? '';
        $active = $data['active'] ?? '';
        $branchID = $data['branch_id'] ?? '';
        $search = $data['search'] ?? '';
        $orderBy = $data['order_by'] ?? 'quantity';
        $paginate = $data['paginate'] ?? false;
        $baseCurrency = Currency::find(Setting::where('setting_key', 'currency')->first()->setting_value);
        $operator = '/';
        if ($currencyID) {
            $currency = Currency::find($currencyID);
            if ($currency->xrate > $baseCurrency->xrate) {
                $operator = '/';
            } else {
                $operator = '*';
            }
            $currencyFromBase = false;
        } else {
            $currencyID = $baseCurrency->id;
            $currencyFromBase = true;
        }
        $query = InventoryProductSaleItem::leftJoin('inventory_products', 'inventory_products.id', 'inventory_product_sale_items.inventory_product_id')
            ->leftJoin('inventory_product_sales', 'inventory_product_sales.id', 'inventory_product_sale_items.inventory_product_sale_id')
            ->leftJoin('inventory_product_variations', 'inventory_product_variations.id', 'inventory_product_sale_items.inventory_product_variation_id')
            ->leftJoin('inventory_product_brands', 'inventory_product_brands.id', 'inventory_products.inventory_product_brand_id')
            ->leftJoin('inventory_product_categories', 'inventory_product_categories.id', 'inventory_products.inventory_product_category_id')
            ->leftJoin('inventory_product_categories as subcategories', 'subcategories.id', 'inventory_products.inventory_product_subcategory_id')
            ->leftJoin('inventory_product_units', 'inventory_product_units.id', 'inventory_products.inventory_product_unit_id')
            ->leftJoin('inventory_product_barcode_types', 'inventory_product_barcode_types.id', 'inventory_products.inventory_product_barcode_type_id')
            ->leftJoin('tax_rates', 'tax_rates.id', 'inventory_products.tax_rate_id')
            ->selectRaw("inventory_product_variations.name name,inventory_product_variations.sku sku,sum(inventory_product_sale_items.quantity) as quantity,coalesce(sum(if(inventory_product_sale_items.xrate>1,coalesce(inventory_product_sale_items.total/inventory_product_sale_items.xrate,0),coalesce(inventory_product_sale_items.total*inventory_product_sale_items.xrate,0))),0) as amount")
            ->selectSub(function ($query) use ($inventoryWarehouseID) {
                $query->selectRaw('coalesce(sum(quantity),0)')
                    ->from('inventory_product_stock')
                    ->whereColumn('inventory_product_stock.inventory_product_variation_id', 'inventory_product_variations.id')
                    ->when($inventoryWarehouseID, function ($query) use ($inventoryWarehouseID) {
                        $query->where('inventory_product_stock.inventory_warehouse_id', $inventoryWarehouseID);
                    })
                    ->limit(1);
            }, 'stock_quantity')
            ->when(($startDate && $endDate), function ($query) use ($startDate, $endDate) {
                $query->whereBetween('inventory_product_sales.sale_date', [$startDate, $endDate]);
            })
            ->when($inventoryCategoryID, function ($query) use ($inventoryCategoryID) {
                $query->where('inventory_products.inventory_product_category_id', $inventoryCategoryID);
            })
            ->when($productType, function ($query) use ($productType) {
                $query->where('inventory_products.product_type', $productType);
            })
            ->when($inventorySubCategoryID, function ($query) use ($inventorySubCategoryID) {
                $query->where('inventory_products.inventory_product_subcategory_id', $inventorySubCategoryID);
            })
            ->when($brandID, function ($query) use ($brandID) {
                $query->where('inventory_products.inventory_product_brand_id', $brandID);
            })
            ->when($search, function ($query) use ($search) {
                $query->where('inventory_products.name', 'like', '%' . $search . '%');
                $query->orWhere('inventory_products.sku', 'like', '%' . $search . '%');
                $query->orWhere('inventory_products.description', 'like', '%' . $search . '%');
                $query->orWhere('inventory_product_variations.name', 'like', '%' . $search . '%');
                $query->orWhere('inventory_product_variations.sku', 'like', '%' . $search . '%');
            })
            ->groupBy('inventory_product_sale_items.inventory_product_variation_id')
            ->orderBy($orderBy, 'desc')
            ->limit(10);
        if (!empty($paginate)) {
            $results = $query->paginate();

        } else {

            $results = $query->get();
        }
        return $results;
    }

    public function getSalesReport($data = []): Collection|array|LengthAwarePaginator
    {
        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? '';
        $paymentTypeID = $data['payment_type_id'] ?? '';
        $inventoryWarehouseID = $data['inventory_warehouse_id'] ?? '';
        $shippingStatus = $data['shipping_status'] ?? '';
        $paymentStatus = $data['payment_status'] ?? '';
        $currencyID = $data['currency_id'] ?? '';
        $status = $data['status'] ?? '';
        $patientID = $data['patient_id'] ?? '';
        $sponsor = $data['sponsor'] ?? '';
        $saleType = $data['sale_type'] ?? '';
        $branchID = $data['branch_id'] ?? '';
        $coPayerID = $data['co_payer_id'] ?? '';
        $search = $data['search'] ?? '';
        $orderBy = $data['order_by'] ?? '';
        $paginate = $data['paginate'] ?? false;
        $baseCurrency = Currency::find(Setting::where('setting_key', 'currency')->first()->setting_value);
        $operator = '/';
        if ($currencyID) {
            $currency = Currency::find($currencyID);
            if ($currency->xrate > $baseCurrency->xrate) {
                $operator = '/';
            } else {
                $operator = '*';
            }
            $currencyFromBase = false;
        } else {
            //$currencyID = $baseCurrency->id;
            $currencyFromBase = true;
        }
        $query = InventoryProductSale::with(['patient', 'createdBy', 'currency', 'warehouse', 'invoice', 'items'])
            ->when($branchID, function ($query) use ($branchID) {
                $query->where('branch_id', $branchID);
            })
            ->when($currencyID, function ($query) use ($currencyID) {
                $query->where('inventory_product_sales.currency_id', $currencyID);
            })
            ->when($status, function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->when($paymentStatus, function ($query) use ($paymentStatus) {
                $query->whereHas('invoice', function ($query) use ($paymentStatus) {
                    $query->where('status', $paymentStatus);
                });
            })
            ->when($sponsor, function ($query) use ($sponsor) {
                $query->where('sponsor', $sponsor);
            })
            ->when($shippingStatus, function ($query) use ($shippingStatus) {
                $query->where('shipping_status', $shippingStatus);
            })
            ->when($saleType, function ($query) use ($saleType) {
                $query->where('sale_type', $saleType);
            })
            ->when($coPayerID, function ($query) use ($coPayerID) {
                $query->where('co_payer_id', $coPayerID);
            })
            ->when($patientID, function ($query) use ($patientID) {
                $query->where('patient_id', $patientID);
            })
            ->when($inventoryWarehouseID, function ($query) use ($inventoryWarehouseID) {
                $query->where('inventory_warehouse_id', $inventoryWarehouseID);
            })
            ->when(($startDate && $endDate), function ($query) use ($startDate, $endDate) {
                $query->whereBetween('sale_date', [$startDate, $endDate]);
            })
            ->when($paymentTypeID, function ($query) use ($paymentTypeID) {
                $query->whereHas('paymentDetail', function ($query) use ($paymentTypeID) {
                    $query->where('payment_type_id', $paymentTypeID);
                });
            })
            ->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('id', 'like', '%' . $search . '%');
                    $query->orWhere('reference', 'like', '%' . $search . '%');
                    $query->orWhereHas('patient', function ($query) use ($search) {
                        $query->where('id', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%')
                            ->orWhere('mobile', 'like', '%' . $search . '%')
                            ->orWhere('first_name', 'like', '%' . $search . '%')
                            ->orWhere('external_id', 'like', '%' . $search . '%')
                            ->orWhere('last_name', 'like', '%' . $search . '%')
                            ->orWhere('middle_name', 'like', '%' . $search . '%');
                    })->orWhereHas('coPayer', function ($query) use ($search) {
                        $query->where('name', 'like', '%' . $search . '%');
                    });
                });
            })
            ->orderBy($orderBy, 'desc');
        if (!empty($paginate)) {
            $results = $query->paginate();

        } else {

            $results = $query->get();
        }
        return $results;
    }

    public function getPurchasesReport($data = []): Collection|array|LengthAwarePaginator
    {

        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? '';
        $inventoryWarehouseID = $data['inventory_warehouse_id'] ?? '';
        $shippingStatus = $data['shipping_status'] ?? '';
        $paymentStatus = $data['payment_status'] ?? '';
        $currencyID = $data['currency_id'] ?? '';
        $status = $data['status'] ?? '';
        $supplierID = $data['supplier_id'] ?? '';
        $saleType = $data['sale_type'] ?? '';
        $branchID = $data['branch_id'] ?? '';
        $search = $data['search'] ?? '';
        $orderBy = $data['order_by'] ?? '';
        $paginate = $data['paginate'] ?? false;
        $baseCurrency = Currency::find(Setting::where('setting_key', 'currency')->first()->setting_value);
        $operator = '/';
        if ($currencyID) {
            $currency = Currency::find($currencyID);
            if ($currency->xrate > $baseCurrency->xrate) {
                $operator = '/';
            } else {
                $operator = '*';
            }
            $currencyFromBase = false;
        } else {
            //$currencyID = $baseCurrency->id;
            $currencyFromBase = true;
        }
        $query = InventoryProductPurchase::with(['supplier', 'warehouse', 'currency', 'items'])
            ->when($branchID, function ($query) use ($branchID) {
                $query->where('branch_id', $branchID);
            })
            ->when($currencyID, function ($query) use ($currencyID) {
                $query->where('currency_id', $currencyID);
            })
            ->when($status, function ($query) use ($status) {

                $query->where('status', $status);
            })
            ->when($paymentStatus, function ($query) use ($paymentStatus) {

                $query->where('payment_status', $paymentStatus);
            })
            ->when($supplierID, function ($query) use ($supplierID) {

                $query->where('supplier_id', $supplierID);
            })
            ->when($inventoryWarehouseID, function ($query) use ($inventoryWarehouseID) {

                $query->where('inventory_warehouse_id', $inventoryWarehouseID);
            })
            ->when(($startDate && $endDate), function ($query) use ($startDate, $endDate) {
                $query->whereBetween('purchase_date', [$startDate, $endDate]);
            })
            ->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('id', 'like', '%' . $search . '%');
                    $query->orWhere('reference', 'like', '%' . $search . '%');

                });
            })
            ->orderBy($orderBy, 'desc');
        if (!empty($paginate)) {
            $results = $query->paginate();

        } else {

            $results = $query->get();
        }
        return $results;
    }

    public function getSuppliersReport($data = []): Collection|array|LengthAwarePaginator
    {
        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? '';
        $inventoryWarehouseID = $data['inventory_warehouse_id'] ?? '';
        $inventoryCategoryID = $data['inventory_category_id'] ?? '';
        $inventorySubCategoryID = $data['inventory_sub_category_id'] ?? '';
        $currencyID = $data['currency_id'] ?? '';
        $status = $data['status'] ?? '';
        $paymentStatus = $data['payment_status'] ?? '';
        $brandID = $data['inventory_product_brand_id'] ?? '';
        $featured = $data['featured'] ?? '';
        $active = $data['active'] ?? '';
        $branchID = $data['branch_id'] ?? '';
        $supplierID = $data['supplier_id'] ?? '';
        $search = $data['search'] ?? '';
        $orderBy = $data['order_by'] ?? 'total_purchases_quantity';
        $paginate = $data['paginate'] ?? false;
        $baseCurrency = Currency::find(Setting::where('setting_key', 'currency')->first()->setting_value);
        $operator = '/';
        if ($currencyID) {
            $currency = Currency::find($currencyID);
            if ($currency->xrate > $baseCurrency->xrate) {
                $operator = '/';
            } else {
                $operator = '*';
            }
            $currencyFromBase = false;
        } else {
            $currencyID = $baseCurrency->id;
            $currencyFromBase = true;
        }
        $query = Message::leftJoin('inventory_product_purchases', 'inventory_product_purchases.supplier_id', 'suppliers.id')
            ->selectRaw("suppliers.*")
            ->selectSub(function ($query) use ($inventoryWarehouseID, $startDate, $endDate, $inventoryCategoryID, $inventorySubCategoryID, $brandID, $status, $paymentStatus, $currencyID, $currencyFromBase) {
                $query->selectRaw('coalesce(sum(quantity),0)')
                    ->from('inventory_product_purchase_items')
                    ->leftJoin('inventory_product_purchases', 'inventory_product_purchases.id', 'inventory_product_purchase_items.inventory_product_purchase_id')
                    ->whereColumn('inventory_product_purchases.supplier_id', 'suppliers.id')
                    ->when($inventoryWarehouseID, function ($query) use ($inventoryWarehouseID) {
                        $query->where('inventory_product_purchases.inventory_warehouse_id', $inventoryWarehouseID);
                    })
                    ->when(($startDate && $endDate), function ($query) use ($startDate, $endDate) {
                        $query->whereBetween('inventory_product_purchases.purchase_date', [$startDate, $endDate]);
                    })
                    ->when($inventoryCategoryID, function ($query) use ($inventoryCategoryID) {
                        $query->where('inventory_products.inventory_product_category_id', $inventoryCategoryID);
                    })
                    ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                        $query->where('inventory_product_purchases.currency_id', $currencyID);
                    })
                    ->when($status, function ($query) use ($status) {
                        $query->where('inventory_product_purchases.status', $status);
                    })
                    ->when($paymentStatus, function ($query) use ($paymentStatus) {
                        $query->where('inventory_product_purchases.payment_status', $paymentStatus);
                    })
                    ->when($inventorySubCategoryID, function ($query) use ($inventorySubCategoryID) {
                        $query->where('inventory_products.inventory_product_subcategory_id', $inventorySubCategoryID);
                    })
                    ->when($brandID, function ($query) use ($brandID) {
                        $query->where('inventory_products.inventory_product_brand_id', $brandID);
                    })
                    ->limit(1);
            }, 'total_purchases_quantity')
            ->selectSub(function ($query) use ($inventoryWarehouseID, $startDate, $endDate, $currencyID, $currencyFromBase, $status, $paymentStatus) {
                $query->selectRaw('coalesce(sum(if(inventory_product_purchase_items.xrate>1,coalesce(inventory_product_purchase_items.total/inventory_product_purchase_items.xrate,0),coalesce(inventory_product_purchase_items.total*inventory_product_purchase_items.xrate,0))),0)')
                    ->from('inventory_product_purchase_items')
                    ->leftJoin('inventory_product_purchases', 'inventory_product_purchases.id', 'inventory_product_purchase_items.inventory_product_purchase_id')
                    ->whereColumn('inventory_product_purchases.supplier_id', 'suppliers.id')
                    ->when($inventoryWarehouseID, function ($query) use ($inventoryWarehouseID) {
                        $query->where('inventory_product_purchases.inventory_warehouse_id', $inventoryWarehouseID);
                    })
                    ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                        $query->where('inventory_product_purchases.currency_id', $currencyID);
                    })
                    ->when(($startDate && $endDate), function ($query) use ($startDate, $endDate) {
                        $query->whereBetween('inventory_product_purchases.purchase_date', [$startDate, $endDate]);
                    })
                    ->when($status, function ($query) use ($status) {
                        $query->where('inventory_product_purchases.status', $status);
                    })
                    ->when($paymentStatus, function ($query) use ($paymentStatus) {
                        $query->where('inventory_product_purchases.payment_status', $paymentStatus);
                    })
                    ->limit(1);
            }, 'total_purchase_amount')
            ->selectSub(function ($query) use ($inventoryWarehouseID, $startDate, $endDate, $currencyID, $currencyFromBase, $status, $paymentStatus) {
                $query->selectRaw('coalesce(sum(if(inventory_product_purchases.xrate>1,coalesce(inventory_product_purchases.balance/inventory_product_purchases.xrate,0),coalesce(inventory_product_purchases.balance*inventory_product_purchases.xrate,0))),0)')
                    ->from('inventory_product_purchases')
                    ->whereColumn('inventory_product_purchases.supplier_id', 'suppliers.id')
                    ->when($inventoryWarehouseID, function ($query) use ($inventoryWarehouseID) {
                        $query->where('inventory_product_purchases.inventory_warehouse_id', $inventoryWarehouseID);
                    })
                    ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                        $query->where('inventory_product_purchases.currency_id', $currencyID);
                    })
                    ->when(($startDate && $endDate), function ($query) use ($startDate, $endDate) {
                        $query->whereBetween('inventory_product_purchases.purchase_date', [$startDate, $endDate]);
                    })
                    ->when($status, function ($query) use ($status) {
                        $query->where('inventory_product_purchases.status', $status);
                    })
                    ->when($paymentStatus, function ($query) use ($paymentStatus) {
                        $query->where('inventory_product_purchases.payment_status', $paymentStatus);
                    })
                    ->limit(1);
            }, 'total_balance')
            ->when($search, function ($query) use ($search) {
                $query->where('suppliers.name', 'like', '%' . $search . '%');
                $query->orWhere('suppliers.sku', 'like', '%' . $search . '%');
                $query->orWhere('suppliers.description', 'like', '%' . $search . '%');
            })
            ->when($supplierID, function ($query) use ($supplierID) {
                $query->where('suppliers.id', $supplierID);
            })
            ->groupBy('suppliers.id')
            ->orderBy($orderBy, 'desc');
        if (!empty($paginate)) {
            $results = $query->paginate();

        } else {

            $results = $query->get();
        }
        return $results;
    }

    public function getCustomersReport($data = []): Collection|array|LengthAwarePaginator
    {
        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? '';
        $inventoryWarehouseID = $data['inventory_warehouse_id'] ?? '';
        $inventoryCategoryID = $data['inventory_category_id'] ?? '';
        $inventorySubCategoryID = $data['inventory_sub_category_id'] ?? '';
        $currencyID = $data['currency_id'] ?? '';
        $status = $data['status'] ?? '';
        $paymentStatus = $data['payment_status'] ?? '';
        $brandID = $data['inventory_product_brand_id'] ?? '';
        $patientID = $data['patient_id'] ?? '';
        $active = $data['active'] ?? '';
        $branchID = $data['branch_id'] ?? '';
        $search = $data['search'] ?? '';
        $orderBy = $data['order_by'] ?? 'total_sales_quantity';
        $paginate = $data['paginate'] ?? false;
        $baseCurrency = Currency::find(Setting::where('setting_key', 'currency')->first()->setting_value);
        $operator = '/';
        if ($currencyID) {
            $currency = Currency::find($currencyID);
            if ($currency->xrate > $baseCurrency->xrate) {
                $operator = '/';
            } else {
                $operator = '*';
            }
            $currencyFromBase = false;
        } else {
            $currencyID = $baseCurrency->id;
            $currencyFromBase = true;
        }
        $query = Client::leftJoin('inventory_product_sales', 'inventory_product_sales.patient_id', 'patients.id')
            ->selectRaw("patients.*")
            ->selectSub(function ($query) use ($inventoryWarehouseID, $startDate, $endDate, $inventoryCategoryID, $inventorySubCategoryID, $brandID, $status, $paymentStatus, $currencyID, $currencyFromBase) {
                $query->selectRaw('coalesce(sum(quantity),0)')
                    ->from('inventory_product_sale_items')
                    ->leftJoin('inventory_product_sales', 'inventory_product_sales.id', 'inventory_product_sale_items.inventory_product_sale_id')
                    ->whereColumn('inventory_product_sales.patient_id', 'patients.id')
                    ->when($inventoryWarehouseID, function ($query) use ($inventoryWarehouseID) {
                        $query->where('inventory_product_sales.inventory_warehouse_id', $inventoryWarehouseID);
                    })
                    ->when(($startDate && $endDate), function ($query) use ($startDate, $endDate) {
                        $query->whereBetween('inventory_product_sales.sale_date', [$startDate, $endDate]);
                    })
                    ->when($inventoryCategoryID, function ($query) use ($inventoryCategoryID) {
                        $query->where('inventory_products.inventory_product_category_id', $inventoryCategoryID);
                    })
                    ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                        $query->where('inventory_product_sales.currency_id', $currencyID);
                    })
                    ->when($status, function ($query) use ($status) {
                        $query->where('inventory_product_sales.status', $status);
                    })
                    ->when($paymentStatus, function ($query) use ($paymentStatus) {
                        $query->whereHas('invoice', function ($query) use ($paymentStatus) {
                            $query->where('status', $paymentStatus);
                        });
                    })
                    ->when($inventorySubCategoryID, function ($query) use ($inventorySubCategoryID) {
                        $query->where('inventory_products.inventory_product_subcategory_id', $inventorySubCategoryID);
                    })
                    ->when($brandID, function ($query) use ($brandID) {
                        $query->where('inventory_products.inventory_product_brand_id', $brandID);
                    })
                    ->limit(1);
            }, 'total_sales_quantity')
            ->selectSub(function ($query) use ($inventoryWarehouseID, $startDate, $endDate, $currencyID, $currencyFromBase, $status, $paymentStatus) {
                $query->selectRaw('coalesce(sum(if(inventory_product_sale_items.xrate>1,coalesce(inventory_product_sale_items.total/inventory_product_sale_items.xrate,0),coalesce(inventory_product_sale_items.total*inventory_product_sale_items.xrate,0))),0)')
                    ->from('inventory_product_sale_items')
                    ->leftJoin('inventory_product_sales', 'inventory_product_sales.id', 'inventory_product_sale_items.inventory_product_sale_id')
                    ->whereColumn('inventory_product_sales.patient_id', 'patients.id')
                    ->when($inventoryWarehouseID, function ($query) use ($inventoryWarehouseID) {
                        $query->where('inventory_product_sales.inventory_warehouse_id', $inventoryWarehouseID);
                    })
                    ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                        $query->where('inventory_product_sales.currency_id', $currencyID);
                    })
                    ->when(($startDate && $endDate), function ($query) use ($startDate, $endDate) {
                        $query->whereBetween('inventory_product_sales.sale_date', [$startDate, $endDate]);
                    })
                    ->when($status, function ($query) use ($status) {
                        $query->where('inventory_product_sales.status', $status);
                    })
                    ->when($paymentStatus, function ($query) use ($paymentStatus) {
                        $query->whereHas('invoice', function ($query) use ($paymentStatus) {
                            $query->where('status', $paymentStatus);
                        });
                    })
                    ->limit(1);
            }, 'total_sales_amount')
            ->selectSub(function ($query) use ($inventoryWarehouseID, $startDate, $endDate, $currencyID, $currencyFromBase, $status, $paymentStatus) {
                $query->selectRaw('coalesce(sum(if(inventory_product_sales.xrate>1,coalesce(inventory_product_sales.balance/inventory_product_sales.xrate,0),coalesce(inventory_product_sales.balance*inventory_product_sales.xrate,0))),0)')
                    ->from('inventory_product_sales')
                    ->whereColumn('inventory_product_sales.patient_id', 'patients.id')
                    ->when($inventoryWarehouseID, function ($query) use ($inventoryWarehouseID) {
                        $query->where('inventory_product_sales.inventory_warehouse_id', $inventoryWarehouseID);
                    })
                    ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                        $query->where('inventory_product_sales.currency_id', $currencyID);
                    })
                    ->when(($startDate && $endDate), function ($query) use ($startDate, $endDate) {
                        $query->whereBetween('inventory_product_sales.sale_date', [$startDate, $endDate]);
                    })
                    ->when($status, function ($query) use ($status) {
                        $query->where('inventory_product_sales.status', $status);
                    })
                    ->when($paymentStatus, function ($query) use ($paymentStatus) {
                        $query->whereHas('invoice', function ($query) use ($paymentStatus) {
                            $query->where('status', $paymentStatus);
                        });
                    })
                    ->limit(1);
            }, 'total_balance')
            ->whereHas('sales')
            ->when($search, function ($query) use ($search) {
                $query->where('patients.first_name', 'like', '%' . $search . '%');
                $query->orWhere('patients.last_name', 'like', '%' . $search . '%');
                $query->orWhere('patients.id', 'like', '%' . $search . '%');
                $query->orWhere('patients.description', 'like', '%' . $search . '%');
            })
            ->when($patientID, function ($query) use ($patientID) {
                $query->where('patients.id', $patientID);
            })
            ->groupBy('patients.id')
            ->orderBy($orderBy, 'desc');
        if (!empty($paginate)) {
            $results = $query->paginate();

        } else {

            $results = $query->get();
        }
        return $results;
    }

    public function getTopCustomersReport($data = []): Collection|array|LengthAwarePaginator
    {
        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? '';
        $inventoryWarehouseID = $data['inventory_warehouse_id'] ?? '';
        $inventoryCategoryID = $data['inventory_category_id'] ?? '';
        $inventorySubCategoryID = $data['inventory_sub_category_id'] ?? '';
        $currencyID = $data['currency_id'] ?? '';
        $status = $data['status'] ?? '';
        $paymentStatus = $data['payment_status'] ?? '';
        $brandID = $data['inventory_product_brand_id'] ?? '';
        $patientID = $data['patient_id'] ?? '';
        $active = $data['active'] ?? '';
        $branchID = $data['branch_id'] ?? '';
        $search = $data['search'] ?? '';
        $orderBy = $data['order_by'] ?? 'total_sales_quantity';
        $paginate = $data['paginate'] ?? false;
        $baseCurrency = Currency::find(Setting::where('setting_key', 'currency')->first()->setting_value);
        $operator = '/';
        if ($currencyID) {
            $currency = Currency::find($currencyID);
            if ($currency->xrate > $baseCurrency->xrate) {
                $operator = '/';
            } else {
                $operator = '*';
            }
            $currencyFromBase = false;
        } else {
            $currencyID = $baseCurrency->id;
            $currencyFromBase = true;
        }
        $query = Client::leftJoin('inventory_product_sales', 'inventory_product_sales.patient_id', 'patients.id')
            ->selectRaw("patients.*")
            ->selectSub(function ($query) use ($inventoryWarehouseID, $startDate, $endDate, $inventoryCategoryID, $inventorySubCategoryID, $brandID, $status, $paymentStatus, $currencyID, $currencyFromBase) {
                $query->selectRaw('coalesce(sum(quantity),0)')
                    ->from('inventory_product_sale_items')
                    ->leftJoin('inventory_product_sales', 'inventory_product_sales.id', 'inventory_product_sale_items.inventory_product_sale_id')
                    ->whereColumn('inventory_product_sales.patient_id', 'patients.id')
                    ->when($inventoryWarehouseID, function ($query) use ($inventoryWarehouseID) {
                        $query->where('inventory_product_sales.inventory_warehouse_id', $inventoryWarehouseID);
                    })
                    ->when(($startDate && $endDate), function ($query) use ($startDate, $endDate) {
                        $query->whereBetween('inventory_product_sales.sale_date', [$startDate, $endDate]);
                    })
                    ->when($inventoryCategoryID, function ($query) use ($inventoryCategoryID) {
                        $query->where('inventory_products.inventory_product_category_id', $inventoryCategoryID);
                    })
                    ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                        $query->where('inventory_product_sales.currency_id', $currencyID);
                    })
                    ->when($status, function ($query) use ($status) {
                        $query->where('inventory_product_sales.status', $status);
                    })
                    ->when($paymentStatus, function ($query) use ($paymentStatus) {
                        $query->whereHas('invoice', function ($query) use ($paymentStatus) {
                            $query->where('status', $paymentStatus);
                        });
                    })
                    ->when($inventorySubCategoryID, function ($query) use ($inventorySubCategoryID) {
                        $query->where('inventory_products.inventory_product_subcategory_id', $inventorySubCategoryID);
                    })
                    ->when($brandID, function ($query) use ($brandID) {
                        $query->where('inventory_products.inventory_product_brand_id', $brandID);
                    })
                    ->limit(1);
            }, 'total_sales_quantity')
            ->selectSub(function ($query) use ($inventoryWarehouseID, $startDate, $endDate, $currencyID, $currencyFromBase, $status, $paymentStatus) {
                $query->selectRaw('coalesce(sum(if(inventory_product_sale_items.xrate>1,coalesce(inventory_product_sale_items.total/inventory_product_sale_items.xrate,0),coalesce(inventory_product_sale_items.total*inventory_product_sale_items.xrate,0))),0)')
                    ->from('inventory_product_sale_items')
                    ->leftJoin('inventory_product_sales', 'inventory_product_sales.id', 'inventory_product_sale_items.inventory_product_sale_id')
                    ->whereColumn('inventory_product_sales.patient_id', 'patients.id')
                    ->when($inventoryWarehouseID, function ($query) use ($inventoryWarehouseID) {
                        $query->where('inventory_product_sales.inventory_warehouse_id', $inventoryWarehouseID);
                    })
                    ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                        $query->where('inventory_product_sales.currency_id', $currencyID);
                    })
                    ->when(($startDate && $endDate), function ($query) use ($startDate, $endDate) {
                        $query->whereBetween('inventory_product_sales.sale_date', [$startDate, $endDate]);
                    })
                    ->when($status, function ($query) use ($status) {
                        $query->where('inventory_product_sales.status', $status);
                    })
                    ->when($paymentStatus, function ($query) use ($paymentStatus) {
                        $query->whereHas('invoice', function ($query) use ($paymentStatus) {
                            $query->where('status', $paymentStatus);
                        });
                    })
                    ->limit(1);
            }, 'total_sales_amount')
            ->selectSub(function ($query) use ($inventoryWarehouseID, $startDate, $endDate, $currencyID, $currencyFromBase, $status, $paymentStatus) {
                $query->selectRaw('coalesce(sum(if(inventory_product_sales.xrate>1,coalesce(inventory_product_sales.balance/inventory_product_sales.xrate,0),coalesce(inventory_product_sales.balance*inventory_product_sales.xrate,0))),0)')
                    ->from('inventory_product_sales')
                    ->whereColumn('inventory_product_sales.patient_id', 'patients.id')
                    ->when($inventoryWarehouseID, function ($query) use ($inventoryWarehouseID) {
                        $query->where('inventory_product_sales.inventory_warehouse_id', $inventoryWarehouseID);
                    })
                    ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                        $query->where('inventory_product_sales.currency_id', $currencyID);
                    })
                    ->when(($startDate && $endDate), function ($query) use ($startDate, $endDate) {
                        $query->whereBetween('inventory_product_sales.sale_date', [$startDate, $endDate]);
                    })
                    ->when($status, function ($query) use ($status) {
                        $query->where('inventory_product_sales.status', $status);
                    })
                    ->when($paymentStatus, function ($query) use ($paymentStatus) {
                        $query->whereHas('invoice', function ($query) use ($paymentStatus) {
                            $query->where('status', $paymentStatus);
                        });
                    })
                    ->limit(1);
            }, 'total_balance')
            ->whereHas('sales')
            ->when($search, function ($query) use ($search) {
                $query->where('patients.first_name', 'like', '%' . $search . '%');
                $query->orWhere('patients.last_name', 'like', '%' . $search . '%');
                $query->orWhere('patients.id', 'like', '%' . $search . '%');
                $query->orWhere('patients.description', 'like', '%' . $search . '%');
            })
            ->when($patientID, function ($query) use ($patientID) {
                $query->where('patients.id', $patientID);
            })
            ->groupBy('patients.id')
            ->orderBy($orderBy, 'desc')
            ->limit(10);

        return $query->get();
    }

    public function getSaleReturnsReport($data = []): Collection|array|LengthAwarePaginator
    {
        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? '';
        $inventoryWarehouseID = $data['inventory_warehouse_id'] ?? '';
        $shippingStatus = $data['shipping_status'] ?? '';
        $paymentStatus = $data['payment_status'] ?? '';
        $currencyID = $data['currency_id'] ?? '';
        $status = $data['status'] ?? '';
        $patientID = $data['patient_id'] ?? '';
        $sponsor = $data['sponsor'] ?? '';
        $saleType = $data['sale_type'] ?? '';
        $branchID = $data['branch_id'] ?? '';
        $coPayerID = $data['co_payer_id'] ?? '';
        $search = $data['search'] ?? '';
        $orderBy = $data['order_by'] ?? '';
        $paginate = $data['paginate'] ?? false;
        $baseCurrency = Currency::find(Setting::where('setting_key', 'currency')->first()->setting_value);
        $operator = '/';
        if ($currencyID) {
            $currency = Currency::find($currencyID);
            if ($currency->xrate > $baseCurrency->xrate) {
                $operator = '/';
            } else {
                $operator = '*';
            }
            $currencyFromBase = false;
        } else {
            //$currencyID = $baseCurrency->id;
            $currencyFromBase = true;
        }
        $query = InventoryProductSaleReturn::with(['patient', 'createdBy', 'currency', 'warehouse', 'items'])
            ->when($branchID, function ($query) use ($branchID) {
                $query->where('branch_id', $branchID);
            })
            ->when($currencyID, function ($query) use ($currencyID) {
                $query->where('inventory_product_sale_returns.currency_id', $currencyID);
            })
            ->when($status, function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->when($paymentStatus, function ($query) use ($paymentStatus) {
                $query->where('payment_status', $paymentStatus);
            })
            ->when($sponsor, function ($query) use ($sponsor) {
                $query->where('sponsor', $sponsor);
            })
            ->when($shippingStatus, function ($query) use ($shippingStatus) {
                $query->where('shipping_status', $shippingStatus);
            })
            ->when($saleType, function ($query) use ($saleType) {
                $query->where('sale_type', $saleType);
            })
            ->when($coPayerID, function ($query) use ($coPayerID) {
                $query->where('co_payer_id', $coPayerID);
            })
            ->when($patientID, function ($query) use ($patientID) {
                $query->where('patient_id', $patientID);
            })
            ->when($inventoryWarehouseID, function ($query) use ($inventoryWarehouseID) {
                $query->where('inventory_warehouse_id', $inventoryWarehouseID);
            })
            ->when(($startDate && $endDate), function ($query) use ($startDate, $endDate) {
                $query->whereBetween('sale_return_date', [$startDate, $endDate]);
            })
            ->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('id', 'like', '%' . $search . '%');
                    $query->orWhere('reference', 'like', '%' . $search . '%');
                    $query->orWhereHas('patient', function ($query) use ($search) {
                        $query->where('id', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%')
                            ->orWhere('mobile', 'like', '%' . $search . '%')
                            ->orWhere('first_name', 'like', '%' . $search . '%')
                            ->orWhere('external_id', 'like', '%' . $search . '%')
                            ->orWhere('last_name', 'like', '%' . $search . '%')
                            ->orWhere('middle_name', 'like', '%' . $search . '%');
                    })->orWhereHas('coPayer', function ($query) use ($search) {
                        $query->where('name', 'like', '%' . $search . '%');
                    });
                });
            })
            ->orderBy($orderBy, 'desc');
        if (!empty($paginate)) {
            $results = $query->paginate();

        } else {

            $results = $query->get();
        }
        return $results;
    }

    public function getPurchaseReturnsReport($data = []): Collection|array|LengthAwarePaginator
    {

        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? '';
        $inventoryWarehouseID = $data['inventory_warehouse_id'] ?? '';
        $shippingStatus = $data['shipping_status'] ?? '';
        $paymentStatus = $data['payment_status'] ?? '';
        $currencyID = $data['currency_id'] ?? '';
        $status = $data['status'] ?? '';
        $supplierID = $data['supplier_id'] ?? '';
        $saleType = $data['sale_type'] ?? '';
        $branchID = $data['branch_id'] ?? '';
        $search = $data['search'] ?? '';
        $orderBy = $data['order_by'] ?? '';
        $paginate = $data['paginate'] ?? false;
        $baseCurrency = Currency::find(Setting::where('setting_key', 'currency')->first()->setting_value);
        $operator = '/';
        if ($currencyID) {
            $currency = Currency::find($currencyID);
            if ($currency->xrate > $baseCurrency->xrate) {
                $operator = '/';
            } else {
                $operator = '*';
            }
            $currencyFromBase = false;
        } else {
            //$currencyID = $baseCurrency->id;
            $currencyFromBase = true;
        }
        $query = InventoryProductPurchaseReturn::with(['supplier', 'warehouse', 'currency', 'items'])
            ->when($branchID, function ($query) use ($branchID) {
                $query->where('branch_id', $branchID);
            })
            ->when($currencyID, function ($query) use ($currencyID) {
                $query->where('currency_id', $currencyID);
            })
            ->when($status, function ($query) use ($status) {

                $query->where('status', $status);
            })
            ->when($paymentStatus, function ($query) use ($paymentStatus) {

                $query->where('payment_status', $paymentStatus);
            })
            ->when($supplierID, function ($query) use ($supplierID) {

                $query->where('supplier_id', $supplierID);
            })
            ->when($inventoryWarehouseID, function ($query) use ($inventoryWarehouseID) {

                $query->where('inventory_warehouse_id', $inventoryWarehouseID);
            })
            ->when(($startDate && $endDate), function ($query) use ($startDate, $endDate) {
                $query->whereBetween('purchase_return_date', [$startDate, $endDate]);
            })
            ->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('id', 'like', '%' . $search . '%');
                    $query->orWhere('reference', 'like', '%' . $search . '%');

                });
            })
            ->orderBy($orderBy, 'desc');
        if (!empty($paginate)) {
            $results = $query->paginate();

        } else {

            $results = $query->get();
        }
        return $results;
    }

    public function getStaffReport($data = []): Collection|array|LengthAwarePaginator
    {
        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? '';
        $inventoryWarehouseID = $data['inventory_warehouse_id'] ?? '';
        $inventoryCategoryID = $data['inventory_category_id'] ?? '';
        $inventorySubCategoryID = $data['inventory_sub_category_id'] ?? '';
        $currencyID = $data['currency_id'] ?? '';
        $status = $data['status'] ?? '';
        $paymentStatus = $data['payment_status'] ?? '';
        $brandID = $data['inventory_product_brand_id'] ?? '';
        $patientID = $data['patient_id'] ?? '';
        $createdByID = $data['created_by_id'] ?? '';
        $active = $data['active'] ?? '';
        $branchID = $data['branch_id'] ?? '';
        $search = $data['search'] ?? '';
        $orderBy = $data['order_by'] ?? 'total_sales_quantity';
        $paginate = $data['paginate'] ?? false;
        $baseCurrency = Currency::find(Setting::where('setting_key', 'currency')->first()->setting_value);
        $operator = '/';
        if ($currencyID) {
            $currency = Currency::find($currencyID);
            if ($currency->xrate > $baseCurrency->xrate) {
                $operator = '/';
            } else {
                $operator = '*';
            }
            $currencyFromBase = false;
        } else {
            $currencyID = $baseCurrency->id;
            $currencyFromBase = true;
        }
        $query = User::leftJoin('inventory_product_sales', 'inventory_product_sales.created_by_id', 'users.id')
            ->selectRaw("users.*")
            ->selectSub(function ($query) use ($inventoryWarehouseID, $startDate, $endDate, $inventoryCategoryID, $inventorySubCategoryID, $brandID, $status, $paymentStatus, $currencyID, $currencyFromBase) {
                $query->selectRaw('coalesce(sum(quantity),0)')
                    ->from('inventory_product_sale_items')
                    ->leftJoin('inventory_product_sales', 'inventory_product_sales.id', 'inventory_product_sale_items.inventory_product_sale_id')
                    ->whereColumn('inventory_product_sales.created_by_id', 'users.id')
                    ->when($inventoryWarehouseID, function ($query) use ($inventoryWarehouseID) {
                        $query->where('inventory_product_sales.inventory_warehouse_id', $inventoryWarehouseID);
                    })
                    ->when(($startDate && $endDate), function ($query) use ($startDate, $endDate) {
                        $query->whereBetween('inventory_product_sales.sale_date', [$startDate, $endDate]);
                    })
                    ->when($inventoryCategoryID, function ($query) use ($inventoryCategoryID) {
                        $query->where('inventory_products.inventory_product_category_id', $inventoryCategoryID);
                    })
                    ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                        $query->where('inventory_product_sales.currency_id', $currencyID);
                    })
                    ->when($status, function ($query) use ($status) {
                        $query->where('inventory_product_sales.status', $status);
                    })
                    ->when($paymentStatus, function ($query) use ($paymentStatus) {
                        $query->whereHas('invoice', function ($query) use ($paymentStatus) {
                            $query->where('status', $paymentStatus);
                        });
                    })
                    ->when($inventorySubCategoryID, function ($query) use ($inventorySubCategoryID) {
                        $query->where('inventory_products.inventory_product_subcategory_id', $inventorySubCategoryID);
                    })
                    ->when($brandID, function ($query) use ($brandID) {
                        $query->where('inventory_products.inventory_product_brand_id', $brandID);
                    })
                    ->limit(1);
            }, 'total_sales_quantity')
            ->selectSub(function ($query) use ($inventoryWarehouseID, $startDate, $endDate, $currencyID, $currencyFromBase, $status, $paymentStatus) {
                $query->selectRaw('coalesce(sum(if(inventory_product_sale_items.xrate>1,coalesce(inventory_product_sale_items.total/inventory_product_sale_items.xrate,0),coalesce(inventory_product_sale_items.total*inventory_product_sale_items.xrate,0))),0)')
                    ->from('inventory_product_sale_items')
                    ->leftJoin('inventory_product_sales', 'inventory_product_sales.id', 'inventory_product_sale_items.inventory_product_sale_id')
                    ->whereColumn('inventory_product_sales.created_by_id', 'users.id')
                    ->when($inventoryWarehouseID, function ($query) use ($inventoryWarehouseID) {
                        $query->where('inventory_product_sales.inventory_warehouse_id', $inventoryWarehouseID);
                    })
                    ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                        $query->where('inventory_product_sales.currency_id', $currencyID);
                    })
                    ->when(($startDate && $endDate), function ($query) use ($startDate, $endDate) {
                        $query->whereBetween('inventory_product_sales.sale_date', [$startDate, $endDate]);
                    })
                    ->when($status, function ($query) use ($status) {
                        $query->where('inventory_product_sales.status', $status);
                    })
                    ->when($paymentStatus, function ($query) use ($paymentStatus) {
                        $query->whereHas('invoice', function ($query) use ($paymentStatus) {
                            $query->where('status', $paymentStatus);
                        });
                    })
                    ->limit(1);
            }, 'total_sales_amount')
            ->selectSub(function ($query) use ($inventoryWarehouseID, $startDate, $endDate, $currencyID, $currencyFromBase, $status, $paymentStatus) {
                $query->selectRaw('coalesce(sum(if(inventory_product_sales.xrate>1,coalesce(inventory_product_sales.balance/inventory_product_sales.xrate,0),coalesce(inventory_product_sales.balance*inventory_product_sales.xrate,0))),0)')
                    ->from('inventory_product_sales')
                    ->whereColumn('inventory_product_sales.created_by_id', 'users.id')
                    ->when($inventoryWarehouseID, function ($query) use ($inventoryWarehouseID) {
                        $query->where('inventory_product_sales.inventory_warehouse_id', $inventoryWarehouseID);
                    })
                    ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                        $query->where('inventory_product_sales.currency_id', $currencyID);
                    })
                    ->when(($startDate && $endDate), function ($query) use ($startDate, $endDate) {
                        $query->whereBetween('inventory_product_sales.sale_date', [$startDate, $endDate]);
                    })
                    ->when($status, function ($query) use ($status) {
                        $query->where('inventory_product_sales.status', $status);
                    })
                    ->when($paymentStatus, function ($query) use ($paymentStatus) {
                        $query->whereHas('invoice', function ($query) use ($paymentStatus) {
                            $query->where('status', $paymentStatus);
                        });
                    })
                    ->limit(1);
            }, 'total_balance')
            ->whereHas('sales')
            ->when($search, function ($query) use ($search) {
                $query->where('users.first_name', 'like', '%' . $search . '%');
                $query->orWhere('users.last_name', 'like', '%' . $search . '%');
                $query->orWhere('users.id', 'like', '%' . $search . '%');
                $query->orWhere('users.description', 'like', '%' . $search . '%');
            })
            ->when($createdByID, function ($query) use ($createdByID) {
                $query->where('users.id', $createdByID);
            })
            ->groupBy('users.id')
            ->orderBy($orderBy, 'desc');
        if (!empty($paginate)) {
            $results = $query->paginate();

        } else {

            $results = $query->get();
        }
        return $results;
    }

    public function getTopStaffReport($data = []): Collection|array|LengthAwarePaginator
    {
        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? '';
        $inventoryWarehouseID = $data['inventory_warehouse_id'] ?? '';
        $inventoryCategoryID = $data['inventory_category_id'] ?? '';
        $inventorySubCategoryID = $data['inventory_sub_category_id'] ?? '';
        $currencyID = $data['currency_id'] ?? '';
        $status = $data['status'] ?? '';
        $paymentStatus = $data['payment_status'] ?? '';
        $brandID = $data['inventory_product_brand_id'] ?? '';
        $patientID = $data['patient_id'] ?? '';
        $createdByID = $data['created_by_id'] ?? '';
        $active = $data['active'] ?? '';
        $branchID = $data['branch_id'] ?? '';
        $search = $data['search'] ?? '';
        $orderBy = $data['order_by'] ?? 'total_sales_quantity';
        $paginate = $data['paginate'] ?? false;
        $baseCurrency = Currency::find(Setting::where('setting_key', 'currency')->first()->setting_value);
        $operator = '/';
        if ($currencyID) {
            $currency = Currency::find($currencyID);
            if ($currency->xrate > $baseCurrency->xrate) {
                $operator = '/';
            } else {
                $operator = '*';
            }
            $currencyFromBase = false;
        } else {
            $currencyID = $baseCurrency->id;
            $currencyFromBase = true;
        }
        $query = User::leftJoin('inventory_product_sales', 'inventory_product_sales.created_by_id', 'users.id')
            ->selectRaw("users.*")
            ->selectSub(function ($query) use ($inventoryWarehouseID, $startDate, $endDate, $inventoryCategoryID, $inventorySubCategoryID, $brandID, $status, $paymentStatus, $currencyID, $currencyFromBase) {
                $query->selectRaw('coalesce(sum(quantity),0)')
                    ->from('inventory_product_sale_items')
                    ->leftJoin('inventory_product_sales', 'inventory_product_sales.id', 'inventory_product_sale_items.inventory_product_sale_id')
                    ->whereColumn('inventory_product_sales.created_by_id', 'users.id')
                    ->when($inventoryWarehouseID, function ($query) use ($inventoryWarehouseID) {
                        $query->where('inventory_product_sales.inventory_warehouse_id', $inventoryWarehouseID);
                    })
                    ->when(($startDate && $endDate), function ($query) use ($startDate, $endDate) {
                        $query->whereBetween('inventory_product_sales.sale_date', [$startDate, $endDate]);
                    })
                    ->when($inventoryCategoryID, function ($query) use ($inventoryCategoryID) {
                        $query->where('inventory_products.inventory_product_category_id', $inventoryCategoryID);
                    })
                    ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                        $query->where('inventory_product_sales.currency_id', $currencyID);
                    })
                    ->when($status, function ($query) use ($status) {
                        $query->where('inventory_product_sales.status', $status);
                    })
                    ->when($paymentStatus, function ($query) use ($paymentStatus) {
                        $query->whereHas('invoice', function ($query) use ($paymentStatus) {
                            $query->where('status', $paymentStatus);
                        });
                    })
                    ->when($inventorySubCategoryID, function ($query) use ($inventorySubCategoryID) {
                        $query->where('inventory_products.inventory_product_subcategory_id', $inventorySubCategoryID);
                    })
                    ->when($brandID, function ($query) use ($brandID) {
                        $query->where('inventory_products.inventory_product_brand_id', $brandID);
                    })
                    ->limit(1);
            }, 'total_sales_quantity')
            ->selectSub(function ($query) use ($inventoryWarehouseID, $startDate, $endDate, $currencyID, $currencyFromBase, $status, $paymentStatus) {
                $query->selectRaw('coalesce(sum(if(inventory_product_sale_items.xrate>1,coalesce(inventory_product_sale_items.total/inventory_product_sale_items.xrate,0),coalesce(inventory_product_sale_items.total*inventory_product_sale_items.xrate,0))),0)')
                    ->from('inventory_product_sale_items')
                    ->leftJoin('inventory_product_sales', 'inventory_product_sales.id', 'inventory_product_sale_items.inventory_product_sale_id')
                    ->whereColumn('inventory_product_sales.created_by_id', 'users.id')
                    ->when($inventoryWarehouseID, function ($query) use ($inventoryWarehouseID) {
                        $query->where('inventory_product_sales.inventory_warehouse_id', $inventoryWarehouseID);
                    })
                    ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                        $query->where('inventory_product_sales.currency_id', $currencyID);
                    })
                    ->when(($startDate && $endDate), function ($query) use ($startDate, $endDate) {
                        $query->whereBetween('inventory_product_sales.sale_date', [$startDate, $endDate]);
                    })
                    ->when($status, function ($query) use ($status) {
                        $query->where('inventory_product_sales.status', $status);
                    })
                    ->when($paymentStatus, function ($query) use ($paymentStatus) {
                        $query->whereHas('invoice', function ($query) use ($paymentStatus) {
                            $query->where('status', $paymentStatus);
                        });
                    })
                    ->limit(1);
            }, 'total_sales_amount')
            ->selectSub(function ($query) use ($inventoryWarehouseID, $startDate, $endDate, $currencyID, $currencyFromBase, $status, $paymentStatus) {
                $query->selectRaw('coalesce(sum(if(inventory_product_sales.xrate>1,coalesce(inventory_product_sales.balance/inventory_product_sales.xrate,0),coalesce(inventory_product_sales.balance*inventory_product_sales.xrate,0))),0)')
                    ->from('inventory_product_sales')
                    ->whereColumn('inventory_product_sales.created_by_id', 'users.id')
                    ->when($inventoryWarehouseID, function ($query) use ($inventoryWarehouseID) {
                        $query->where('inventory_product_sales.inventory_warehouse_id', $inventoryWarehouseID);
                    })
                    ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                        $query->where('inventory_product_sales.currency_id', $currencyID);
                    })
                    ->when(($startDate && $endDate), function ($query) use ($startDate, $endDate) {
                        $query->whereBetween('inventory_product_sales.sale_date', [$startDate, $endDate]);
                    })
                    ->when($status, function ($query) use ($status) {
                        $query->where('inventory_product_sales.status', $status);
                    })
                    ->when($paymentStatus, function ($query) use ($paymentStatus) {
                        $query->whereHas('invoice', function ($query) use ($paymentStatus) {
                            $query->where('status', $paymentStatus);
                        });
                    })
                    ->limit(1);
            }, 'total_balance')
            ->whereHas('sales')
            ->when($search, function ($query) use ($search) {
                $query->where('users.first_name', 'like', '%' . $search . '%');
                $query->orWhere('users.last_name', 'like', '%' . $search . '%');
                $query->orWhere('users.id', 'like', '%' . $search . '%');
                $query->orWhere('users.description', 'like', '%' . $search . '%');
            })
            ->when($createdByID, function ($query) use ($createdByID) {
                $query->where('users.id', $createdByID);
            })
            ->groupBy('users.id')
            ->orderBy($orderBy, 'desc')
            ->limit(10);

        return $query->get();
    }


    public function getPurchasePaymentsReport($data = []): Collection|array|LengthAwarePaginator
    {
        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? '';
        $inventoryWarehouseID = $data['inventory_warehouse_id'] ?? '';
        $inventoryCategoryID = $data['inventory_category_id'] ?? '';
        $inventorySubCategoryID = $data['inventory_sub_category_id'] ?? '';
        $currencyID = $data['currency_id'] ?? '';
        $status = $data['status'] ?? '';
        $paymentStatus = $data['payment_status'] ?? '';
        $brandID = $data['inventory_product_brand_id'] ?? '';
        $featured = $data['featured'] ?? '';
        $active = $data['active'] ?? '';
        $branchID = $data['branch_id'] ?? '';
        $supplierID = $data['supplier_id'] ?? '';
        $search = $data['search'] ?? '';
        $orderBy = $data['order_by'] ?? 'created_at';
        $paginate = $data['paginate'] ?? false;
        $baseCurrency = Currency::find(Setting::where('setting_key', 'currency')->first()->setting_value);
        $operator = '/';
        if ($currencyID) {
            $currency = Currency::find($currencyID);
            if ($currency->xrate > $baseCurrency->xrate) {
                $operator = '/';
            } else {
                $operator = '*';
            }
            $currencyFromBase = false;
        } else {
            $currencyID = $baseCurrency->id;
            $currencyFromBase = true;
        }
        $query = InventoryProductPurchasePayment::with(['currency', 'paymentDetail', 'paymentDetail.paymentType', 'purchase', 'purchase.supplier'])
            ->leftJoin('inventory_product_purchases', 'inventory_product_purchase_payments.inventory_product_purchase_id', 'inventory_product_purchases.id')
            ->selectRaw("inventory_product_purchase_payments.*")
            ->when($search, function ($query) use ($search) {
                $query->where('inventory_product_purchase_payments.id', 'like', '%' . $search . '%');
            })
            ->when($inventoryWarehouseID, function ($query) use ($inventoryWarehouseID) {
                $query->where('inventory_product_purchases.inventory_warehouse_id', $inventoryWarehouseID);
            })
            ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                $query->where('inventory_product_purchase_payments.currency_id', $currencyID);
            })
            ->when(($startDate && $endDate), function ($query) use ($startDate, $endDate) {
                $query->whereBetween('inventory_product_purchase_payments.date', [$startDate, $endDate]);
            })
            ->when($supplierID, function ($query) use ($supplierID) {
                $query->where('inventory_product_purchases.supplier_id', $supplierID);
            })
            ->groupBy('inventory_product_purchase_payments.id')
            ->orderBy($orderBy, 'desc');
        if (!empty($paginate)) {
            $results = $query->paginate();

        } else {

            $results = $query->get();
        }
        return $results;
    }

    public function getSalePaymentsReport($data = []): Collection|array|LengthAwarePaginator
    {
        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? '';
        $inventoryWarehouseID = $data['inventory_warehouse_id'] ?? '';
        $inventoryCategoryID = $data['inventory_category_id'] ?? '';
        $inventorySubCategoryID = $data['inventory_sub_category_id'] ?? '';
        $currencyID = $data['currency_id'] ?? '';
        $status = $data['status'] ?? '';
        $paymentStatus = $data['payment_status'] ?? '';
        $brandID = $data['inventory_product_brand_id'] ?? '';
        $featured = $data['featured'] ?? '';
        $active = $data['active'] ?? '';
        $branchID = $data['branch_id'] ?? '';
        $paymentTypeID = $data['payment_type_id'] ?? '';
        $patientID = $data['patient_id'] ?? '';
        $search = $data['search'] ?? '';
        $orderBy = $data['order_by'] ?? 'date';
        $orderByDir = $data['order_by_dir'] ?? 'desc';
        $paginate = $data['paginate'] ?? false;
        $baseCurrency = Currency::find(Setting::where('setting_key', 'currency')->first()->setting_value);
        $operator = '/';
        if ($currencyID) {
            $currency = Currency::find($currencyID);
            if ($currency->xrate > $baseCurrency->xrate) {
                $operator = '/';
            } else {
                $operator = '*';
            }
            $currencyFromBase = false;
        } else {
            $currencyID = $baseCurrency->id;
            $currencyFromBase = true;
        }
        $query = InvoicePayment::with(['currency', 'paymentDetail', 'paymentDetail.paymentType', 'invoice', 'invoice.sale', 'invoice.patient'])
            ->join('invoices', 'invoices.id', 'invoice_payments.invoice_id')
            ->join('inventory_product_sales', 'inventory_product_sales.id', 'invoices.inventory_product_sale_id')
            ->when($search, function ($query) use ($search) {
                $query->where('invoice_payments.id', 'like', '%' . $search . '%');
            })
            ->when($inventoryWarehouseID, function ($query) use ($inventoryWarehouseID) {
                $query->where('inventory_product_sales.inventory_warehouse_id', $inventoryWarehouseID);
            })
            ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                $query->where('invoice_payments.currency_id', $currencyID);
            })
            ->when(($startDate && $endDate), function ($query) use ($startDate, $endDate) {
                $query->whereBetween('invoice_payments.date', [$startDate, $endDate]);
            })
            ->when($patientID, function ($query) use ($patientID) {
                $query->where('invoice_payments.patient_id', $patientID);
            })
            ->when($patientID, function ($query) use ($patientID) {
                $query->where('invoice_payments.patient_id', $patientID);
            })
            ->when($paymentTypeID, function ($query) use ($paymentTypeID) {
                $query->whereHas('paymentDetail', function ($query) use ($paymentTypeID) {
                    $query->where('payment_type_id', $paymentTypeID);
                });
            })
            ->groupBy('invoice_payments.id')
            ->orderBy('invoice_payments.' . $orderBy, $orderByDir);
        if (!empty($paginate)) {
            $results = $query->paginate();

        } else {

            $results = $query->get();
        }
        return $results;
    }

    public function getSuppliersDebtReport($data = []): Collection|array|LengthAwarePaginator
    {
        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? '';
        $inventoryWarehouseID = $data['inventory_warehouse_id'] ?? '';
        $inventoryCategoryID = $data['inventory_category_id'] ?? '';
        $inventorySubCategoryID = $data['inventory_sub_category_id'] ?? '';
        $currencyID = $data['currency_id'] ?? '';
        $status = $data['status'] ?? '';
        $paymentStatus = $data['payment_status'] ?? '';
        $brandID = $data['inventory_product_brand_id'] ?? '';
        $featured = $data['featured'] ?? '';
        $active = $data['active'] ?? '';
        $branchID = $data['branch_id'] ?? '';
        $supplierID = $data['supplier_id'] ?? '';
        $search = $data['search'] ?? '';
        $orderBy = $data['order_by'] ?? 'total_purchases_quantity';
        $paginate = $data['paginate'] ?? false;
        $baseCurrency = Currency::find(Setting::where('setting_key', 'currency')->first()->setting_value);
        $operator = '/';
        if ($currencyID) {
            $currency = Currency::find($currencyID);
            if ($currency->xrate > $baseCurrency->xrate) {
                $operator = '/';
            } else {
                $operator = '*';
            }
            $currencyFromBase = false;
        } else {
            $currencyID = $baseCurrency->id;
            $currencyFromBase = true;
        }
        $query = Message::leftJoin('inventory_product_purchases', 'inventory_product_purchases.supplier_id', 'suppliers.id')
            ->selectRaw("suppliers.*")
            ->selectSub(function ($query) use ($inventoryWarehouseID, $startDate, $endDate, $inventoryCategoryID, $inventorySubCategoryID, $brandID, $status, $paymentStatus, $currencyID, $currencyFromBase) {
                $query->selectRaw('coalesce(sum(quantity),0)')
                    ->from('inventory_product_purchase_items')
                    ->leftJoin('inventory_product_purchases', 'inventory_product_purchases.id', 'inventory_product_purchase_items.inventory_product_purchase_id')
                    ->whereColumn('inventory_product_purchases.supplier_id', 'suppliers.id')
                    ->when($inventoryWarehouseID, function ($query) use ($inventoryWarehouseID) {
                        $query->where('inventory_product_purchases.inventory_warehouse_id', $inventoryWarehouseID);
                    })
                    ->when(($startDate && $endDate), function ($query) use ($startDate, $endDate) {
                        $query->whereBetween('inventory_product_purchases.purchase_date', [$startDate, $endDate]);
                    })
                    ->when($inventoryCategoryID, function ($query) use ($inventoryCategoryID) {
                        $query->where('inventory_products.inventory_product_category_id', $inventoryCategoryID);
                    })
                    ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                        $query->where('inventory_product_purchases.currency_id', $currencyID);
                    })
                    ->when($status, function ($query) use ($status) {
                        $query->where('inventory_product_purchases.status', $status);
                    })
                    ->when($paymentStatus, function ($query) use ($paymentStatus) {
                        $query->where('inventory_product_purchases.payment_status', $paymentStatus);
                    })
                    ->when($inventorySubCategoryID, function ($query) use ($inventorySubCategoryID) {
                        $query->where('inventory_products.inventory_product_subcategory_id', $inventorySubCategoryID);
                    })
                    ->when($brandID, function ($query) use ($brandID) {
                        $query->where('inventory_products.inventory_product_brand_id', $brandID);
                    })
                    ->limit(1);
            }, 'total_purchases_quantity')
            ->selectSub(function ($query) use ($inventoryWarehouseID, $startDate, $endDate, $currencyID, $currencyFromBase, $status, $paymentStatus) {
                $query->selectRaw('coalesce(sum(if(inventory_product_purchase_items.xrate>1,coalesce(inventory_product_purchase_items.total/inventory_product_purchase_items.xrate,0),coalesce(inventory_product_purchase_items.total*inventory_product_purchase_items.xrate,0))),0)')
                    ->from('inventory_product_purchase_items')
                    ->leftJoin('inventory_product_purchases', 'inventory_product_purchases.id', 'inventory_product_purchase_items.inventory_product_purchase_id')
                    ->whereColumn('inventory_product_purchases.supplier_id', 'suppliers.id')
                    ->when($inventoryWarehouseID, function ($query) use ($inventoryWarehouseID) {
                        $query->where('inventory_product_purchases.inventory_warehouse_id', $inventoryWarehouseID);
                    })
                    ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                        $query->where('inventory_product_purchases.currency_id', $currencyID);
                    })
                    ->when(($startDate && $endDate), function ($query) use ($startDate, $endDate) {
                        $query->whereBetween('inventory_product_purchases.purchase_date', [$startDate, $endDate]);
                    })
                    ->when($status, function ($query) use ($status) {
                        $query->where('inventory_product_purchases.status', $status);
                    })
                    ->when($paymentStatus, function ($query) use ($paymentStatus) {
                        $query->where('inventory_product_purchases.payment_status', $paymentStatus);
                    })
                    ->limit(1);
            }, 'total_purchase_amount')
            ->selectSub(function ($query) use ($inventoryWarehouseID, $startDate, $endDate, $currencyID, $currencyFromBase, $status, $paymentStatus) {
                $query->selectRaw('coalesce(sum(if(inventory_product_purchases.xrate>1,coalesce(inventory_product_purchases.balance/inventory_product_purchases.xrate,0),coalesce(inventory_product_purchases.balance*inventory_product_purchases.xrate,0))),0)')
                    ->from('inventory_product_purchases')
                    ->whereColumn('inventory_product_purchases.supplier_id', 'suppliers.id')
                    ->when($inventoryWarehouseID, function ($query) use ($inventoryWarehouseID) {
                        $query->where('inventory_product_purchases.inventory_warehouse_id', $inventoryWarehouseID);
                    })
                    ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                        $query->where('inventory_product_purchases.currency_id', $currencyID);
                    })
                    ->when(($startDate && $endDate), function ($query) use ($startDate, $endDate) {
                        $query->whereBetween('inventory_product_purchases.purchase_date', [$startDate, $endDate]);
                    })
                    ->when($status, function ($query) use ($status) {
                        $query->where('inventory_product_purchases.status', $status);
                    })
                    ->when($paymentStatus, function ($query) use ($paymentStatus) {
                        $query->where('inventory_product_purchases.payment_status', $paymentStatus);
                    })
                    ->limit(1);
            }, 'total_balance')
            ->whereHas('purchases', function ($query) use ($startDate, $endDate) {
                $query->where('balance', '>', 0)
                    ->when(($startDate && $endDate), function ($query) use ($startDate, $endDate) {
                        $query->whereBetween('purchase_date', [$startDate, $endDate]);
                    });
            })
            ->when($search, function ($query) use ($search) {
                $query->where('suppliers.name', 'like', '%' . $search . '%');
                $query->orWhere('suppliers.sku', 'like', '%' . $search . '%');
                $query->orWhere('suppliers.description', 'like', '%' . $search . '%');
            })
            ->when($supplierID, function ($query) use ($supplierID) {
                $query->where('suppliers.id', $supplierID);
            })
            ->groupBy('suppliers.id')
            ->orderBy($orderBy, 'desc');
        if (!empty($paginate)) {
            $results = $query->paginate();

        } else {

            $results = $query->get();
        }
        return $results;
    }

    public function getSalesByPaymentsType($data = [])
    {
        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? '';
        $currencyID = $data['currency_id'] ?? '';
        $paidBy = $data['paid_by'] ?? '';
        $coPayerID = $data['co_payer_id'] ?? '';
        $branchID = $data['branch_id'] ?? '';
        $orderBy = $data['order_by'] ?? 'amount';
        $orderByDir = $data['order_by_dir'] ?? 'desc';
        $baseCurrency = Currency::find(Setting::where('setting_key', 'currency')->first()->setting_value);
        $operator = '/';
        if ($currencyID) {
            $currency = Currency::find($currencyID);
            if ($currency->xrate > $baseCurrency->xrate) {
                $operator = '/';
            } else {
                $operator = '*';
            }
            $currencyFromBase = false;
        } else {
            $currencyID = $baseCurrency->id;
            $currencyFromBase = true;
        }
        return InvoicePayment::leftJoin('payment_types', 'payment_types.id', 'invoice_payments.payment_type_id')
            ->join('invoices', 'invoices.id', 'invoice_payments.invoice_id')
            ->join('inventory_product_sales', 'inventory_product_sales.id', 'invoices.inventory_product_sale_id')
            ->when(($startDate && $endDate), function (Builder $query) use ($startDate, $endDate) {
                $query->whereBetween('date', [$startDate, $endDate]);
            })
            ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                $query->where('invoice_payments.currency_id', $currencyID);
            })
            ->when($paidBy, function ($query) use ($paidBy) {
                $query->where('invoice_payments.paid_by', $paidBy);
            })
            ->when($coPayerID, function ($query) use ($coPayerID) {
                $query->where('invoice_payments.co_payer_id', $coPayerID);
            })
            ->when($branchID, function ($query) use ($branchID) {
                $query->where('invoice_payments.branch_id', $branchID);
            })
            ->selectRaw('sum(coalesce(if(invoice_payments.xrate>1,invoice_payments.amount*invoice_payments.xrate,invoice_payments.amount/invoice_payments.xrate),0)) as amount,payment_types.name payment_type,payment_types.report_color')
            ->groupBy('invoice_payments.payment_type_id')
            ->orderBy('invoice_payments.' . $orderBy, $orderByDir)
            ->get();
    }

    public function getStockReport($data = []): Collection|array|LengthAwarePaginator
    {
        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? '';
        $inventoryWarehouseID = $data['inventory_warehouse_id'] ?? '';
        $inventoryCategoryID = $data['inventory_category_id'] ?? '';
        $inventorySubCategoryID = $data['inventory_sub_category_id'] ?? '';
        $currencyID = $data['currency_id'] ?? '';
        $productType = $data['product_type'] ?? '';
        $brandID = $data['inventory_product_brand_id'] ?? '';
        $featured = $data['featured'] ?? '';
        $active = $data['active'] ?? '';
        $branchID = $data['branch_id'] ?? '';
        $search = $data['search'] ?? '';
        $paginate = $data['paginate'] ?? false;
        $baseCurrency = Currency::find(Setting::where('setting_key', 'currency')->first()->setting_value);
        $operator = '/';
        if ($currencyID) {
            $currency = Currency::find($currencyID);
            if ($currency->xrate > $baseCurrency->xrate) {
                $operator = '/';
            } else {
                $operator = '*';
            }
            $currencyFromBase = false;
        } else {
            $currencyID = $baseCurrency->id;
            $currencyFromBase = true;
        }
        $query = InventoryProductVariation::leftJoin('inventory_products', 'inventory_products.id', 'inventory_product_variations.inventory_product_id')
            ->leftJoin('inventory_product_brands', 'inventory_product_brands.id', 'inventory_products.inventory_product_brand_id')
            ->leftJoin('inventory_product_categories', 'inventory_product_categories.id', 'inventory_products.inventory_product_category_id')
            ->leftJoin('inventory_product_categories as subcategories', 'subcategories.id', 'inventory_products.inventory_product_subcategory_id')
            ->leftJoin('inventory_product_units', 'inventory_product_units.id', 'inventory_products.inventory_product_unit_id')
            ->leftJoin('inventory_product_barcode_types', 'inventory_product_barcode_types.id', 'inventory_products.inventory_product_barcode_type_id')
            ->leftJoin('tax_rates', 'tax_rates.id', 'inventory_products.tax_rate_id')
            ->selectRaw("inventory_product_variations.*,inventory_product_categories.name category,subcategories.name subcategory,inventory_product_brands.name brand,inventory_product_units.name unit,inventory_product_barcode_types.name barcode_type,tax_rates.name tax_rate,inventory_products.product_type product_type,inventory_products.name product_name")
            ->selectSub(function ($query) use ($inventoryWarehouseID) {
                $query->selectRaw('coalesce(sum(coalesce(quantity,0)),0)')
                    ->from('inventory_product_stock')
                    ->whereColumn('inventory_product_stock.inventory_product_variation_id', 'inventory_product_variations.id')
                    ->when($inventoryWarehouseID, function ($query) use ($inventoryWarehouseID) {
                        $query->where('inventory_product_stock.inventory_warehouse_id', $inventoryWarehouseID);
                    })
                    ->limit(1);
            }, 'stock_quantity')
            ->selectSub(function ($query) use ($inventoryWarehouseID) {
                $query->selectRaw('coalesce(sum(coalesce(stock_value,0)),0)')
                    ->from('inventory_product_stock')
                    ->whereColumn('inventory_product_stock.inventory_product_variation_id', 'inventory_product_variations.id')
                    ->when($inventoryWarehouseID, function ($query) use ($inventoryWarehouseID) {
                        $query->where('inventory_product_stock.inventory_warehouse_id', $inventoryWarehouseID);
                    })
                    ->limit(1);
            }, 'stock_value')
            ->when($inventoryCategoryID, function ($query) use ($inventoryCategoryID) {
                $query->where('inventory_products.inventory_product_category_id', $inventoryCategoryID);
            })
            ->when($productType, function ($query) use ($productType) {
                $query->where('inventory_products.product_type', $productType);
            })
            ->when($inventorySubCategoryID, function ($query) use ($inventorySubCategoryID) {
                $query->where('inventory_products.inventory_product_subcategory_id', $inventorySubCategoryID);
            })
            ->when($brandID, function ($query) use ($brandID) {
                $query->where('inventory_products.inventory_product_brand_id', $brandID);
            })
            ->when($search, function ($query) use ($search) {
                $query->where('inventory_products.name', 'like', '%' . $search . '%');
                $query->orWhere('inventory_products.sku', 'like', '%' . $search . '%');
                $query->orWhere('inventory_products.description', 'like', '%' . $search . '%');
                $query->orWhere('inventory_product_variations.name', 'like', '%' . $search . '%');
                $query->orWhere('inventory_product_variations.sku', 'like', '%' . $search . '%');
            })
            ->groupBy('inventory_product_variations.id');
        if (!empty($paginate)) {
            $results = $query->paginate();

        } else {

            $results = $query->get();
        }
        return $results;
    }

    public function getConsultationsByPeriod($data = [])
    {
        $period = $data['period'] ?? 'week';
        $startDate = !empty($data['start_date']) ? Carbon::parse($data['start_date']) : Carbon::today();
        $endDate = $data['end_date'] ?? '';
        $coPayerID = $data['co_payer_id'] ?? '';
        $branchID = $data['branch_id'] ?? '';
        $doctorID = $data['doctor_id'] ?? '';
        $chartData = [];
        $limit = 7;
        $add = 'day';
        if ($period === 'week') {
            $startDate = $startDate->startOf('week');
            $limit = 7;
            $add = 'day';
            $label = $startDate->format('D');
        }
        if ($period === 'month') {
            $startDate = $startDate->startOf('month');
            $limit = 31;
            $add = 'day';
            $label = $startDate->format('Y-m-d');
        }
        if ($period === 'year') {
            $startDate = $startDate->startOf('year');
            $limit = 12;
            $add = 'month';
            $label = $startDate->format('Y-m-d');
        }
        for ($i = 0; $i < $limit; $i++) {
            if ($period === 'week') {
                $label = $startDate->format('D');
            }
            if ($period === 'month') {
                $label = $startDate->format('Y-m-d');
            }
            if ($period === 'year') {
                $label = $startDate->format('M Y');
            }
            $chartData[] = [
                'value' => Consultation::when($period, function ($query) use ($period, $startDate) {
                    if ($period === 'week') {
                        $query->where('consultations.date', $startDate->format('Y-m-d'));
                    }
                    if ($period === 'month') {
                        $query->where('consultations.date', $startDate->format('Y-m-d'));
                    }
                    if ($period === 'year') {
                        $query->whereBetween('consultations.date', [$startDate->startOfMonth()->format('Y-m-d'), $startDate->endOfMonth()->format('Y-m-d')]);
                    }
                })
                    ->when($doctorID, function ($query) use ($doctorID) {
                        $query->where('consultations.doctor_id', $doctorID);
                    })
                    ->when($coPayerID, function ($query) use ($coPayerID) {
                        $query->where('consultations.co_payer_id', $coPayerID);
                    })
                    ->when($branchID, function ($query) use ($branchID) {
                        $query->where('consultations.branch_id', $branchID);
                    })
                    ->count(),

                'label' => $label
            ];
            $startDate = $startDate->add($add, 1, false);
        }
        return $chartData;
    }

    public function getReferringPractitionerPatientCountReport($data = []): Collection|array|LengthAwarePaginator
    {
        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? '';
        $referringPractitionerID = $data['referring_practitioner_id'] ?? '';
        $status = $data['status'] ?? '';
        $gender = $data['gender'] ?? '';
        $search = $data['search'] ?? '';
        $orderBy = $data['order_by'] ?? 'total_referred_patients';
        $paginate = $data['paginate'] ?? false;

        $query = ReferringPractitioner::leftJoin('patients', 'patients.referring_practitioner_id', 'referring_practitioners.id')
            ->selectRaw("referring_practitioners.*,count(patients.id) as total_referred_patients ")
            ->when($search, function ($query) use ($search) {
                $query->where('referring_practitioners.first_name', 'like', '%' . $search . '%');
                $query->orWhere('referring_practitioners.last_name', 'like', '%' . $search . '%');
                $query->orWhere('referring_practitioners.id', 'like', '%' . $search . '%');
                $query->orWhere('referring_practitioners.description', 'like', '%' . $search . '%');
            })
            ->when($referringPractitionerID, function ($query) use ($referringPractitionerID) {
                $query->where('patients.referring_practitioner_id', $referringPractitionerID);
            })
            ->when($status, function ($query) use ($status) {
                $query->where('patients.status', $status);
            })
            ->when($gender, function ($query) use ($gender) {
                $query->where('patients.gender', $gender);
            })
            ->when($startDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('patients.created_at', [$startDate, $endDate]);
            })
            ->groupBy('referring_practitioners.id')
            ->havingRaw('total_referred_patients>0')
            ->orderBy($orderBy, 'desc');
        if (!empty($paginate)) {
            $results = $query->paginate();

        } else {

            $results = $query->get();
        }
        return $results;
    }

    public function getPatientsByInsuranceCountReport($data = []): Collection|array|LengthAwarePaginator
    {
        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? '';
        $referringPractitionerID = $data['referring_practitioner_id'] ?? '';
        $coPayerID = $data['co_payer_id'] ?? '';
        $status = $data['status'] ?? '';
        $gender = $data['gender'] ?? '';
        $search = $data['search'] ?? '';
        $orderBy = $data['order_by'] ?? 'total_patients';
        $paginate = $data['paginate'] ?? false;

        $query = CourseMaterial::selectSub(function ($query) use ($gender, $startDate, $endDate, $status) {
            $query->from('patient_co_payers')
                ->join('patients', 'patients.id', 'patient_co_payers.patient_id')
                ->whereColumn('patient_co_payers.co_payer_id', 'co_payers.id')
                ->when($gender, function ($query) use ($gender) {
                    $query->where('patients.gender', $gender);
                })
                ->when($startDate, function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('patients.created_at', [$startDate, $endDate]);
                })
                ->when($status, function ($query) use ($status) {
                    $query->where('patients.status', $status);
                })
                ->selectRaw('count(patients.id)');
        }, 'total_patients')
            ->selectRaw("co_payers.*")
            ->when($search, function ($query) use ($search) {
                $query->where('co_payers.name', 'like', '%' . $search . '%');
                $query->orWhere('co_payers.id', 'like', '%' . $search . '%');
                $query->orWhere('co_payers.external_id', 'like', '%' . $search . '%');
                $query->orWhere('co_payers.description', 'like', '%' . $search . '%');
            })
            ->when($coPayerID, function ($query) use ($coPayerID) {
                $query->where('co_payers.id', $coPayerID);
            })
            ->groupBy('co_payers.id')
            ->havingRaw('total_patients>0')
            ->orderBy($orderBy, 'desc');
        if (!empty($paginate)) {
            $results = $query->paginate();

        } else {

            $results = $query->get();
        }
        return $results;
    }

    public function getNewPatientsReport($data = []): Collection|array|LengthAwarePaginator
    {
        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? '';
        $referringPractitionerID = $data['referring_practitioner_id'] ?? '';
        $coPayerID = $data['co_payer_id'] ?? '';
        $status = $data['status'] ?? '';
        $gender = $data['gender'] ?? '';
        $search = $data['search'] ?? '';
        $orderBy = $data['order_by'] ?? 'created_at';
        $paginate = $data['paginate'] ?? false;

        $query = Client::with(['referringPractitioner'])
            ->when($search, function ($query) use ($search) {
                $query->where('first_name', 'like', '%' . $search . '%')
                    ->orWhere('last_name', 'like', '%' . $search . '%')
                    ->orWhere('id', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%')
                    ->orWhere('mobile', 'like', '%' . $search . '%')
                    ->orWhere('id_number', 'like', '%' . $search . '%')
                    ->orWhere('external_id', 'like', '%' . $search . '%')
                    ->orWhere('middle_name', 'like', '%' . $search . '%');
            })
            ->when($gender, function ($query) use ($gender) {
                $query->where('patients.gender', $gender);
            })
            ->when($startDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('patients.created_at', [$startDate, $endDate]);
            })
            ->when($referringPractitionerID, function ($query) use ($referringPractitionerID) {
                $query->where('patients.referring_practitioner_id', $referringPractitionerID);
            })
            ->when($status, function ($query) use ($status) {
                $query->where('patients.status', $status);
            })
            ->when($coPayerID, function ($query) use ($coPayerID) {
                $query->whereHas('patient_co_payers', function ($query) use ($coPayerID) {
                    $query->where('co_payer_id', $coPayerID);
                });
            })
            ->orderBy($orderBy, 'desc');
        if (!empty($paginate)) {
            $results = $query->paginate();

        } else {

            $results = $query->get();
        }
        return $results;
    }

    public function getPatientsVisitCountByInsuranceReport($data = []): Collection|array|LengthAwarePaginator
    {
        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? '';
        $referringPractitionerID = $data['referring_practitioner_id'] ?? '';
        $doctorID = $data['doctor_id'] ?? '';
        $coPayerID = $data['co_payer_id'] ?? '';
        $status = $data['status'] ?? '';
        $gender = $data['gender'] ?? '';
        $search = $data['search'] ?? '';
        $orderBy = $data['order_by'] ?? 'total_visits';
        $paginate = $data['paginate'] ?? false;

        $query = CourseMaterial::selectSub(function ($query) use ($gender, $startDate, $endDate, $status) {
            $query->from('consultations')
                ->join('patients', 'patients.id', 'consultations.patient_id')
                ->join('invoices', 'invoices.consultation_id', 'consultations.id')
                ->whereColumn('invoices.co_payer_id', 'co_payers.id')
                ->when($gender, function ($query) use ($gender) {
                    $query->where('patients.gender', $gender);
                })
                ->when($startDate, function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('consultations.date', [$startDate, $endDate]);
                })
                ->when($status, function ($query) use ($status) {
                    $query->where('consultations.status', $status);
                })
                ->selectRaw('count(consultations.id)');
        }, 'total_visits')
            ->selectRaw("co_payers.*")
            ->when($search, function ($query) use ($search) {
                $query->where('co_payers.name', 'like', '%' . $search . '%');
                $query->orWhere('co_payers.id', 'like', '%' . $search . '%');
                $query->orWhere('co_payers.external_id', 'like', '%' . $search . '%');
                $query->orWhere('co_payers.description', 'like', '%' . $search . '%');
            })
            ->when($coPayerID, function ($query) use ($coPayerID) {
                $query->where('co_payers.id', $coPayerID);
            })
            ->groupBy('co_payers.id')
            ->havingRaw('total_visits>0')
            ->orderBy($orderBy, 'desc');
        if (!empty($paginate)) {
            $results = $query->paginate();

        } else {

            $results = $query->get();
        }
        return $results;
    }

    public function getPatientsVisitCountByPractitionerReport($data = []): Collection|array|LengthAwarePaginator
    {
        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? '';
        $referringPractitionerID = $data['referring_practitioner_id'] ?? '';
        $doctorID = $data['doctor_id'] ?? '';
        $coPayerID = $data['co_payer_id'] ?? '';
        $status = $data['status'] ?? '';
        $gender = $data['gender'] ?? '';
        $search = $data['search'] ?? '';
        $orderBy = $data['order_by'] ?? 'total_visits';
        $paginate = $data['paginate'] ?? false;

        $query = User::selectSub(function ($query) use ($coPayerID, $gender, $startDate, $endDate, $status) {
            $query->from('consultations')
                ->join('patients', 'patients.id', 'consultations.patient_id')
                ->leftJoin('invoices', 'invoices.consultation_id', 'consultations.id')
                ->whereColumn('consultations.doctor_id', 'users.id')
                ->when($gender, function ($query) use ($gender) {
                    $query->where('patients.gender', $gender);
                })
                ->when($coPayerID, function ($query) use ($coPayerID) {
                    $query->where('invoices.co_payer_id', $coPayerID);
                })
                ->when($startDate, function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('consultations.date', [$startDate, $endDate]);
                })
                ->when($status, function ($query) use ($status) {
                    $query->where('consultations.status', $status);
                })
                ->selectRaw('count(consultations.id)');
        }, 'total_visits')
            ->selectRaw("users.*")
            ->whereHas('roles', function ($query) {
                $query->where('name', 'doctor');
            })
            ->when($search, function ($query) use ($search) {
                $query->where('users.first_name', 'like', '%' . $search . '%');
                $query->orWhere('users.id', 'like', '%' . $search . '%');
                $query->orWhere('users.practice_number', 'like', '%' . $search . '%');
                $query->orWhere('users.description', 'like', '%' . $search . '%');
            })
            ->when($doctorID, function ($query) use ($doctorID) {
                $query->where('users.id', $doctorID);
            })
            ->groupBy('users.id')
            ->havingRaw('total_visits>0')
            ->orderBy($orderBy, 'desc');
        if (!empty($paginate)) {
            $results = $query->paginate();

        } else {

            $results = $query->get();
        }
        return $results;
    }


    public function getTrialBalance($data = []): Collection|array|LengthAwarePaginator
    {
        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? '';
        $currencyID = $data['currency_id'] ?? '';
        $createdByID = $data['created_by_id'] ?? '';
        $active = $data['active'] ?? '';
        $branchID = $data['branch_id'] ?? '';
        $search = $data['search'] ?? '';
        $orderBy = $data['order_by'] ?? 'created_at';
        $paginate = $data['paginate'] ?? false;
        $baseCurrency = Currency::find(Setting::where('setting_key', 'currency')->first()->setting_value);
        $operator = '/';
        if ($currencyID) {
            $currency = Currency::find($currencyID);
            $currencyFromBase = false;
        } else {
            $currencyID = $baseCurrency->id;
            $currencyFromBase = true;
        }
        $financialPeriod = FinancialPeriod::where('closed', 0)->first();
        $query = JournalEntry::leftJoin('chart_of_accounts', 'journal_entries.chart_of_account_id', 'chart_of_accounts.id')
            ->selectRaw("coalesce(sum(if(journal_entries.xrate>1,coalesce(journal_entries.debit/journal_entries.xrate,0),coalesce(journal_entries.debit*journal_entries.xrate,0))),0) debit,coalesce(sum(if(journal_entries.xrate>1,coalesce(journal_entries.credit/journal_entries.xrate,0),coalesce(journal_entries.credit*journal_entries.xrate,0))),0) credit,chart_of_accounts.name")
            ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                $query->where('journal_entries.currency_id', $currencyID);
            })
            ->when($endDate, function ($query) use ($endDate) {
                $query->where('journal_entries.date', '<=', $endDate);
            })
            ->when($branchID, function ($query) use ($branchID) {
                $query->where('journal_entries.branch_id', $branchID);
            })
            ->where('journal_entries.financial_period_id', $financialPeriod->id)
            ->groupBy('chart_of_accounts.id');

        return $query->get();
    }

    public function getLedgerReport($data = []): Collection|array|LengthAwarePaginator
    {
        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? '';
        $currencyID = $data['currency_id'] ?? '';
        $createdByID = $data['created_by_id'] ?? '';
        $active = $data['active'] ?? '';
        $branchID = $data['branch_id'] ?? '';
        $search = $data['search'] ?? '';
        $financialPeriodID = $data['financial_period_id'] ?? '';
        $orderBy = $data['order_by'] ?? 'created_at';
        $paginate = $data['paginate'] ?? false;
        $baseCurrency = Currency::find(Setting::where('setting_key', 'currency')->first()->setting_value);
        $operator = '/';
        if ($currencyID) {
            $currency = Currency::find($currencyID);
            $currencyFromBase = false;
        } else {
            $currencyID = $baseCurrency->id;
            $currencyFromBase = true;
        }
        $query = JournalEntry::leftJoin('chart_of_accounts', 'journal_entries.chart_of_account_id', 'chart_of_accounts.id')
            ->selectRaw("coalesce(sum(if(journal_entries.xrate>1,coalesce(journal_entries.debit/journal_entries.xrate,0),coalesce(journal_entries.debit*journal_entries.xrate,0))),0) debit,coalesce(sum(if(journal_entries.xrate>1,coalesce(journal_entries.credit/journal_entries.xrate,0),coalesce(journal_entries.credit*journal_entries.xrate,0))),0) credit,chart_of_accounts.name,chart_of_accounts.gl_code,chart_of_accounts.account_type")
            ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                $query->where('journal_entries.currency_id', $currencyID);
            })
            ->when(($startDate && $endDate), function ($query) use ($startDate, $endDate) {
                $query->whereBetween('journal_entries.date', [$startDate, $endDate]);
            })
            ->when($financialPeriodID, function ($query) use ($financialPeriodID) {
                $query->where('journal_entries.financial_period_id', $financialPeriodID);
            })
            ->when($branchID, function ($query) use ($branchID) {
                $query->where('journal_entries.branch_id', $branchID);
            })
            ->orderBy('account_type')
            ->groupBy('chart_of_accounts.id');

        return $query->get();
    }

    public function getBalanceSheet($data = []): Collection|array|LengthAwarePaginator
    {
        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? '';
        $currencyID = $data['currency_id'] ?? '';
        $createdByID = $data['created_by_id'] ?? '';
        $active = $data['active'] ?? '';
        $branchID = $data['branch_id'] ?? '';
        $search = $data['search'] ?? '';
        $orderBy = $data['order_by'] ?? 'created_at';
        $paginate = $data['paginate'] ?? false;
        $baseCurrency = Currency::find(Setting::where('setting_key', 'currency')->first()->setting_value);
        $operator = '/';
        if ($currencyID) {
            $currency = Currency::find($currencyID);
            $currencyFromBase = false;
        } else {
            $currencyID = $baseCurrency->id;
            $currencyFromBase = true;
        }
        $financialPeriod = !empty($data['financial_period_id']) ? FinancialPeriod::find($data['financial_period_id']) : FinancialPeriod::where('closed', 0)->first();
        $entries = JournalEntry::leftJoin('chart_of_accounts', 'journal_entries.chart_of_account_id', 'chart_of_accounts.id')
            ->selectRaw("
                        case
                            when account_type = 'equity' then
                                coalesce(sum(if(journal_entries.xrate > 1, coalesce(journal_entries.credit / journal_entries.xrate, 0),coalesce(journal_entries.credit * journal_entries.xrate, 0)))-sum(if(journal_entries.xrate > 1, coalesce(journal_entries.debit / journal_entries.xrate, 0),coalesce(journal_entries.debit * journal_entries.xrate, 0))), 0)
                            when account_type = 'fixed_asset' then
                                coalesce(sum(if(journal_entries.xrate > 1, coalesce(journal_entries.debit / journal_entries.xrate, 0),coalesce(journal_entries.debit * journal_entries.xrate, 0)))-sum(if(journal_entries.xrate > 1, coalesce(journal_entries.credit / journal_entries.xrate, 0),coalesce(journal_entries.credit * journal_entries.xrate, 0))), 0)
                            when account_type = 'current_asset' then
                                coalesce(sum(if(journal_entries.xrate > 1, coalesce(journal_entries.debit / journal_entries.xrate, 0),coalesce(journal_entries.debit * journal_entries.xrate, 0)))-sum(if(journal_entries.xrate > 1, coalesce(journal_entries.credit / journal_entries.xrate, 0),coalesce(journal_entries.credit * journal_entries.xrate, 0))), 0)
                            when account_type = 'other_asset' then
                                coalesce(sum(if(journal_entries.xrate > 1, coalesce(journal_entries.debit / journal_entries.xrate, 0),coalesce(journal_entries.debit * journal_entries.xrate, 0)))-sum(if(journal_entries.xrate > 1, coalesce(journal_entries.credit / journal_entries.xrate, 0),coalesce(journal_entries.credit * journal_entries.xrate, 0))), 0)
                            when account_type = 'cash' then
                                coalesce(sum(if(journal_entries.xrate > 1, coalesce(journal_entries.debit / journal_entries.xrate, 0),coalesce(journal_entries.debit * journal_entries.xrate, 0)))-sum(if(journal_entries.xrate > 1, coalesce(journal_entries.credit / journal_entries.xrate, 0),coalesce(journal_entries.credit * journal_entries.xrate, 0))), 0)
                            when account_type = 'bank' then
                                coalesce(sum(if(journal_entries.xrate > 1, coalesce(journal_entries.debit / journal_entries.xrate, 0),coalesce(journal_entries.debit * journal_entries.xrate, 0)))-sum(if(journal_entries.xrate > 1, coalesce(journal_entries.credit / journal_entries.xrate, 0),coalesce(journal_entries.credit * journal_entries.xrate, 0))), 0)
                            when account_type = 'stock' then
                                coalesce(sum(if(journal_entries.xrate > 1, coalesce(journal_entries.debit / journal_entries.xrate, 0),coalesce(journal_entries.debit * journal_entries.xrate, 0)))-sum(if(journal_entries.xrate > 1, coalesce(journal_entries.credit / journal_entries.xrate, 0),coalesce(journal_entries.credit * journal_entries.xrate, 0))), 0)
                            when account_type = 'other_current_liability' then
                                coalesce(sum(if(journal_entries.xrate > 1, coalesce(journal_entries.credit / journal_entries.xrate, 0),coalesce(journal_entries.credit * journal_entries.xrate, 0)))-sum(if(journal_entries.xrate > 1, coalesce(journal_entries.debit / journal_entries.xrate, 0),coalesce(journal_entries.debit * journal_entries.xrate, 0))), 0)
                            when account_type = 'credit_card' then
                                coalesce(sum(if(journal_entries.xrate > 1, coalesce(journal_entries.credit / journal_entries.xrate, 0),coalesce(journal_entries.credit * journal_entries.xrate, 0)))-sum(if(journal_entries.xrate > 1, coalesce(journal_entries.debit / journal_entries.xrate, 0),coalesce(journal_entries.debit * journal_entries.xrate, 0))), 0)
                            when account_type = 'long_term_liability' then
                                coalesce(sum(if(journal_entries.xrate > 1, coalesce(journal_entries.credit / journal_entries.xrate, 0),coalesce(journal_entries.credit * journal_entries.xrate, 0)))-sum(if(journal_entries.xrate > 1, coalesce(journal_entries.debit / journal_entries.xrate, 0),coalesce(journal_entries.debit * journal_entries.xrate, 0))), 0)
                            when account_type = 'other_liability' then
                                coalesce(sum(if(journal_entries.xrate > 1, coalesce(journal_entries.credit / journal_entries.xrate, 0),coalesce(journal_entries.credit * journal_entries.xrate, 0)))-sum(if(journal_entries.xrate > 1, coalesce(journal_entries.debit / journal_entries.xrate, 0),coalesce(journal_entries.debit * journal_entries.xrate, 0))), 0)
                            when account_type = 'income_tax' then
                                coalesce(sum(if(journal_entries.xrate > 1, coalesce(journal_entries.credit / journal_entries.xrate, 0),coalesce(journal_entries.credit * journal_entries.xrate, 0)))-sum(if(journal_entries.xrate > 1, coalesce(journal_entries.debit / journal_entries.xrate, 0),coalesce(journal_entries.debit * journal_entries.xrate, 0))), 0)

                        END as balance,
                        chart_of_accounts.name,
                        chart_of_accounts.gl_code,
                        chart_of_accounts.account_type
                        ")
            ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                $query->where('journal_entries.currency_id', $currencyID);
            })
            ->when($endDate, function ($query) use ($endDate) {
                $query->where('journal_entries.date', '<=', $endDate);
            })
            ->when($branchID, function ($query) use ($branchID) {
                $query->where('journal_entries.branch_id', $branchID);
            })
            ->whereIn('account_type', ['equity', 'fixed_asset', 'current_asset', 'current_asset', 'other_asset', 'cash', 'bank', 'stock', 'other_current_liability', 'credit_card', 'long_term_liability', 'other_liability', 'income_tax'])
            ->where('journal_entries.financial_period_id', $financialPeriod->id)
            ->where('chart_of_accounts.account_type', 'equity')
            ->groupBy('chart_of_accounts.id')->get();


        return [
            'assets' => $entries->whereIn('account_type', ['fixed_asset', 'current_asset', 'current_asset', 'other_asset', 'cash', 'bank', 'stock']),
            'liabilities' => $entries->whereIn('account_type', ['other_current_liability', 'credit_card', 'long_term_liability', 'other_liability', 'income_tax']),
            'equity' => $entries->whereIn('account_type', ['equity']),
        ];
    }

    public function getIncomeStatement($data = []): Collection|array|LengthAwarePaginator
    {
        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? '';
        $currencyID = $data['currency_id'] ?? '';
        $createdByID = $data['created_by_id'] ?? '';
        $active = $data['active'] ?? '';
        $branchID = $data['branch_id'] ?? '';
        $financialPeriodID = $data['financial_period_id'] ?? '';
        $search = $data['search'] ?? '';
        $orderBy = $data['order_by'] ?? 'created_at';
        $paginate = $data['paginate'] ?? false;
        $baseCurrency = Currency::find(Setting::where('setting_key', 'currency')->first()->setting_value);
        $operator = '/';
        if ($currencyID) {
            $currency = Currency::find($currencyID);
            $currencyFromBase = false;
        } else {
            $currencyID = $baseCurrency->id;
            $currencyFromBase = true;
        }
        $entries = JournalEntry::leftJoin('chart_of_accounts', 'journal_entries.chart_of_account_id', 'chart_of_accounts.id')
            ->selectRaw("
                        case
                            when account_type = 'income' then
                                coalesce(sum(if(journal_entries.xrate > 1, coalesce(journal_entries.credit / journal_entries.xrate, 0),coalesce(journal_entries.credit * journal_entries.xrate, 0)))-sum(if(journal_entries.xrate > 1, coalesce(journal_entries.debit / journal_entries.xrate, 0),coalesce(journal_entries.debit * journal_entries.xrate, 0))), 0)
                            when account_type = 'other_income' then
                                coalesce(sum(if(journal_entries.xrate > 1, coalesce(journal_entries.credit / journal_entries.xrate, 0),coalesce(journal_entries.credit * journal_entries.xrate, 0)))-sum(if(journal_entries.xrate > 1, coalesce(journal_entries.debit / journal_entries.xrate, 0),coalesce(journal_entries.debit * journal_entries.xrate, 0))), 0)
                            when account_type = 'expense' then
                                coalesce(sum(if(journal_entries.xrate > 1, coalesce(journal_entries.debit / journal_entries.xrate, 0),coalesce(journal_entries.debit * journal_entries.xrate, 0)))-sum(if(journal_entries.xrate > 1, coalesce(journal_entries.credit / journal_entries.xrate, 0),coalesce(journal_entries.credit * journal_entries.xrate, 0))), 0)
                            when account_type = 'cost_of_goods_sold' then
                                coalesce(sum(if(journal_entries.xrate > 1, coalesce(journal_entries.debit / journal_entries.xrate, 0),coalesce(journal_entries.debit * journal_entries.xrate, 0)))-sum(if(journal_entries.xrate > 1, coalesce(journal_entries.credit / journal_entries.xrate, 0),coalesce(journal_entries.credit * journal_entries.xrate, 0))), 0)
                            when account_type = 'other_expense' then
                                coalesce(sum(if(journal_entries.xrate > 1, coalesce(journal_entries.debit / journal_entries.xrate, 0),coalesce(journal_entries.debit * journal_entries.xrate, 0)))-sum(if(journal_entries.xrate > 1, coalesce(journal_entries.credit / journal_entries.xrate, 0),coalesce(journal_entries.credit * journal_entries.xrate, 0))), 0)
                        END as balance,
                        chart_of_accounts.name,
                        chart_of_accounts.gl_code,
                        chart_of_accounts.account_type
                        ")
            ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                $query->where('journal_entries.currency_id', $currencyID);
            })
            ->when(($startDate && $endDate), function ($query) use ($startDate, $endDate) {
                $query->whereBetween('journal_entries.date', [$startDate, $endDate]);
            })
            ->when($branchID, function ($query) use ($branchID) {
                $query->where('journal_entries.branch_id', $branchID);
            })
            ->when($financialPeriodID, function ($query) use ($financialPeriodID) {
                $query->where('journal_entries.financial_period_id', $financialPeriodID);
            })
            ->whereIn('account_type', ['income', 'other_income', 'expense', 'cost_of_goods_sold', 'other_expense'])
            ->where('chart_of_accounts.account_type', 'equity')
            ->groupBy('chart_of_accounts.id')->get();


        return [
            'expenses' => $entries->whereIn('account_type', ['cost_of_goods_sold']),
            'other_expenses' => $entries->whereIn('account_type', ['expense', 'other_expense']),
            'income' => $entries->whereIn('account_type', ['income']),
            'other_income' => $entries->whereIn('account_type', ['other_income']),
        ];
    }

    public function getSystemFinancialSummaryReport($data = []): Collection|array|LengthAwarePaginator
    {
        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? '';
        $startTime = $data['start_time'] ?? '';
        $endTime = $data['end_time'] ?? '';
        $currencyID = $data['currency_id'] ?? '';
        $paginate = $data['paginate'] ?? false;
        $referringPractitionerID = $data['referring_practitioner_id'] ?? '';
        $createdByType = $data['created_by_type'] ?? '';
        $patientID = $data['patient_id'] ?? '';
        $doctorID = $data['doctor_id'] ?? '';
        $coPayerID = $data['co_payer_id'] ?? '';
        $status = $data['status'] ?? '';
        $branchID = $data['branch_id'] ?? '';
        $baseCurrency = Currency::find(Setting::where('setting_key', 'currency')->first()->setting_value);
        $operator = '/';
        if ($currencyID) {
            $currency = Currency::find($currencyID);
            if ($currency->xrate > $baseCurrency->xrate) {
                $operator = '/';
            } else {
                $operator = '*';
            }
            $currencyFromBase = false;
        } else {
            $currencyID = $baseCurrency->id;
            $currencyFromBase = true;
        }
        $query = User::selectSub(function ($query) use ($doctorID, $patientID, $currencyID, $currencyFromBase, $branchID, $coPayerID, $startDate, $endDate) {
            $query->selectRaw('coalesce(sum(if(currency_id=' . $currencyID . ',coalesce(amount,0),if(xrate<1,coalesce(amount*xrate,0),coalesce(amount/xrate,0)))),0)')
                ->from('invoices')
                ->whereColumn('invoices.doctor_id', 'users.id')
                ->when($patientID, function ($query) use ($patientID) {
                    $query->where('invoices.patient_id', $patientID);
                })
                ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                    $query->where('invoices.currency_id', $currencyID);
                })
                ->when($branchID, function ($query) use ($branchID) {
                    $query->where('invoices.branch_id', $branchID);
                })
                ->when($coPayerID, function ($query) use ($coPayerID) {
                    $query->where('invoices.co_payer_id', $coPayerID);
                })
                ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('invoices.date', [$startDate, $endDate]);
                })
                ->groupBy('doctor_id')
                ->limit(1);
        }, 'amount')
            ->selectSub(function ($query) use ($doctorID, $patientID, $currencyID, $currencyFromBase, $branchID, $coPayerID, $startDate, $endDate) {
                $query->selectRaw('coalesce(sum(if(invoice_payments.currency_id=' . $currencyID . ',coalesce(invoice_payments.amount,0),if(invoice_payments.xrate<1,coalesce(invoice_payments.amount*invoice_payments.xrate,0),coalesce(invoice_payments.amount/invoice_payments.xrate,0)))),0)')
                    ->from('invoice_payments')
                    ->join('invoices', 'invoices.id', 'invoice_payments.invoice_id')
                    ->whereColumn('invoices.doctor_id', 'users.id')
                    ->when($patientID, function ($query) use ($patientID) {
                        $query->where('invoices.patient_id', $patientID);
                    })
                    ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                        $query->where('invoices.currency_id', $currencyID);
                    })
                    ->when($branchID, function ($query) use ($branchID) {
                        $query->where('invoices.branch_id', $branchID);
                    })
                    ->when($coPayerID, function ($query) use ($coPayerID) {
                        $query->where('invoices.co_payer_id', $coPayerID);
                    })
                    ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                        $query->whereBetween('invoices.date', [$startDate, $endDate]);
                    })
                    ->groupBy('invoices.doctor_id')
                    ->limit(1);
            }, 'payments')
            ->when($doctorID, function ($query) use ($doctorID) {
                $query->where('users.id', $doctorID);
            })
            ->selectRaw('users.*')
            ->havingRaw('amount>0 or payments>0');

        if (!empty($paginate)) {
            $results = $query->paginate();

        } else {

            $results = $query->get();
        }
        return $results;
    }

    public function getPatientsFinancialSummaryReport($data = []): Collection|array|LengthAwarePaginator
    {
        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? '';
        $startTime = $data['start_time'] ?? '';
        $endTime = $data['end_time'] ?? '';
        $currencyID = $data['currency_id'] ?? '';
        $paginate = $data['paginate'] ?? false;
        $referringPractitionerID = $data['referring_practitioner_id'] ?? '';
        $createdByType = $data['created_by_type'] ?? '';
        $patientID = $data['patient_id'] ?? '';
        $doctorID = $data['doctor_id'] ?? '';
        $coPayerID = $data['co_payer_id'] ?? '';
        $status = $data['status'] ?? '';
        $branchID = $data['branch_id'] ?? '';
        $baseCurrency = Currency::find(Setting::where('setting_key', 'currency')->first()->setting_value);
        $operator = '/';
        if ($currencyID) {
            $currency = Currency::find($currencyID);
            if ($currency->xrate > $baseCurrency->xrate) {
                $operator = '/';
            } else {
                $operator = '*';
            }
            $currencyFromBase = false;
        } else {
            $currencyID = $baseCurrency->id;
            $currencyFromBase = true;
        }
        $query = Invoice::join('patients', 'patients.id', 'invoices.patient_id')
            ->when($patientID, function ($query) use ($patientID) {
                $query->where('invoices.patient_id', $patientID);
            })
            ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                $query->where('invoices.currency_id', $currencyID);
            })
            ->when($branchID, function ($query) use ($branchID) {
                $query->where('invoices.branch_id', $branchID);
            })
            ->when($coPayerID, function ($query) use ($coPayerID) {
                $query->where('invoices.co_payer_id', $coPayerID);
            })
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('invoices.date', [$startDate, $endDate]);
            })
            ->selectRaw('patients.id,patient_id,CONCAT_WS(" ",patients.first_name,patients.middle_name,patients.last_name) name,coalesce(sum(if(currency_id=' . $currencyID . ',coalesce(amount,0),if(xrate<1,coalesce(amount*xrate,0),coalesce(amount/xrate,0)))),0) as amount,coalesce(sum(if(currency_id=' . $currencyID . ',coalesce(balance,0),if(xrate<1,coalesce(balance*xrate,0),coalesce(balance/xrate,0)))),0) as balance,coalesce(sum(if(currency_id=' . $currencyID . ',coalesce(cash_balance,0),if(xrate<1,coalesce(cash_balance*xrate,0),coalesce(cash_balance/xrate,0)))),0) as cash_balance,coalesce(sum(if(currency_id=' . $currencyID . ',coalesce(co_payer_balance,0),if(xrate<1,coalesce(co_payer_balance*xrate,0),coalesce(co_payer_balance/xrate,0)))),0) as co_payer_balance,coalesce(sum(if(currency_id=' . $currencyID . ',coalesce(cash_amount,0),if(xrate<1,coalesce(cash_amount*xrate,0),coalesce(cash_amount/xrate,0)))),0) as cash_amount,coalesce(sum(if(currency_id=' . $currencyID . ',coalesce(co_payer_amount,0),if(xrate<1,coalesce(co_payer_amount*xrate,0),coalesce(co_payer_amount/xrate,0)))),0) as co_payer_amount,coalesce(sum(if(currency_id=' . $currencyID . ',coalesce(shortfall,0),if(xrate<1,coalesce(shortfall*xrate,0),coalesce(shortfall/xrate,0)))),0) as shortfall')
            ->groupBy('invoices.patient_id');

        if (!empty($paginate)) {
            $results = $query->paginate();

        } else {

            $results = $query->get();
        }
        return $results;
    }

    public function getPatientsInsuranceOnAccountReport($data = []): Collection|array|LengthAwarePaginator
    {
        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? '';
        $startTime = $data['start_time'] ?? '';
        $endTime = $data['end_time'] ?? '';
        $currencyID = $data['currency_id'] ?? '';
        $paginate = $data['paginate'] ?? false;
        $referringPractitionerID = $data['referring_practitioner_id'] ?? '';
        $createdByType = $data['created_by_type'] ?? '';
        $patientID = $data['patient_id'] ?? '';
        $doctorID = $data['doctor_id'] ?? '';
        $coPayerID = $data['co_payer_id'] ?? '';
        $status = $data['status'] ?? '';
        $branchID = $data['branch_id'] ?? '';
        $baseCurrency = Currency::find(Setting::where('setting_key', 'currency')->first()->setting_value);
        $operator = '/';
        if ($currencyID) {
            $currency = Currency::find($currencyID);
            if ($currency->xrate > $baseCurrency->xrate) {
                $operator = '/';
            } else {
                $operator = '*';
            }
            $currencyFromBase = false;
        } else {
            $currencyID = $baseCurrency->id;
            $currencyFromBase = true;
        }
        $query = Invoice::join('patients', 'patients.id', 'invoices.patient_id')
            ->whereIn('invoices.sponsor', ['co_payer', 'both'])
            ->when($patientID, function ($query) use ($patientID) {
                $query->where('invoices.patient_id', $patientID);
            })
            ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                $query->where('invoices.currency_id', $currencyID);
            })
            ->when($branchID, function ($query) use ($branchID) {
                $query->where('invoices.branch_id', $branchID);
            })
            ->when($coPayerID, function ($query) use ($coPayerID) {
                $query->where('invoices.co_payer_id', $coPayerID);
            })
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('invoices.date', [$startDate, $endDate]);
            })
            ->selectRaw('patients.id,patient_id,CONCAT_WS(" ",patients.first_name,patients.middle_name,patients.last_name) name,coalesce(sum(if(currency_id=' . $currencyID . ',coalesce(co_payer_balance,0),if(xrate<1,coalesce(co_payer_balance*xrate,0),coalesce(co_payer_balance/xrate,0)))),0) as co_payer_balance,coalesce(sum(if(currency_id=' . $currencyID . ',coalesce(co_payer_amount,0),if(xrate<1,coalesce(co_payer_amount*xrate,0),coalesce(co_payer_amount/xrate,0)))),0) as co_payer_amount,coalesce(sum(if(currency_id=' . $currencyID . ',coalesce(shortfall,0),if(xrate<1,coalesce(shortfall*xrate,0),coalesce(shortfall/xrate,0)))),0) as shortfall')
            ->groupBy('invoices.patient_id');

        if (!empty($paginate)) {
            $results = $query->paginate();

        } else {

            $results = $query->get();
        }
        return $results;
    }

    public function getPatientsOnAccountBalanceAnalysisReport($data = []): Collection|array|LengthAwarePaginator
    {
        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? '';
        $startTime = $data['start_time'] ?? '';
        $endTime = $data['end_time'] ?? '';
        $currencyID = $data['currency_id'] ?? '';
        $paginate = $data['paginate'] ?? false;
        $referringPractitionerID = $data['referring_practitioner_id'] ?? '';
        $createdByType = $data['created_by_type'] ?? '';
        $patientID = $data['patient_id'] ?? '';
        $doctorID = $data['doctor_id'] ?? '';
        $coPayerID = $data['co_payer_id'] ?? '';
        $status = $data['status'] ?? '';
        $branchID = $data['branch_id'] ?? '';
        $baseCurrency = Currency::find(Setting::where('setting_key', 'currency')->first()->setting_value);
        $operator = '/';
        if ($currencyID) {
            $currency = Currency::find($currencyID);
            if ($currency->xrate > $baseCurrency->xrate) {
                $operator = '/';
            } else {
                $operator = '*';
            }
            $currencyFromBase = false;
        } else {
            $currencyID = $baseCurrency->id;
            $currencyFromBase = true;
        }
        $query = Invoice::join('patients', 'patients.id', 'invoices.patient_id')
            ->when($patientID, function ($query) use ($patientID) {
                $query->where('invoices.patient_id', $patientID);
            })
            ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                $query->where('invoices.currency_id', $currencyID);
            })
            ->when($branchID, function ($query) use ($branchID) {
                $query->where('invoices.branch_id', $branchID);
            })
            ->when($coPayerID, function ($query) use ($coPayerID) {
                $query->where('invoices.co_payer_id', $coPayerID);
            })
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('invoices.date', [$startDate, $endDate]);
            })
            ->selectRaw('patients.id,patient_id,CONCAT_WS(" ",patients.first_name,patients.middle_name,patients.last_name) name,coalesce(sum(if(currency_id=' . $currencyID . ',coalesce(cash_balance,0),if(xrate<1,coalesce(cash_balance*xrate,0),coalesce(cash_balance/xrate,0)))),0) as cash_balance,coalesce(sum(if(currency_id=' . $currencyID . ',coalesce(cash_amount,0),if(xrate<1,coalesce(cash_amount*xrate,0),coalesce(cash_amount/xrate,0)))),0) as cash_amount')
            ->groupBy('invoices.patient_id');

        if (!empty($paginate)) {
            $results = $query->paginate();

        } else {

            $results = $query->get();
        }
        return $results;
    }

    public function getProviderProductivityReport($data = [])
    {
        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? '';
        $startTime = $data['start_time'] ?? '';
        $endTime = $data['end_time'] ?? '';
        $currencyID = $data['currency_id'] ?? '';
        $paginate = $data['paginate'] ?? false;
        $referringPractitionerID = $data['referring_practitioner_id'] ?? '';
        $createdByType = $data['created_by_type'] ?? '';
        $patientID = $data['patient_id'] ?? '';
        $doctorID = $data['doctor_id'] ?? '';
        $coPayerID = $data['co_payer_id'] ?? '';
        $status = $data['status'] ?? '';
        $branchID = $data['branch_id'] ?? '';
        $baseCurrency = Currency::find(Setting::where('setting_key', 'currency')->first()->setting_value);
        $operator = '/';
        if ($currencyID) {
            $currency = Currency::find($currencyID);
            if ($currency->xrate > $baseCurrency->xrate) {
                $operator = '/';
            } else {
                $operator = '*';
            }
            $currencyFromBase = false;
        } else {
            $currencyID = $baseCurrency->id;
            $currencyFromBase = true;
        }
        $query = User::selectSub(function ($query) use ($doctorID, $patientID, $currencyID, $currencyFromBase, $branchID, $coPayerID, $startDate, $endDate) {
            $query->selectRaw('coalesce(sum(if(currency_id=' . $currencyID . ',coalesce(amount,0),if(xrate<1,coalesce(amount*xrate,0),coalesce(amount/xrate,0)))),0)')
                ->from('invoices')
                ->whereColumn('invoices.doctor_id', 'users.id')
                ->when($patientID, function ($query) use ($patientID) {
                    $query->where('invoices.patient_id', $patientID);
                })
                ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                    $query->where('invoices.currency_id', $currencyID);
                })
                ->when($branchID, function ($query) use ($branchID) {
                    $query->where('invoices.branch_id', $branchID);
                })
                ->when($coPayerID, function ($query) use ($coPayerID) {
                    $query->where('invoices.co_payer_id', $coPayerID);
                })
                ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('invoices.date', [$startDate, $endDate]);
                })
                ->groupBy('doctor_id')
                ->limit(1);
        }, 'amount')
            ->selectSub(function ($query) use ($doctorID, $patientID, $currencyID, $currencyFromBase, $branchID, $coPayerID, $startDate, $endDate) {
                $query->selectRaw('coalesce(sum(if(invoice_payments.currency_id=' . $currencyID . ',coalesce(invoice_payments.amount,0),if(invoice_payments.xrate<1,coalesce(invoice_payments.amount*invoice_payments.xrate,0),coalesce(invoice_payments.amount/invoice_payments.xrate,0)))),0)')
                    ->from('invoice_payments')
                    ->join('invoices', 'invoices.id', 'invoice_payments.invoice_id')
                    ->whereColumn('invoices.doctor_id', 'users.id')
                    ->when($patientID, function ($query) use ($patientID) {
                        $query->where('invoices.patient_id', $patientID);
                    })
                    ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                        $query->where('invoices.currency_id', $currencyID);
                    })
                    ->when($branchID, function ($query) use ($branchID) {
                        $query->where('invoices.branch_id', $branchID);
                    })
                    ->when($coPayerID, function ($query) use ($coPayerID) {
                        $query->where('invoices.co_payer_id', $coPayerID);
                    })
                    ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                        $query->whereBetween('invoices.date', [$startDate, $endDate]);
                    })
                    ->groupBy('invoices.doctor_id')
                    ->limit(1);
            }, 'payments')
            ->selectSub(function ($query) use ($doctorID, $patientID, $currencyID, $currencyFromBase, $branchID, $coPayerID, $startDate, $endDate) {
                $query->from('consultations')
                    ->join('invoices', 'invoices.id', 'consultations.invoice_id')
                    ->whereColumn('consultations.doctor_id', 'users.id')
                    ->when($patientID, function ($query) use ($patientID) {
                        $query->where('consultations.patient_id', $patientID);
                    })
                    ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                        $query->where('invoices.currency_id', $currencyID);
                    })
                    ->when($branchID, function ($query) use ($branchID) {
                        $query->where('invoices.branch_id', $branchID);
                    })
                    ->when($coPayerID, function ($query) use ($coPayerID) {
                        $query->where('invoices.co_payer_id', $coPayerID);
                    })
                    ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                        $query->whereBetween('consultations.date', [$startDate, $endDate]);
                    })
                    ->groupBy('consultations.doctor_id')
                    ->selectRaw('coalesce(count(*),0)');
            }, 'total_consultations')
            ->whereHas('roles', function ($query) {
                $query->where('name', 'doctor');
            })
            ->when($doctorID, function ($query) use ($doctorID) {
                $query->where('users.id', $doctorID);
            })
            ->selectRaw('users.*');
        if (!empty($paginate)) {
            $results = $query->paginate();

        } else {

            $results = $query->get();
        }
        return $results;
    }

    public function getChargesByProviderReport($data = [])
    {
        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? '';
        $startTime = $data['start_time'] ?? '';
        $endTime = $data['end_time'] ?? '';
        $currencyID = $data['currency_id'] ?? '';
        $paginate = $data['paginate'] ?? false;
        $referringPractitionerID = $data['referring_practitioner_id'] ?? '';
        $createdByType = $data['created_by_type'] ?? '';
        $patientID = $data['patient_id'] ?? '';
        $doctorID = $data['doctor_id'] ?? '';
        $coPayerID = $data['co_payer_id'] ?? '';
        $status = $data['status'] ?? '';
        $branchID = $data['branch_id'] ?? '';
        $baseCurrency = Currency::find(Setting::where('setting_key', 'currency')->first()->setting_value);
        $operator = '/';
        if ($currencyID) {
            $currency = Currency::find($currencyID);
            if ($currency->xrate > $baseCurrency->xrate) {
                $operator = '/';
            } else {
                $operator = '*';
            }
            $currencyFromBase = false;
        } else {
            $currencyID = $baseCurrency->id;
            $currencyFromBase = true;
        }
        $query = User::selectSub(function ($query) use ($doctorID, $patientID, $currencyID, $currencyFromBase, $branchID, $coPayerID, $startDate, $endDate) {
            $query->selectRaw('coalesce(sum(if(invoices.xrate>1,amount*xrate,amount/xrate)),0)')
                ->from('invoices')
                ->whereColumn('invoices.doctor_id', 'users.id')
                ->when($patientID, function ($query) use ($patientID) {
                    $query->where('invoices.patient_id', $patientID);
                })
                ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                    $query->where('invoices.currency_id', $currencyID);
                })
                ->when($branchID, function ($query) use ($branchID) {
                    $query->where('invoices.branch_id', $branchID);
                })
                ->when($coPayerID, function ($query) use ($coPayerID) {
                    $query->where('invoices.co_payer_id', $coPayerID);
                })
                ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('invoices.date', [$startDate, $endDate]);
                })
                ->groupBy('doctor_id')
                ->limit(1);
        }, 'amount')
            ->whereHas('roles', function ($query) {
                $query->where('name', 'doctor');
            })
            ->when($doctorID, function ($query) use ($doctorID) {
                $query->where('users.id', $doctorID);
            })
            ->selectRaw('users.*');
        if (!empty($paginate)) {
            $results = $query->paginate();

        } else {

            $results = $query->get();
        }
        return $results;
    }

    public function getChargesByProviderByMonthReport($data = [])
    {
        $startDate = Carbon::parse($data['start_date'] ?? '');
        $endDate = Carbon::parse($data['end_date'] ?? '');
        $startTime = $data['start_time'] ?? '';
        $endTime = $data['end_time'] ?? '';
        $currencyID = $data['currency_id'] ?? '';
        $paginate = $data['paginate'] ?? false;
        $referringPractitionerID = $data['referring_practitioner_id'] ?? '';
        $createdByType = $data['created_by_type'] ?? '';
        $patientID = $data['patient_id'] ?? '';
        $doctorID = $data['doctor_id'] ?? '';
        $coPayerID = $data['co_payer_id'] ?? '';
        $status = $data['status'] ?? '';
        $branchID = $data['branch_id'] ?? '';
        $baseCurrency = Currency::find(Setting::where('setting_key', 'currency')->first()->setting_value);
        $operator = '/';
        if ($currencyID) {
            $currency = Currency::find($currencyID);
            if ($currency->xrate > $baseCurrency->xrate) {
                $operator = '/';
            } else {
                $operator = '*';
            }
            $currencyFromBase = false;
        } else {
            $currencyID = $baseCurrency->id;
            $currencyFromBase = true;
        }
        $users = User::whereHas('roles', function ($query) {
            $query->where('name', 'doctor');
        })->get()->transform(function ($item) use ($startDate, $endDate, $patientID, $currencyID, $currencyFromBase, $branchID, $coPayerID) {
            $results = [];
            $nextDate = clone $startDate;
            do {
                $amount = Invoice::selectRaw('coalesce(sum(if(invoices.xrate>1,amount*xrate,amount/xrate)),0) as amount')
                    ->where('invoices.doctor_id', $item->id)
                    ->when($patientID, function ($query) use ($patientID) {
                        $query->where('invoices.patient_id', $patientID);
                    })
                    ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                        $query->where('invoices.currency_id', $currencyID);
                    })
                    ->when($branchID, function ($query) use ($branchID) {
                        $query->where('invoices.branch_id', $branchID);
                    })
                    ->when($coPayerID, function ($query) use ($coPayerID) {
                        $query->where('invoices.co_payer_id', $coPayerID);
                    })
                    ->whereBetween('invoices.date', [$nextDate->startOfMonth()->format('Y-m-d'), $nextDate->endOfMonth()->format('Y-m-d')])
                    ->first()->amount;
                $results[] = [
                    'month' => $nextDate->format('M Y'),
                    'amount' => $amount
                ];
                $nextDate = $nextDate->addMonthNoOverflow();
            } while (Carbon::parse($endDate)->greaterThan($nextDate));

            $item->data = $results;
            return $item;
        });

        return $users;
    }

    public function getChargesByProviderByMonthByCoPayerReport($data = [])
    {
        $startDate = Carbon::parse($data['start_date'] ?? '');
        $endDate = Carbon::parse($data['end_date'] ?? '');
        $startTime = $data['start_time'] ?? '';
        $endTime = $data['end_time'] ?? '';
        $currencyID = $data['currency_id'] ?? '';
        $paginate = $data['paginate'] ?? false;
        $referringPractitionerID = $data['referring_practitioner_id'] ?? '';
        $createdByType = $data['created_by_type'] ?? '';
        $patientID = $data['patient_id'] ?? '';
        $doctorID = $data['doctor_id'] ?? '';
        $coPayerID = $data['co_payer_id'] ?? '';
        $status = $data['status'] ?? '';
        $branchID = $data['branch_id'] ?? '';
        $baseCurrency = Currency::find(Setting::where('setting_key', 'currency')->first()->setting_value);
        $operator = '/';
        if ($currencyID) {
            $currency = Currency::find($currencyID);
            if ($currency->xrate > $baseCurrency->xrate) {
                $operator = '/';
            } else {
                $operator = '*';
            }
            $currencyFromBase = false;
        } else {
            $currencyID = $baseCurrency->id;
            $currencyFromBase = true;
        }
        $users = User::whereHas('roles', function ($query) {
            $query->where('name', 'doctor');
        })->get()->transform(function ($item, $key) use ($startDate, $endDate, $patientID, $currencyID, $currencyFromBase, $branchID, $coPayerID) {
            $results = [];
            foreach (CourseMaterial::where('active', 1)->get() as $key) {
                $temp = [];
                $total = 0;
                $nextDate = clone $startDate;
                do {
                    $amount = Invoice::selectRaw('coalesce(sum(if(invoices.xrate>1,amount*xrate,amount/xrate)),0) as amount')
                        ->where('invoices.doctor_id', $item->id)
                        ->when($patientID, function ($query) use ($patientID) {
                            $query->where('invoices.patient_id', $patientID);
                        })
                        ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                            $query->where('invoices.currency_id', $currencyID);
                        })
                        ->when($branchID, function ($query) use ($branchID) {
                            $query->where('invoices.branch_id', $branchID);
                        })
                        ->where('invoices.co_payer_id', $key->id)
                        ->whereBetween('invoices.date', [$nextDate->startOfMonth()->format('Y-m-d'), $nextDate->endOfMonth()->format('Y-m-d')])
                        ->first()->amount;
                    $total += $amount;
                    $temp[] = [
                        'month' => $nextDate->format('M Y'),
                        'amount' => $amount
                    ];
                    $nextDate = $nextDate->addMonthNoOverflow();
                } while (Carbon::parse($endDate)->greaterThan($nextDate));

                $results[] = [
                    'co_payer' => $key,
                    'total' => $total,
                    'data' => $temp
                ];
            }

            $item->data = $results;
            return $item;
        });

        return $users;
    }

    public function getPaymentsReport($data = [])
    {
        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? '';
        $currencyID = $data['currency_id'] ?? '';
        $paidBy = $data['paid_by'] ?? '';
        $coPayerID = $data['co_payer_id'] ?? '';
        $paymentTypeID = $data['payment_type_id'] ?? '';
        $doctorID = $data['doctor_id'] ?? '';
        $branchID = $data['branch_id'] ?? '';
        $patientID = $data['patient_id'] ?? '';
        $paginate = $data['paginate'] ?? false;
        $baseCurrency = Currency::find(Setting::where('setting_key', 'currency')->first()->setting_value);
        $operator = '/';
        if ($currencyID) {
            $currency = Currency::find($currencyID);
            if ($currency->xrate > $baseCurrency->xrate) {
                $operator = '/';
            } else {
                $operator = '*';
            }
            $currencyFromBase = false;
        } else {
            $currencyID = $baseCurrency->id;
            $currencyFromBase = true;
        }
        $query = InvoicePayment::with(['currency', 'patient', 'invoice', 'paymentType', 'coPayer'])
            ->when(($startDate && $endDate), function (Builder $query) use ($startDate, $endDate) {
                $query->whereBetween('date', [$startDate, $endDate]);
            })
            ->when($doctorID, function (Builder $query) use ($doctorID) {
                $query->whereHas('invoice', function (Builder $query) use ($doctorID) {
                    $query->where('doctor_id', $doctorID);
                });
            })
            ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                $query->where('invoice_payments.currency_id', $currencyID);
            })
            ->when($patientID, function ($query) use ($patientID) {
                $query->where('invoice_payments.patient_id', $patientID);
            })
            ->when($paidBy, function ($query) use ($paidBy) {
                $query->where('invoice_payments.paid_by', $paidBy);
            })
            ->when($paymentTypeID, function ($query) use ($paymentTypeID) {
                $query->where('invoice_payments.payment_type_id', $paymentTypeID);
            })
            ->when($coPayerID, function ($query) use ($coPayerID) {
                $query->where('invoice_payments.co_payer_id', $coPayerID);
            })
            ->when($branchID, function ($query) use ($branchID) {
                $query->where('invoice_payments.branch_id', $branchID);
            });
        if (!empty($paginate)) {
            $results = $query->paginate();
        } else {
            $results = $query->get();
        }
        return $results;
    }

    public function getPaymentsByInsuranceReport($data = [])
    {
        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? '';
        $currencyID = $data['currency_id'] ?? '';
        $paidBy = $data['paid_by'] ?? '';
        $coPayerID = $data['co_payer_id'] ?? '';
        $paymentTypeID = $data['payment_type_id'] ?? '';
        $doctorID = $data['doctor_id'] ?? '';
        $branchID = $data['branch_id'] ?? '';
        $patientID = $data['patient_id'] ?? '';
        $paginate = $data['paginate'] ?? false;
        $baseCurrency = Currency::find(Setting::where('setting_key', 'currency')->first()->setting_value);
        $operator = '/';
        if ($currencyID) {
            $currency = Currency::find($currencyID);
            if ($currency->xrate > $baseCurrency->xrate) {
                $operator = '/';
            } else {
                $operator = '*';
            }
            $currencyFromBase = false;
        } else {
            $currencyID = $baseCurrency->id;
            $currencyFromBase = true;
        }
        $query = CourseMaterial::leftJoin('invoice_payments', 'invoice_payments.co_payer_id', 'co_payers.id')
            ->leftJoin('invoices', 'invoice_payments.invoice_id', 'invoices.id')
            ->when(($startDate && $endDate), function (Builder $query) use ($startDate, $endDate) {
                $query->whereBetween('invoice_payments.date', [$startDate, $endDate]);
            })
            ->when($doctorID, function (Builder $query) use ($doctorID) {
                $query->where('invoices.doctor_id', $doctorID);
            })
            ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                $query->where('invoice_payments.currency_id', $currencyID);
            })
            ->when($patientID, function ($query) use ($patientID) {
                $query->where('invoice_payments.patient_id', $patientID);
            })
            ->when($paidBy, function ($query) use ($paidBy) {
                $query->where('invoice_payments.paid_by', $paidBy);
            })
            ->when($paymentTypeID, function ($query) use ($paymentTypeID) {
                $query->where('invoice_payments.payment_type_id', $paymentTypeID);
            })
            ->when($coPayerID, function ($query) use ($coPayerID) {
                $query->where('invoice_payments.co_payer_id', $coPayerID);
            })
            ->when($branchID, function ($query) use ($branchID) {
                $query->where('invoice_payments.branch_id', $branchID);
            })
            ->groupBy('co_payers.id')
            ->selectRaw('co_payers.name,co_payers.id,sum(coalesce(if(invoice_payments.xrate>1,invoice_payments.amount*invoice_payments.xrate,invoice_payments.amount/invoice_payments.xrate),0)) as amount');
        if (!empty($paginate)) {
            $results = $query->paginate();
        } else {
            $results = $query->get();
        }
        return $results;
    }

    public function procedureProductivityByInsuranceReport($data = [])
    {
        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? '';
        $currencyID = $data['currency_id'] ?? '';
        $paidBy = $data['paid_by'] ?? '';
        $coPayerID = $data['co_payer_id'] ?? '';
        $paymentTypeID = $data['payment_type_id'] ?? '';
        $doctorID = $data['doctor_id'] ?? '';
        $branchID = $data['branch_id'] ?? '';
        $patientID = $data['patient_id'] ?? '';
        $paginate = $data['paginate'] ?? false;
        $baseCurrency = Currency::find(Setting::where('setting_key', 'currency')->first()->setting_value);
        $operator = '/';
        if ($currencyID) {
            $currency = Currency::find($currencyID);
            if ($currency->xrate > $baseCurrency->xrate) {
                $operator = '/';
            } else {
                $operator = '*';
            }
            $currencyFromBase = false;
        } else {
            $currencyID = $baseCurrency->id;
            $currencyFromBase = true;
        }
        return CourseMaterial::when($coPayerID, function ($query) use ($coPayerID) {
            $query->where('id', $coPayerID);
        })->get()->map(function ($item) use ($startDate, $endDate, $currencyID, $currencyFromBase, $patientID, $doctorID, $paymentTypeID, $paidBy, $branchID) {
            $results = InvoiceItem::leftJoin('invoices', 'invoices.id', 'invoice_items.invoice_id')
                ->when(($startDate && $endDate), function (Builder $query) use ($startDate, $endDate) {
                    $query->whereBetween('invoices.date', [$startDate, $endDate]);
                })
                ->where('invoices.co_payer_id', $item->id)
                ->where('invoice_items.co_payer_amount', '>', 0)
                ->when($doctorID, function (Builder $query) use ($doctorID) {
                    $query->where('invoices.doctor_id', $doctorID);
                })
                ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                    $query->where('invoices.currency_id', $currencyID);
                })
                ->when($patientID, function ($query) use ($patientID) {
                    $query->where('invoices.patient_id', $patientID);
                })
                ->when($branchID, function ($query) use ($branchID) {
                    $query->where('invoices.branch_id', $branchID);
                })
                ->groupBy('invoice_items.tariff_id')
                ->selectRaw('invoice_items.tariff_code,invoice_items.name,sum(invoice_items.qty) total_qty,sum(coalesce(if(invoices.xrate>1,invoice_items.co_payer_amount*invoices.xrate,invoice_items.co_payer_amount/invoices.xrate),0)) as amount,sum(invoice_items.qty) total_qty,sum(coalesce(if(invoices.xrate>1,invoice_items.award*invoices.xrate,invoice_items.award/invoices.xrate),0)) as award')
                ->get();
            $item->data = $results;
            return $item;
        });
    }

    public function getPatientsPaymentsDetailedReport($data = [])
    {
        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? '';
        $currencyID = $data['currency_id'] ?? '';
        $paidBy = $data['paid_by'] ?? '';
        $coPayerID = $data['co_payer_id'] ?? '';
        $paymentTypeID = $data['payment_type_id'] ?? '';
        $doctorID = $data['doctor_id'] ?? '';
        $branchID = $data['branch_id'] ?? '';
        $patientID = $data['patient_id'] ?? '';
        $paginate = $data['paginate'] ?? false;
        $baseCurrency = Currency::find(Setting::where('setting_key', 'currency')->first()->setting_value);
        $operator = '/';
        if ($currencyID) {
            $currency = Currency::find($currencyID);
            if ($currency->xrate > $baseCurrency->xrate) {
                $operator = '/';
            } else {
                $operator = '*';
            }
            $currencyFromBase = false;
        } else {
            $currencyID = $baseCurrency->id;
            $currencyFromBase = true;
        }
        $query = Invoice::with(['currency', 'patient', 'coPayer'])
            ->when(($startDate && $endDate), function (Builder $query) use ($startDate, $endDate) {
                $query->whereBetween('date', [$startDate, $endDate]);
            })
            ->when($doctorID, function (Builder $query) use ($doctorID) {
                $query->where('doctor_id', $doctorID);
            })
            ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                $query->where('invoices.currency_id', $currencyID);
            })
            ->when($patientID, function ($query) use ($patientID) {
                $query->where('invoices.patient_id', $patientID);
            })
            ->when($paymentTypeID, function ($query) use ($paymentTypeID) {
                $query->where('invoices.payment_type_id', $paymentTypeID);
            })
            ->when($coPayerID, function ($query) use ($coPayerID) {
                $query->where('invoices.co_payer_id', $coPayerID);
            })
            ->when($branchID, function ($query) use ($branchID) {
                $query->where('invoices.branch_id', $branchID);
            });
        if (!empty($paginate)) {
            $results = $query->paginate();
        } else {
            $results = $query->get();
        }
        $results->transform(function ($item) use ($currencyID, $currencyFromBase) {
            if ($currencyFromBase) {
                $item->amount = $item->xrate > 0 ? $item->amount * $item->xrate : $item->amount / $item->xrate;
                $item->tax_total = $item->xrate > 0 ? $item->tax_total * $item->xrate : $item->tax_total / $item->xrate;
                $item->shortfall = $item->xrate > 0 ? $item->shortfall * $item->xrate : $item->shortfall / $item->xrate;
                $item->discount_amount = $item->xrate > 0 ? $item->discount_amount * $item->xrate : $item->discount_amount / $item->xrate;
                $item->cash_amount = $item->xrate > 0 ? $item->cash_amount * $item->xrate : $item->cash_amount / $item->xrate;
                $item->co_payer_amount = $item->xrate > 0 ? $item->co_payer_amount * $item->xrate : $item->co_payer_amount / $item->xrate;
                $item->balance = $item->xrate > 0 ? $item->balance * $item->xrate : $item->balance / $item->xrate;
                $item->cash_balance = $item->xrate > 0 ? $item->cash_balance * $item->xrate : $item->cash_balance / $item->xrate;
                $item->co_payer_balance = $item->xrate > 0 ? $item->co_payer_balance * $item->xrate : $item->co_payer_balance / $item->xrate;
            }
            return $item;
        });
        return $results;
    }

    public function getPaymentsByReferringPractitionerReport($data = [])
    {
        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? '';
        $startTime = $data['start_time'] ?? '';
        $endTime = $data['end_time'] ?? '';
        $currencyID = $data['currency_id'] ?? '';
        $paginate = $data['paginate'] ?? false;
        $referringPractitionerID = $data['referring_practitioner_id'] ?? '';
        $createdByType = $data['created_by_type'] ?? '';
        $patientID = $data['patient_id'] ?? '';
        $doctorID = $data['doctor_id'] ?? '';
        $coPayerID = $data['co_payer_id'] ?? '';
        $status = $data['status'] ?? '';
        $branchID = $data['branch_id'] ?? '';
        $baseCurrency = Currency::find(Setting::where('setting_key', 'currency')->first()->setting_value);
        $operator = '/';
        if ($currencyID) {
            $currency = Currency::find($currencyID);
            if ($currency->xrate > $baseCurrency->xrate) {
                $operator = '/';
            } else {
                $operator = '*';
            }
            $currencyFromBase = false;
        } else {
            $currencyID = $baseCurrency->id;
            $currencyFromBase = true;
        }
        $query = ReferringPractitioner::selectSub(function ($query) use ($doctorID, $patientID, $currencyID, $currencyFromBase, $branchID, $coPayerID, $startDate, $endDate) {
            $query->selectRaw('coalesce(sum(if(currency_id=' . $currencyID . ',coalesce(amount,0),if(xrate<1,coalesce(amount*xrate,0),coalesce(amount/xrate,0)))),0)')
                ->from('invoices')
                ->join('patients', 'invoices.patient_id', 'patients.id')
                ->whereColumn('patients.referring_practitioner_id', 'referring_practitioners.id')
                ->when($patientID, function ($query) use ($patientID) {
                    $query->where('invoices.patient_id', $patientID);
                })
                ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                    $query->where('invoices.currency_id', $currencyID);
                })
                ->when($branchID, function ($query) use ($branchID) {
                    $query->where('invoices.branch_id', $branchID);
                })
                ->when($coPayerID, function ($query) use ($coPayerID) {
                    $query->where('invoices.co_payer_id', $coPayerID);
                })
                ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('invoices.date', [$startDate, $endDate]);
                })
                ->groupBy('patients.referring_practitioner_id')
                ->limit(1);
        }, 'amount')
            ->selectSub(function ($query) use ($doctorID, $patientID, $currencyID, $currencyFromBase, $branchID, $coPayerID, $startDate, $endDate) {
                $query->selectRaw('coalesce(sum(if(invoice_payments.currency_id=' . $currencyID . ',coalesce(invoice_payments.amount,0),if(invoice_payments.xrate<1,coalesce(invoice_payments.amount*invoice_payments.xrate,0),coalesce(invoice_payments.amount/invoice_payments.xrate,0)))),0)')
                    ->from('invoice_payments')
                    ->join('invoices', 'invoices.id', 'invoice_payments.invoice_id')
                    ->join('patients', 'invoices.patient_id', 'patients.id')
                    ->whereColumn('patients.referring_practitioner_id', 'referring_practitioners.id')
                    ->when($patientID, function ($query) use ($patientID) {
                        $query->where('invoices.patient_id', $patientID);
                    })
                    ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                        $query->where('invoices.currency_id', $currencyID);
                    })
                    ->when($branchID, function ($query) use ($branchID) {
                        $query->where('invoices.branch_id', $branchID);
                    })
                    ->when($coPayerID, function ($query) use ($coPayerID) {
                        $query->where('invoices.co_payer_id', $coPayerID);
                    })
                    ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                        $query->whereBetween('invoices.date', [$startDate, $endDate]);
                    })
                    ->groupBy('patients.referring_practitioner_id')
                    ->limit(1);
            }, 'payments')
            ->selectSub(function ($query) use ($doctorID, $patientID, $currencyID, $currencyFromBase, $branchID, $coPayerID, $startDate, $endDate) {
                $query->from('consultations')
                    ->join('invoices', 'invoices.id', 'consultations.invoice_id')
                    ->join('patients', 'invoices.patient_id', 'patients.id')
                    ->whereColumn('patients.referring_practitioner_id', 'referring_practitioners.id')
                    ->when($patientID, function ($query) use ($patientID) {
                        $query->where('consultations.patient_id', $patientID);
                    })
                    ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                        $query->where('invoices.currency_id', $currencyID);
                    })
                    ->when($branchID, function ($query) use ($branchID) {
                        $query->where('invoices.branch_id', $branchID);
                    })
                    ->when($coPayerID, function ($query) use ($coPayerID) {
                        $query->where('invoices.co_payer_id', $coPayerID);
                    })
                    ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                        $query->whereBetween('consultations.date', [$startDate, $endDate]);
                    })
                    ->groupBy('patients.referring_practitioner_id')
                    ->selectRaw('coalesce(count(*),0)');
            }, 'total_consultations')
            ->when($referringPractitionerID, function ($query) use ($referringPractitionerID) {
                $query->where('referring_practitioners.id', $referringPractitionerID);
            })
            ->selectRaw('referring_practitioners.*');
        if (!empty($paginate)) {
            $results = $query->paginate();

        } else {

            $results = $query->get();
        }
        return $results;
    }

    public function getSummaryEncounterLineActivitiesReport($data = [])
    {
        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? '';
        $startTime = $data['start_time'] ?? '';
        $endTime = $data['end_time'] ?? '';
        $currencyID = $data['currency_id'] ?? '';
        $paginate = $data['paginate'] ?? false;
        $referringPractitionerID = $data['referring_practitioner_id'] ?? '';
        $createdByType = $data['created_by_type'] ?? '';
        $patientID = $data['patient_id'] ?? '';
        $doctorID = $data['doctor_id'] ?? '';
        $coPayerID = $data['co_payer_id'] ?? '';
        $status = $data['status'] ?? '';
        $branchID = $data['branch_id'] ?? '';
        $baseCurrency = Currency::find(Setting::where('setting_key', 'currency')->first()->setting_value);
        $operator = '/';
        if ($currencyID) {
            $currency = Currency::find($currencyID);
            if ($currency->xrate > $baseCurrency->xrate) {
                $operator = '/';
            } else {
                $operator = '*';
            }
            $currencyFromBase = false;
        } else {
            $currencyID = $baseCurrency->id;
            $currencyFromBase = true;
        }
        $query = InvoiceItem::with(['invoice', 'invoice.patient'])
            ->leftJoin('invoices', 'invoices.id', 'invoice_items.invoice_id')
            ->when(($startDate && $endDate), function (Builder $query) use ($startDate, $endDate) {
                $query->whereBetween('invoices.date', [$startDate, $endDate]);
            })
            ->whereHas('invoice.consultation')
            ->when($doctorID, function (Builder $query) use ($doctorID) {
                $query->where('invoices.doctor_id', $doctorID);
            })
            ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                $query->where('invoices.currency_id', $currencyID);
            })
            ->when($patientID, function ($query) use ($patientID) {
                $query->where('invoices.patient_id', $patientID);
            })
            ->when($branchID, function ($query) use ($branchID) {
                $query->where('invoices.branch_id', $branchID);
            })
            ->selectRaw('invoice_items.*');
        if (!empty($paginate)) {
            $results = $query->paginate();
        } else {
            $results = $query->get();
        }
        $results->transform(function ($item) use ($currencyID, $currencyFromBase) {
            if ($currencyFromBase) {
                $item->unit_cost = $item->xrate > 0 ? $item->unit_cost * $item->xrate : $item->unit_cost / $item->xrate;
                $item->tax_total = $item->xrate > 0 ? $item->tax_total * $item->xrate : $item->tax_total / $item->xrate;
                $item->shortfall = $item->xrate > 0 ? $item->shortfall * $item->xrate : $item->shortfall / $item->xrate;
                $item->award = $item->xrate > 0 ? $item->award * $item->xrate : $item->award / $item->xrate;
                $item->cash_amount = $item->xrate > 0 ? $item->cash_amount * $item->xrate : $item->cash_amount / $item->xrate;
                $item->co_payer_amount = $item->xrate > 0 ? $item->co_payer_amount * $item->xrate : $item->co_payer_amount / $item->xrate;
                $item->total = $item->xrate > 0 ? $item->total * $item->xrate : $item->total / $item->xrate;
            }
            return $item;
        });
        return $results;
    }

    public function getPatientStatementReport($data = [])
    {
        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? '';
        $startTime = $data['start_time'] ?? '';
        $endTime = $data['end_time'] ?? '';
        $currencyID = $data['currency_id'] ?? '';
        $paginate = $data['paginate'] ?? false;
        $includeItems = $data['include_items'] ?? false;
        $referringPractitionerID = $data['referring_practitioner_id'] ?? '';
        $createdByType = $data['created_by_type'] ?? '';
        $patientID = $data['patient_id'] ?? '';
        $doctorID = $data['doctor_id'] ?? '';
        $coPayerID = $data['co_payer_id'] ?? '';
        $status = $data['status'] ?? '';
        $branchID = $data['branch_id'] ?? '';
        $baseCurrency = Currency::find(Setting::where('setting_key', 'currency')->first()->setting_value);
        $operator = '/';
        if ($currencyID) {
            $currency = Currency::find($currencyID);
            if ($currency->xrate > $baseCurrency->xrate) {
                $operator = '/';
            } else {
                $operator = '*';
            }
            $currencyFromBase = false;
        } else {
            $currencyID = $baseCurrency->id;
            $currencyFromBase = true;
        }
        $patient = Client::find($patientID);
        //get current balance
        $balance = Invoice::where('balance', '>', 0)
            ->when($doctorID, function ($query) use ($doctorID) {
                $query->where('invoices.doctor_id', $doctorID);
            })
            ->when($endDate, function ($query) use ($endDate) {
                $query->where('invoices.date', '<=', $endDate);
            })
            ->when($patientID, function ($query) use ($patientID) {
                $query->where('invoices.patient_id', $patientID);
            })
            ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                $query->where('invoices.currency_id', $currencyID);
            })
            ->when($branchID, function ($query) use ($branchID) {
                $query->where('invoices.branch_id', $branchID);
            })
            ->when($coPayerID, function ($query) use ($coPayerID) {
                $query->where('invoices.co_payer_id', $coPayerID);
            })
            ->selectRaw('coalesce(sum(if(currency_id=' . $currencyID . ',coalesce(amount,0),if(xrate<1,coalesce(amount*xrate,0),coalesce(amount/xrate,0)))),0) as amount,coalesce(sum(if(currency_id=' . $currencyID . ',coalesce(balance,0),if(xrate<1,coalesce(balance*xrate,0),coalesce(balance/xrate,0)))),0) as balance,coalesce(sum(if(currency_id=' . $currencyID . ',coalesce(cash_amount,0),if(xrate<1,coalesce(cash_amount*xrate,0),coalesce(cash_amount/xrate,0)))),0) as cash_amount,coalesce(sum(if(currency_id=' . $currencyID . ',coalesce(co_payer_amount,0),if(xrate<1,coalesce(co_payer_amount*xrate,0),coalesce(co_payer_amount/xrate,0)))),0) as co_payer_amount,coalesce(sum(if(currency_id=' . $currencyID . ',coalesce(cash_balance,0),if(xrate<1,coalesce(cash_balance*xrate,0),coalesce(cash_balance/xrate,0)))),0) as cash_balance,coalesce(sum(if(currency_id=' . $currencyID . ',coalesce(co_payer_balance,0),if(xrate<1,coalesce(co_payer_balance*xrate,0),coalesce(co_payer_balance/xrate,0)))),0) as co_payer_balance')
            ->first();
        $patient->balance = $balance;
        if ($includeItems) {
            $items = Invoice::with(['invoiceItems', 'doctor'])
                ->when(($startDate && $endDate), function (Builder $query) use ($startDate, $endDate) {
                    $query->whereBetween('invoices.date', [$startDate, $endDate]);
                })
                ->when($doctorID, function (Builder $query) use ($doctorID) {
                    $query->where('invoices.doctor_id', $doctorID);
                })
                ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                    $query->where('invoices.currency_id', $currencyID);
                })
                ->when($patientID, function ($query) use ($patientID) {
                    $query->where('invoices.patient_id', $patientID);
                })
                ->when($branchID, function ($query) use ($branchID) {
                    $query->where('invoices.branch_id', $branchID);
                })->get()->transform(function ($item) use ($currencyID, $currencyFromBase) {
                    if ($currencyFromBase) {
                        $item->discount_amount = $item->xrate > 0 ? $item->discount_amount * $item->xrate : $item->discount_amount / $item->xrate;
                        $item->tax_total = $item->xrate > 0 ? $item->tax_total * $item->xrate : $item->tax_total / $item->xrate;
                        $item->balance = $item->xrate > 0 ? $item->balance * $item->xrate : $item->balance / $item->xrate;
                        $item->cash_balance = $item->xrate > 0 ? $item->cash_balance * $item->xrate : $item->cash_balance / $item->xrate;
                        $item->cash_amount = $item->xrate > 0 ? $item->cash_amount * $item->xrate : $item->cash_amount / $item->xrate;
                        $item->co_payer_amount = $item->xrate > 0 ? $item->co_payer_amount * $item->xrate : $item->co_payer_amount / $item->xrate;
                        $item->co_payer_balance = $item->xrate > 0 ? $item->co_payer_balance * $item->xrate : $item->co_payer_balance / $item->xrate;
                    }
                    $item->invoiceItems->transform(function ($itm) use ($currencyID, $currencyFromBase) {
                        if ($currencyFromBase) {
                            $itm->unit_cost = $itm->xrate > 0 ? $itm->unit_cost * $itm->xrate : $itm->unit_cost / $itm->xrate;
                            $itm->tax_total = $itm->xrate > 0 ? $itm->tax_total * $itm->xrate : $itm->tax_total / $itm->xrate;
                            $itm->shortfall = $itm->xrate > 0 ? $itm->shortfall * $itm->xrate : $itm->shortfall / $itm->xrate;
                            $itm->award = $itm->xrate > 0 ? $itm->award * $itm->xrate : $itm->award / $itm->xrate;
                            $itm->cash_amount = $itm->xrate > 0 ? $itm->cash_amount * $itm->xrate : $itm->cash_amount / $itm->xrate;
                            $itm->co_payer_amount = $itm->xrate > 0 ? $itm->co_payer_amount * $itm->xrate : $itm->co_payer_amount / $itm->xrate;
                            $itm->total = $itm->xrate > 0 ? $itm->total * $itm->xrate : $itm->total / $itm->xrate;
                        }
                        return $itm;
                    });
                    return $item;
                });

            $patient->records = $items;
        }
        return $patient;
    }

    public function getPatientStatementSummaryReport($data = [])
    {
        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? '';
        $startTime = $data['start_time'] ?? '';
        $endTime = $data['end_time'] ?? '';
        $currencyID = $data['currency_id'] ?? '';
        $paginate = $data['paginate'] ?? false;
        $includeItems = $data['include_items'] ?? false;
        $referringPractitionerID = $data['referring_practitioner_id'] ?? '';
        $createdByType = $data['created_by_type'] ?? '';
        $patientID = $data['patient_id'] ?? '';
        $doctorID = $data['doctor_id'] ?? '';
        $coPayerID = $data['co_payer_id'] ?? '';
        $status = $data['status'] ?? '';
        $branchID = $data['branch_id'] ?? '';
        $baseCurrency = Currency::find(Setting::where('setting_key', 'currency')->first()->setting_value);
        $operator = '/';
        if ($currencyID) {
            $currency = Currency::find($currencyID);
            if ($currency->xrate > $baseCurrency->xrate) {
                $operator = '/';
            } else {
                $operator = '*';
            }
            $currencyFromBase = false;
        } else {
            $currencyID = $baseCurrency->id;
            $currencyFromBase = true;
        }
        $query = Client::leftJoin('invoices', 'invoices.patient_id', 'patients.id')
            ->when($doctorID, function ($query) use ($doctorID) {
                $query->where('invoices.doctor_id', $doctorID);
            })
            ->when(($startDate && $endDate), function (Builder $query) use ($startDate, $endDate) {
                $query->whereBetween('invoices.date', [$startDate, $endDate]);
            })
            ->when($patientID, function ($query) use ($patientID) {
                $query->where('patients.id', $patientID);
            })
            ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                $query->where('invoices.currency_id', $currencyID);
            })
            ->when($branchID, function ($query) use ($branchID) {
                $query->where('invoices.branch_id', $branchID);
            })
            ->when($coPayerID, function ($query) use ($coPayerID) {
                $query->where('invoices.co_payer_id', $coPayerID);
            })
            ->groupBy('patients.id')
            ->having('balance', '>', 0)
            ->selectRaw('patients.*,coalesce(sum(if(currency_id=' . $currencyID . ',coalesce(amount,0),if(xrate<1,coalesce(amount*xrate,0),coalesce(amount/xrate,0)))),0) as amount,coalesce(sum(if(currency_id=' . $currencyID . ',coalesce(balance,0),if(xrate<1,coalesce(balance*xrate,0),coalesce(balance/xrate,0)))),0) as balance,coalesce(sum(if(currency_id=' . $currencyID . ',coalesce(cash_amount,0),if(xrate<1,coalesce(cash_amount*xrate,0),coalesce(cash_amount/xrate,0)))),0) as cash_amount,coalesce(sum(if(currency_id=' . $currencyID . ',coalesce(co_payer_amount,0),if(xrate<1,coalesce(co_payer_amount*xrate,0),coalesce(co_payer_amount/xrate,0)))),0) as co_payer_amount,coalesce(sum(if(currency_id=' . $currencyID . ',coalesce(cash_balance,0),if(xrate<1,coalesce(cash_balance*xrate,0),coalesce(cash_balance/xrate,0)))),0) as cash_balance,coalesce(sum(if(currency_id=' . $currencyID . ',coalesce(co_payer_balance,0),if(xrate<1,coalesce(co_payer_balance*xrate,0),coalesce(co_payer_balance/xrate,0)))),0) as co_payer_balance');
        if (!empty($paginate)) {
            $results = $query->paginate();
        } else {
            $results = $query->get();
        }

        return $results;
    }

    public function getPatientDetailsReport($data = [])
    {
        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? '';
        $dobFrom = $data['dob_from'] ?? '';
        $dobTo = $data['dob_to'] ?? '';
        $currencyID = $data['currency_id'] ?? '';
        $paginate = $data['paginate'] ?? false;
        $gender = $data['gender'] ?? '';
        $referringPractitionerID = $data['referring_practitioner_id'] ?? '';
        $sponsor = $data['sponsor'] ?? '';
        $patientID = $data['patient_id'] ?? '';
        $doctorID = $data['doctor_id'] ?? '';
        $coPayerID = $data['co_payer_id'] ?? '';
        $status = $data['status'] ?? '';
        $branchID = $data['branch_id'] ?? '';
        $baseCurrency = Currency::find(Setting::where('setting_key', 'currency')->first()->setting_value);
        $operator = '/';
        if ($currencyID) {
            $currency = Currency::find($currencyID);
            if ($currency->xrate > $baseCurrency->xrate) {
                $operator = '/';
            } else {
                $operator = '*';
            }
            $currencyFromBase = false;
        } else {
            $currencyID = $baseCurrency->id;
            $currencyFromBase = true;
        }
        $query = Client::with(['doctor', 'referringPractitioner', 'defaultCoPayer', 'defaultCoPayer.coPayer'])
            ->when($doctorID, function ($query) use ($doctorID) {
                $query->where('patients.doctor_id', $doctorID);
            })
            ->when(($startDate && $endDate), function (Builder $query) use ($startDate, $endDate) {
                $query->whereBetween('patients.created_at', [$startDate, $endDate]);
            })
            ->when(($dobFrom && $dobTo), function (Builder $query) use ($dobFrom, $dobTo) {
                $query->whereBetween('patients.dob', [$dobFrom, $dobTo]);
            })
            ->when($gender, function ($query) use ($gender) {
                $query->where('patients.gender', $gender);
            })
            ->when($patientID, function ($query) use ($patientID) {
                $query->where('patients.id', $patientID);
            })
            ->when($branchID, function ($query) use ($branchID) {
                $query->where('patients.branch_id', $branchID);
            })
            ->when($status, function ($query) use ($status) {
                $query->where('patients.status', $status);
            })
            ->when($sponsor, function ($query) use ($sponsor) {
                $query->where('patients.sponsor', $sponsor);
            })
            ->when($referringPractitionerID, function ($query) use ($referringPractitionerID) {
                $query->where('patients.referring_practitioner_id', $referringPractitionerID);
            })
            ->when($coPayerID, function ($query) use ($coPayerID) {
                $query->whereHas('coPayers', function (Builder $query) use ($coPayerID) {
                    $query->where('co_payer_id', $coPayerID);
                });
            })
            ->selectRaw('patients.*');
        if (!empty($paginate)) {
            $results = $query->paginate();
        } else {
            $results = $query->get();
        }

        return $results;
    }

    public function getPatientChargesPaymentHistoryReport($data = [])
    {
        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? '';
        $startTime = $data['start_time'] ?? '';
        $endTime = $data['end_time'] ?? '';
        $currencyID = $data['currency_id'] ?? '';
        $paginate = $data['paginate'] ?? false;
        $includeItems = $data['include_items'] ?? false;
        $includePayments = $data['include_payments'] ?? false;
        $referringPractitionerID = $data['referring_practitioner_id'] ?? '';
        $createdByType = $data['created_by_type'] ?? '';
        $patientID = $data['patient_id'] ?? '';
        $doctorID = $data['doctor_id'] ?? '';
        $coPayerID = $data['co_payer_id'] ?? '';
        $status = $data['status'] ?? '';
        $branchID = $data['branch_id'] ?? '';
        $baseCurrency = Currency::find(Setting::where('setting_key', 'currency')->first()->setting_value);
        $operator = '/';
        if ($currencyID) {
            $currency = Currency::find($currencyID);
            if ($currency->xrate > $baseCurrency->xrate) {
                $operator = '/';
            } else {
                $operator = '*';
            }
            $currencyFromBase = false;
        } else {
            $currencyID = $baseCurrency->id;
            $currencyFromBase = true;
        }
        $patient = Client::find($patientID);
        //get current balance
        $balance = Invoice::where('balance', '>', 0)
            ->when($doctorID, function ($query) use ($doctorID) {
                $query->where('invoices.doctor_id', $doctorID);
            })
            ->when($endDate, function ($query) use ($endDate) {
                $query->where('invoices.date', '<=', $endDate);
            })
            ->when($patientID, function ($query) use ($patientID) {
                $query->where('invoices.patient_id', $patientID);
            })
            ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                $query->where('invoices.currency_id', $currencyID);
            })
            ->when($branchID, function ($query) use ($branchID) {
                $query->where('invoices.branch_id', $branchID);
            })
            ->when($coPayerID, function ($query) use ($coPayerID) {
                $query->where('invoices.co_payer_id', $coPayerID);
            })
            ->selectRaw('coalesce(sum(if(currency_id=' . $currencyID . ',coalesce(amount,0),if(xrate<1,coalesce(amount*xrate,0),coalesce(amount/xrate,0)))),0) as amount,coalesce(sum(if(currency_id=' . $currencyID . ',coalesce(balance,0),if(xrate<1,coalesce(balance*xrate,0),coalesce(balance/xrate,0)))),0) as balance,coalesce(sum(if(currency_id=' . $currencyID . ',coalesce(cash_amount,0),if(xrate<1,coalesce(cash_amount*xrate,0),coalesce(cash_amount/xrate,0)))),0) as cash_amount,coalesce(sum(if(currency_id=' . $currencyID . ',coalesce(co_payer_amount,0),if(xrate<1,coalesce(co_payer_amount*xrate,0),coalesce(co_payer_amount/xrate,0)))),0) as co_payer_amount,coalesce(sum(if(currency_id=' . $currencyID . ',coalesce(cash_balance,0),if(xrate<1,coalesce(cash_balance*xrate,0),coalesce(cash_balance/xrate,0)))),0) as cash_balance,coalesce(sum(if(currency_id=' . $currencyID . ',coalesce(co_payer_balance,0),if(xrate<1,coalesce(co_payer_balance*xrate,0),coalesce(co_payer_balance/xrate,0)))),0) as co_payer_balance')
            ->first();
        $patient->balance = $balance;
        if ($includeItems) {
            $items = Invoice::with(['invoiceItems', 'doctor'])
                ->when(($startDate && $endDate), function (Builder $query) use ($startDate, $endDate) {
                    $query->whereBetween('invoices.date', [$startDate, $endDate]);
                })
                ->when($doctorID, function (Builder $query) use ($doctorID) {
                    $query->where('invoices.doctor_id', $doctorID);
                })
                ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                    $query->where('invoices.currency_id', $currencyID);
                })
                ->when($patientID, function ($query) use ($patientID) {
                    $query->where('invoices.patient_id', $patientID);
                })
                ->when($branchID, function ($query) use ($branchID) {
                    $query->where('invoices.branch_id', $branchID);
                })->get()->transform(function ($item) use ($currencyID, $currencyFromBase) {
                    if ($currencyFromBase) {
                        $item->discount_amount = $item->xrate > 0 ? $item->discount_amount * $item->xrate : $item->discount_amount / $item->xrate;
                        $item->tax_total = $item->xrate > 0 ? $item->tax_total * $item->xrate : $item->tax_total / $item->xrate;
                        $item->balance = $item->xrate > 0 ? $item->balance * $item->xrate : $item->balance / $item->xrate;
                        $item->cash_balance = $item->xrate > 0 ? $item->cash_balance * $item->xrate : $item->cash_balance / $item->xrate;
                        $item->cash_amount = $item->xrate > 0 ? $item->cash_amount * $item->xrate : $item->cash_amount / $item->xrate;
                        $item->co_payer_amount = $item->xrate > 0 ? $item->co_payer_amount * $item->xrate : $item->co_payer_amount / $item->xrate;
                        $item->co_payer_balance = $item->xrate > 0 ? $item->co_payer_balance * $item->xrate : $item->co_payer_balance / $item->xrate;
                    }
                    $item->invoiceItems->transform(function ($itm) use ($currencyID, $currencyFromBase) {
                        if ($currencyFromBase) {
                            $itm->unit_cost = $itm->xrate > 0 ? $itm->unit_cost * $itm->xrate : $itm->unit_cost / $itm->xrate;
                            $itm->tax_total = $itm->xrate > 0 ? $itm->tax_total * $itm->xrate : $itm->tax_total / $itm->xrate;
                            $itm->shortfall = $itm->xrate > 0 ? $itm->shortfall * $itm->xrate : $itm->shortfall / $itm->xrate;
                            $itm->award = $itm->xrate > 0 ? $itm->award * $itm->xrate : $itm->award / $itm->xrate;
                            $itm->cash_amount = $itm->xrate > 0 ? $itm->cash_amount * $itm->xrate : $itm->cash_amount / $itm->xrate;
                            $itm->co_payer_amount = $itm->xrate > 0 ? $itm->co_payer_amount * $itm->xrate : $itm->co_payer_amount / $itm->xrate;
                            $itm->total = $itm->xrate > 0 ? $itm->total * $itm->xrate : $itm->total / $itm->xrate;
                        }
                        return $itm;
                    });
                    return $item;
                });

            $patient->records = $items;
        }
        if ($includePayments) {
            $payments = InvoicePayment::with(['coPayer', 'paymentType', 'paymentDetail', 'currency'])
                ->when(($startDate && $endDate), function (Builder $query) use ($startDate, $endDate) {
                    $query->whereBetween('invoice_payments.date', [$startDate, $endDate]);
                })
                ->when($doctorID, function (Builder $query) use ($doctorID) {
                    $query->whereHas('invoice', function (Builder $query) use ($doctorID) {
                        $query->where('doctor_id', $doctorID);
                    });
                })
                ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                    $query->where('invoice_payments.currency_id', $currencyID);
                })
                ->when($patientID, function ($query) use ($patientID) {
                    $query->where('invoice_payments.patient_id', $patientID);
                })
                ->when($branchID, function ($query) use ($branchID) {
                    $query->where('invoice_payments.branch_id', $branchID);
                })->get()->transform(function ($item) use ($currencyID, $currencyFromBase) {
                    if ($currencyFromBase) {
                        $item->amount = $item->xrate > 0 ? $item->amount * $item->xrate : $item->amount / $item->xrate;
                    }
                    return $item;
                });

            $patient->payments = $payments;
        }
        return $patient;
    }

    public function getPatientVisitSummaryReport($data = [])
    {
        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? '';
        $startTime = $data['start_time'] ?? '';
        $endTime = $data['end_time'] ?? '';
        $currencyID = $data['currency_id'] ?? '';
        $paginate = $data['paginate'] ?? false;
        $includeItems = $data['include_items'] ?? false;
        $includePayments = $data['include_payments'] ?? false;
        $referringPractitionerID = $data['referring_practitioner_id'] ?? '';
        $createdByType = $data['created_by_type'] ?? '';
        $patientID = $data['patient_id'] ?? '';
        $doctorID = $data['doctor_id'] ?? '';
        $nurseID = $data['nurse_id'] ?? '';
        $coPayerID = $data['co_payer_id'] ?? '';
        $status = $data['status'] ?? '';
        $branchID = $data['branch_id'] ?? '';
        $baseCurrency = Currency::find(Setting::where('setting_key', 'currency')->first()->setting_value);
        $operator = '/';
        if ($currencyID) {
            $currency = Currency::find($currencyID);
            if ($currency->xrate > $baseCurrency->xrate) {
                $operator = '/';
            } else {
                $operator = '*';
            }
            $currencyFromBase = false;
        } else {
            $currencyID = $baseCurrency->id;
            $currencyFromBase = true;
        }
        return Client::with(['defaultCoPayer', 'defaultCoPayer.coPayer', 'doctor', 'country', 'consultations', 'consultations.doctor', 'consultations.nurse', 'consultations.prescriptions', 'consultations.diagnosis', 'consultations.vitals', 'consultations.procedures', 'consultations.procedures.tariff'])
            ->whereHas('consultations', function ($query) use ($startDate, $endDate, $doctorID, $branchID, $nurseID) {
                $query->when(($startDate && $endDate), function (Builder $query) use ($startDate, $endDate) {
                    $query->whereBetween('consultations.date', [$startDate, $endDate]);
                })
                    ->when($doctorID, function ($query) use ($doctorID) {
                        $query->where('consultations.doctor_id', $doctorID);
                    })
                    ->when($nurseID, function ($query) use ($nurseID) {
                        $query->where('consultations.nurse_id', $nurseID);
                    })
                    ->when($branchID, function ($query) use ($branchID) {
                        $query->where('consultations.branch_id', $branchID);
                    });
            })
            ->where('id', $patientID)
            ->first();
    }

    public function getAuditTrailReport($data = []): Collection|array|LengthAwarePaginator
    {
        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? '';
        $roleID = $data['role_id'] ?? '';
        $doctorID = $data['doctor_id'] ?? '';
        $userID = $data['user_id'] ?? '';
        $status = $data['status'] ?? '';
        $gender = $data['gender'] ?? '';
        $search = $data['search'] ?? '';
        $orderBy = $data['order_by'] ?? 'created_at';
        $paginate = $data['paginate'] ?? false;
        $query = Activity::with(['causer'])
            ->when(($startDate && $endDate), function (Builder $query) use ($startDate, $endDate) {
                $query->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->when($userID, function (Builder $query) use ($userID) {
                $query->where('causer_id', $userID);
            })
            ->when($search, function ($query) use ($search) {
                $query->where('users.first_name', 'like', '%' . $search . '%');
                $query->orWhere('users.id', 'like', '%' . $search . '%');
                $query->orWhere('users.practice_number', 'like', '%' . $search . '%');
                $query->orWhere('users.description', 'like', '%' . $search . '%');
            })
            ->when($roleID, function ($query) use ($roleID) {
                $query->whereHas('roles', function ($query) use ($roleID) {
                    $query->where('id', $roleID);
                });
            })
            ->orderBy($orderBy, 'desc');
        if (!empty($paginate)) {
            $results = $query->paginate();
        } else {
            $results = $query->get();
        }
        return $results;
    }

    public function getActiveUsersReport($data = []): Collection|array|LengthAwarePaginator
    {
        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? '';
        $roleID = $data['role_id'] ?? '';
        $doctorID = $data['doctor_id'] ?? '';
        $coPayerID = $data['co_payer_id'] ?? '';
        $status = $data['status'] ?? '';
        $gender = $data['gender'] ?? '';
        $search = $data['search'] ?? '';
        $orderBy = $data['order_by'] ?? 'last_login';
        $paginate = $data['paginate'] ?? false;
        $query = User::with(['roles'])
            ->whereNotNull('last_login')
            ->when(($startDate && $endDate), function (Builder $query) use ($startDate, $endDate) {
                $query->whereBetween('users.created_at', [$startDate, $endDate]);
            })
            ->when($gender, function (Builder $query) use ($gender) {
                $query->where('users.gender', $gender);
            })
            ->when($search, function ($query) use ($search) {
                $query->where('users.first_name', 'like', '%' . $search . '%');
                $query->orWhere('users.id', 'like', '%' . $search . '%');
                $query->orWhere('users.practice_number', 'like', '%' . $search . '%');
                $query->orWhere('users.description', 'like', '%' . $search . '%');
            })
            ->when($roleID, function ($query) use ($roleID) {
                $query->whereHas('roles', function ($query) use ($roleID) {
                    $query->where('id', $roleID);
                });
            })
            ->orderBy($orderBy, 'desc');
        if (!empty($paginate)) {
            $results = $query->paginate();
        } else {
            $results = $query->get();
        }
        return $results;
    }

    public function getClaimsDetailReport($data = []): Collection|array|LengthAwarePaginator
    {
        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? '';
        $roleID = $data['role_id'] ?? '';
        $doctorID = $data['doctor_id'] ?? '';
        $currencyID = $data['currency_id'] ?? '';
        $coPayerID = $data['co_payer_id'] ?? '';
        $branchID = $data['branch_id'] ?? '';
        $claimBatchID = $data['claim_batch_id'] ?? '';
        $status = $data['status'] ?? '';
        $patientID = $data['patient_id'] ?? '';
        $search = $data['search'] ?? '';
        $orderBy = $data['order_by'] ?? 'created_at';
        $paginate = $data['paginate'] ?? false;
        $query = Claim::with(['invoice', 'invoice.currency', 'claimBatch', 'patient', 'coPayer', 'consultation'])
            ->when(($startDate && $endDate), function (Builder $query) use ($startDate, $endDate) {
                $query->whereBetween('claims.created_at', [$startDate, $endDate]);
            })
            ->when($coPayerID, function (Builder $query) use ($coPayerID) {
                $query->where('claims.co_payer_id', $coPayerID);
            })
            ->when($patientID, function (Builder $query) use ($patientID) {
                $query->where('claims.patient_id', $patientID);
            })
            ->when($status, function (Builder $query) use ($status) {
                $query->where('claims.status', $status);
            })
            ->when($claimBatchID, function (Builder $query) use ($claimBatchID) {
                $query->where('claims.claim_batch_id', $claimBatchID);
            })
            ->when($branchID, function (Builder $query) use ($branchID) {
                $query->where('claims.branch_id', $branchID);
            })
            ->when($search, function ($query) use ($search) {
                $query->where('claims.id', 'like', '%' . $search . '%');
            })
            ->when($currencyID, function ($query) use ($currencyID) {
                $query->whereHas('invoice', function ($query) use ($currencyID) {
                    $query->where('currency_id', $currencyID);
                });
            })
            ->orderBy($orderBy, 'desc');
        if (!empty($paginate)) {
            $results = $query->paginate();
        } else {
            $results = $query->get();
        }
        return $results;
    }

    public function getShortfallsReport($data = []): Collection|array|LengthAwarePaginator
    {
        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? '';
        $roleID = $data['role_id'] ?? '';
        $doctorID = $data['doctor_id'] ?? '';
        $currencyID = $data['currency_id'] ?? '';
        $coPayerID = $data['co_payer_id'] ?? '';
        $branchID = $data['branch_id'] ?? '';
        $claimBatchID = $data['claim_batch_id'] ?? '';
        $status = $data['status'] ?? '';
        $patientID = $data['patient_id'] ?? '';
        $search = $data['search'] ?? '';
        $orderBy = $data['order_by'] ?? 'created_at';
        $paginate = $data['paginate'] ?? false;
        $query = Claim::with(['invoice', 'invoice.currency', 'claimBatch', 'patient', 'coPayer', 'consultation'])
            ->where('shortfall', '>', 0)
            ->when(($startDate && $endDate), function (Builder $query) use ($startDate, $endDate) {
                $query->whereBetween('claims.created_at', [$startDate, $endDate]);
            })
            ->when($coPayerID, function (Builder $query) use ($coPayerID) {
                $query->where('claims.co_payer_id', $coPayerID);
            })
            ->when($patientID, function (Builder $query) use ($patientID) {
                $query->where('claims.patient_id', $patientID);
            })
            ->when($claimBatchID, function (Builder $query) use ($claimBatchID) {
                $query->where('claims.claim_batch_id', $claimBatchID);
            })
            ->when($branchID, function (Builder $query) use ($branchID) {
                $query->where('claims.branch_id', $branchID);
            })
            ->when($search, function ($query) use ($search) {
                $query->where('claims.id', 'like', '%' . $search . '%');
            })
            ->when($currencyID, function ($query) use ($currencyID) {
                $query->whereHas('invoice', function ($query) use ($currencyID) {
                    $query->where('currency_id', $currencyID);
                });
            })
            ->orderBy($orderBy, 'desc');
        if (!empty($paginate)) {
            $results = $query->paginate();
        } else {
            $results = $query->get();
        }
        return $results;
    }

    public function getClaimsLineLevelReport($data = []): Collection|array|LengthAwarePaginator
    {
        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? '';
        $roleID = $data['role_id'] ?? '';
        $doctorID = $data['doctor_id'] ?? '';
        $currencyID = $data['currency_id'] ?? '';
        $coPayerID = $data['co_payer_id'] ?? '';
        $branchID = $data['branch_id'] ?? '';
        $claimBatchID = $data['claim_batch_id'] ?? '';
        $status = $data['status'] ?? '';
        $patientID = $data['patient_id'] ?? '';
        $search = $data['search'] ?? '';
        $orderBy = $data['order_by'] ?? 'created_at';
        $paginate = $data['paginate'] ?? false;
        $query = InvoiceItem::with(['invoice', 'invoice.currency', 'invoice.claim', 'invoice.claim.claimBatch', 'invoice.patient', 'invoice.coPayer'])
            ->where('co_payer_amount', '>', 0)
            ->whereHas('invoice', function (Builder $query) use ($startDate, $endDate, $coPayerID, $patientID, $status, $currencyID, $branchID) {
                $query->when(($startDate && $endDate), function (Builder $query) use ($startDate, $endDate) {
                    $query->whereBetween('invoices.date', [$startDate, $endDate]);
                })
                    ->when($coPayerID, function (Builder $query) use ($coPayerID) {
                        $query->where('invoices.co_payer_id', $coPayerID);
                    })
                    ->when($patientID, function (Builder $query) use ($patientID) {
                        $query->where('invoices.patient_id', $patientID);
                    })
                    ->when($branchID, function (Builder $query) use ($branchID) {
                        $query->where('invoices.branch_id', $branchID);
                    })
                    ->when($currencyID, function ($query) use ($currencyID) {
                        $query->where('currency_id', $currencyID);
                    });
            })
            ->whereHas('invoice.claim', function (Builder $query) use ($startDate, $endDate, $coPayerID, $patientID, $status, $currencyID, $branchID, $claimBatchID) {
                $query->when($status, function (Builder $query) use ($status) {
                    $query->where('claims.status', $status);
                })
                    ->when($claimBatchID, function (Builder $query) use ($claimBatchID) {
                        $query->where('claims.claim_batch_id', $claimBatchID);
                    });
            })
            ->when($search, function ($query) use ($search) {
                $query->where('invoice_items.id', 'like', '%' . $search . '%');
            })
            ->orderBy($orderBy, 'desc');
        if (!empty($paginate)) {
            $results = $query->paginate();
        } else {
            $results = $query->get();
        }
        return $results;
    }

    public function getChargesPaymentsReconciliationReport($data = []): Collection|array|LengthAwarePaginator
    {
        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? '';
        $roleID = $data['role_id'] ?? '';
        $doctorID = $data['doctor_id'] ?? '';
        $currencyID = $data['currency_id'] ?? '';

        $coPayerID = $data['co_payer_id'] ?? '';
        $branchID = $data['branch_id'] ?? '';
        $claimBatchID = $data['claim_batch_id'] ?? '';
        $status = $data['status'] ?? '';
        $patientID = $data['patient_id'] ?? '';
        $search = $data['search'] ?? '';
        $orderBy = $data['order_by'] ?? 'created_at';
        $paginate = $data['paginate'] ?? false;
        $baseCurrency = Currency::find(Setting::where('setting_key', 'currency')->first()->setting_value);
        if ($currencyID) {
            $currency = Currency::find($currencyID);
            $currencyFromBase = false;
        } else {
            $currencyID = $baseCurrency->id;
            $currencyFromBase = true;
        }
        $query = Invoice::with(['currency', 'doctor', 'patient', 'coPayer'])
            ->when(($startDate && $endDate), function (Builder $query) use ($startDate, $endDate) {
                $query->whereBetween('invoices.date', [$startDate, $endDate]);
            })
            ->when($coPayerID, function (Builder $query) use ($coPayerID) {
                $query->where('invoices.co_payer_id', $coPayerID);
            })
            ->when($doctorID, function (Builder $query) use ($doctorID) {
                $query->where('invoices.doctor_id', $doctorID);
            })
            ->when($patientID, function (Builder $query) use ($patientID) {
                $query->where('invoices.patient_id', $patientID);
            })
            ->when($branchID, function (Builder $query) use ($branchID) {
                $query->where('invoices.branch_id', $branchID);
            })
            ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                $query->where('currency_id', $currencyID);
            })
            ->when($search, function ($query) use ($search) {
                $query->where('invoices.id', 'like', '%' . $search . '%');
            })
            ->orderBy($orderBy, 'desc');
        if (!empty($paginate)) {
            $results = $query->paginate();
        } else {
            $results = $query->get();
        }
        $results->transform(function ($item) use ($currencyID, $currencyFromBase) {
            if ($currencyFromBase) {
                $item->discount_amount = $item->xrate > 0 ? $item->discount_amount * $item->xrate : $item->discount_amount / $item->xrate;
                $item->tax_total = $item->xrate > 0 ? $item->tax_total * $item->xrate : $item->tax_total / $item->xrate;
                $item->balance = $item->xrate > 0 ? $item->balance * $item->xrate : $item->balance / $item->xrate;
                $item->cash_balance = $item->xrate > 0 ? $item->cash_balance * $item->xrate : $item->cash_balance / $item->xrate;
                $item->cash_amount = $item->xrate > 0 ? $item->cash_amount * $item->xrate : $item->cash_amount / $item->xrate;
                $item->co_payer_amount = $item->xrate > 0 ? $item->co_payer_amount * $item->xrate : $item->co_payer_amount / $item->xrate;
                $item->co_payer_balance = $item->xrate > 0 ? $item->co_payer_balance * $item->xrate : $item->co_payer_balance / $item->xrate;
            }
            return $item;
        });
        return $results;
    }

    public function getMonthlyActivityAnalysisReport($data = []): Collection|array|LengthAwarePaginator
    {

        $endDate = Carbon::parse($data['end_date']) ?? Carbon::today();
        $startDate = (clone $endDate)->startOfMonth();
        $roleID = $data['role_id'] ?? '';
        $doctorID = $data['doctor_id'] ?? '';
        $currencyID = $data['currency_id'] ?? '';

        $coPayerID = $data['co_payer_id'] ?? '';
        $branchID = $data['branch_id'] ?? '';
        $claimBatchID = $data['claim_batch_id'] ?? '';
        $status = $data['status'] ?? '';
        $patientID = $data['patient_id'] ?? '';
        $search = $data['search'] ?? '';
        $orderBy = $data['order_by'] ?? 'created_at';
        $paginate = $data['paginate'] ?? false;
        $year = $endDate->format('Y');
        $baseCurrency = Currency::find(Setting::where('setting_key', 'currency')->first()->setting_value);
        if ($currencyID) {
            $currency = Currency::find($currencyID);
            $currencyFromBase = false;
            $currencyFromBaseSQL = 'false';
        } else {
            $currencyID = $baseCurrency->id;
            $currencyFromBase = true;
            $currencyFromBaseSQL = 'true';
        }
        $monthlyCharges = Invoice::with(['currency', 'doctor', 'patient', 'coPayer'])
            ->whereBetween('invoices.date', [$startDate, $endDate])
            ->when($coPayerID, function (Builder $query) use ($coPayerID) {
                $query->where('invoices.co_payer_id', $coPayerID);
            })
            ->when($doctorID, function (Builder $query) use ($doctorID) {
                $query->where('invoices.doctor_id', $doctorID);
            })
            ->when($patientID, function (Builder $query) use ($patientID) {
                $query->where('invoices.patient_id', $patientID);
            })
            ->when($branchID, function (Builder $query) use ($branchID) {
                $query->where('invoices.branch_id', $branchID);
            })
            ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                $query->where('currency_id', $currencyID);
            })
            ->when($search, function ($query) use ($search) {
                $query->where('invoices.id', 'like', '%' . $search . '%');
            })
            ->selectRaw('sum(if(' . $currencyFromBaseSQL . ',coalesce(amount,0),if(xrate<1,coalesce(amount*xrate,0),coalesce(amount/xrate,0)))) as amount,sum(if(' . $currencyFromBaseSQL . ',coalesce(balance,0),if(xrate<1,coalesce(balance*xrate,0),coalesce(balance/xrate,0)))) as balance,sum(if(' . $currencyFromBaseSQL . ',coalesce(cash_amount,0),if(xrate<1,coalesce(cash_amount*xrate,0),coalesce(cash_amount/xrate,0)))) as cash_amount,sum(if(' . $currencyFromBaseSQL . ',coalesce(co_payer_amount,0),if(xrate<1,coalesce(co_payer_amount*xrate,0),coalesce(co_payer_amount/xrate,0)))) as co_payer_amount,sum(if(' . $currencyFromBaseSQL . ',coalesce(cash_balance,0),if(xrate<1,coalesce(cash_balance*xrate,0),coalesce(cash_balance/xrate,0)))) as cash_balance,sum(if(' . $currencyFromBaseSQL . ',coalesce(co_payer_balance,0),if(xrate<1,coalesce(co_payer_balance*xrate,0),coalesce(co_payer_balance/xrate,0)))) as co_payer_balance,count(cash_amount) as cash_patients,count(co_payer_amount) as co_payer_patients')
            ->first();
        $yearlyCharges = Invoice::with(['currency', 'doctor', 'patient', 'coPayer'])
            ->whereBetween('invoices.date', [$startDate->startOfYear(), $endDate->endOfYear()])
            ->when($coPayerID, function (Builder $query) use ($coPayerID) {
                $query->where('invoices.co_payer_id', $coPayerID);
            })
            ->when($doctorID, function (Builder $query) use ($doctorID) {
                $query->where('invoices.doctor_id', $doctorID);
            })
            ->when($patientID, function (Builder $query) use ($patientID) {
                $query->where('invoices.patient_id', $patientID);
            })
            ->when($branchID, function (Builder $query) use ($branchID) {
                $query->where('invoices.branch_id', $branchID);
            })
            ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                $query->where('currency_id', $currencyID);
            })
            ->when($search, function ($query) use ($search) {
                $query->where('invoices.id', 'like', '%' . $search . '%');
            })
            ->selectRaw('sum(if(' . $currencyFromBaseSQL . ',coalesce(amount,0),if(xrate<1,coalesce(amount*xrate,0),coalesce(amount/xrate,0)))) as amount,sum(if(' . $currencyFromBaseSQL . ',coalesce(balance,0),if(xrate<1,coalesce(balance*xrate,0),coalesce(balance/xrate,0)))) as balance,sum(if(' . $currencyFromBaseSQL . ',coalesce(cash_amount,0),if(xrate<1,coalesce(cash_amount*xrate,0),coalesce(cash_amount/xrate,0)))) as cash_amount,sum(if(' . $currencyFromBaseSQL . ',coalesce(co_payer_amount,0),if(xrate<1,coalesce(co_payer_amount*xrate,0),coalesce(co_payer_amount/xrate,0)))) as co_payer_amount,sum(if(' . $currencyFromBaseSQL . ',coalesce(cash_balance,0),if(xrate<1,coalesce(cash_balance*xrate,0),coalesce(cash_balance/xrate,0)))) as cash_balance,sum(if(' . $currencyFromBaseSQL . ',coalesce(co_payer_balance,0),if(xrate<1,coalesce(co_payer_balance*xrate,0),coalesce(co_payer_balance/xrate,0)))) as co_payer_balance,count(cash_amount) as cash_patients,count(co_payer_amount) as co_payer_patients')
            ->first();

        return [
            'monthly_charges' => $monthlyCharges,
            'yearly_charges' => $yearlyCharges,
        ];
    }

    public function getFinancialProductivityByMonthReport($data = [])
    {
        $startDate = Carbon::parse($data['start_date'] ?? '');
        $endDate = Carbon::parse($data['end_date'] ?? '');
        $startTime = $data['start_time'] ?? '';
        $endTime = $data['end_time'] ?? '';
        $currencyID = $data['currency_id'] ?? '';
        $paginate = $data['paginate'] ?? false;
        $referringPractitionerID = $data['referring_practitioner_id'] ?? '';
        $createdByType = $data['created_by_type'] ?? '';
        $patientID = $data['patient_id'] ?? '';
        $doctorID = $data['doctor_id'] ?? '';
        $coPayerID = $data['co_payer_id'] ?? '';
        $status = $data['status'] ?? '';
        $branchID = $data['branch_id'] ?? '';
        $baseCurrency = Currency::find(Setting::where('setting_key', 'currency')->first()->setting_value);
        $operator = '/';
        if ($currencyID) {
            $currency = Currency::find($currencyID);
            $currencyFromBase = false;
            $currencyFromBaseSQL = 'false';
        } else {
            $currencyID = $baseCurrency->id;
            $currencyFromBase = true;
            $currencyFromBaseSQL = 'true';
        }
        $results = [];
        $nextDate = clone $startDate;
        do {
            $amount = Invoice::selectRaw('sum(if(' . $currencyFromBaseSQL . ',coalesce(amount,0),if(xrate<1,coalesce(amount*xrate,0),coalesce(amount/xrate,0)))) as amount,sum(if(' . $currencyFromBaseSQL . ',coalesce(balance,0),if(xrate<1,coalesce(balance*xrate,0),coalesce(balance/xrate,0)))) as balance,sum(if(' . $currencyFromBaseSQL . ',coalesce(cash_amount,0),if(xrate<1,coalesce(cash_amount*xrate,0),coalesce(cash_amount/xrate,0)))) as cash_amount,sum(if(' . $currencyFromBaseSQL . ',coalesce(co_payer_amount,0),if(xrate<1,coalesce(co_payer_amount*xrate,0),coalesce(co_payer_amount/xrate,0)))) as co_payer_amount,sum(if(' . $currencyFromBaseSQL . ',coalesce(cash_balance,0),if(xrate<1,coalesce(cash_balance*xrate,0),coalesce(cash_balance/xrate,0)))) as cash_balance,sum(if(' . $currencyFromBaseSQL . ',coalesce(co_payer_balance,0),if(xrate<1,coalesce(co_payer_balance*xrate,0),coalesce(co_payer_balance/xrate,0)))) as co_payer_balance,count(cash_amount) as cash_patients,count(co_payer_amount) as co_payer_patients')
                ->when($patientID, function ($query) use ($patientID) {
                    $query->where('invoices.patient_id', $patientID);
                })
                ->when($doctorID, function ($query) use ($doctorID) {
                    $query->where('invoices.doctor_id', $doctorID);
                })
                ->when($currencyID && !$currencyFromBase, function ($query) use ($currencyID) {
                    $query->where('invoices.currency_id', $currencyID);
                })
                ->when($branchID, function ($query) use ($branchID) {
                    $query->where('invoices.branch_id', $branchID);
                })
                ->when($coPayerID, function ($query) use ($coPayerID) {
                    $query->where('invoices.co_payer_id', $coPayerID);
                })
                ->whereBetween('invoices.date', [$nextDate->startOfMonth()->format('Y-m-d'), $nextDate->endOfMonth()->format('Y-m-d')])
                ->first();
            $results[] = [
                'month' => $nextDate->format('M Y'),
                'data' => $amount
            ];
            $nextDate = $nextDate->addMonthNoOverflow();
        } while (Carbon::parse($endDate)->greaterThan($nextDate));


        return $results;
    }
}
