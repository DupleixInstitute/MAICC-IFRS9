<?php

namespace App\Http\Controllers;

use App\Actions\Reports\Reports;
use App\Exports\BaseExport;
use App\Exports\CoPayersReportExport;
use App\Exports\DoctorsReportExport;
use App\Exports\PaymentTypesReportExport;
use App\Models\Event;
use App\Models\Branch;
use App\Models\Consultation;
use App\Models\CourseMaterial;
use App\Models\Currency;
use App\Models\FinancialPeriod;
use App\Models\InventoryProductBrand;
use App\Models\InventoryProductCategory;
use App\Models\InventoryWarehouse;
use App\Models\InvoicePayment;
use App\Models\Client;
use App\Models\PaymentType;
use App\Models\Report;
use App\Models\ReportCategory;
use App\Models\Setting;
use App\Models\Message;
use App\Models\Timezone;
use App\Models\User;
use App\Models\Vital;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;


class ReportsController extends Controller
{
    public Reports $reports;

    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(['permission:reports'])->only(['index', 'show']);
        $this->reports = new Reports();
    }

    public function index()
    {
        return Inertia::render('Reports/Index', [
            'reports' => ReportCategory::with(['reports'])->where('active', 1)->get(),
        ]);
    }

    public function invoices(Request $request)
    {
        $data = [];
        return Inertia::render('Reports/Invoices', [
            'data' => $data,
        ]);
    }

    public function coPayers(Request $request)
    {
        $coPayerID = $request->co_payer_id;
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $currencyID = $request->currency_id;
        if ($currencyID) {
            $currency = Currency::find($request->currency_id);
        } else {
            $currency = Currency::find(Setting::where('setting_key', 'currency')->first()->setting_value);
            $currencyID = $currency->id;
        }
        $query = DB::table('co_payers')
            ->leftJoin('invoices', function ($join) use ($currencyID, $startDate, $endDate) {
                $join->on('invoices.co_payer_id', '=', 'co_payers.id')
                    ->when($currencyID, function (Builder $query) use ($currencyID) {
                        return $query->where('invoices.currency_id', $currencyID);
                    })
                    ->when(($startDate && $endDate), function (Builder $query) use ($startDate, $endDate) {
                        return $query->whereBetween('invoices.date', [$startDate, $endDate]);
                    });
            }
            )
            ->leftJoin('invoice_payments', 'invoice_payments.co_payer_id', 'co_payers.id')
            ->selectRaw("co_payers.name,coalesce(case when invoices.currency_id=" . $currency->id . " then sum(invoices.co_payer_amount) else sum(invoices.co_payer_amount/invoices.xrate) end,0) total_amount,coalesce(case when invoice_payments.currency_id=" . $currency->id . " then sum(invoice_payments.amount) else sum(invoice_payments.amount/invoice_payments.xrate) end,0) total_payments,coalesce(case when invoices.currency_id=" . $currency->id . " then sum(invoices.shortfall) else sum(invoices.shortfall/invoices.xrate) end,0) total_shortfall")
            ->when($coPayerID, function (Builder $query) use ($coPayerID) {
                $query->where('co_payers.id', $coPayerID);
            })
            ->groupBy('co_payers.id');
        $results = $query->get()->map(function ($item) {
            $item->balance = $item->total_amount - $item->total_payments;
            return $item;
        });
        if ($request->download) {
            if ($request->download_type === 'pdf') {
                $pdf = PDF::loadView('reports.co_payer_pdf', ['results' => $results, 'currency' => $currency, 'start_date' => $startDate, 'end_date' => $endDate]);
                return $pdf->download('CoPayers Report.pdf');
            }
            $view = view('reports.co_payer_excel', ['results' => $results, 'currency' => $currency, 'start_date' => $startDate, 'end_date' => $endDate]);
            if ($request->download_type == 'excel') {
                return Excel::download(new CoPayersReportExport($view), 'CoPayers Report.xls');
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new CoPayersReportExport($view), 'CoPayers Report.csv');
            }
        }
        return Inertia::render('Reports/CoPayers', [
            'results' => $results,
            'currency' => $currency,
            'coPayerID' => $coPayerID,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'coPayers' => CourseMaterial::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'currencies' => Currency::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
        ]);
    }

    public function paymentTypes(Request $request)
    {
        $paymentTypeID = $request->payment_type_id;
        $coPayerID = $request->co_payer_id;
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $currencyID = $request->currency_id;
        if ($currencyID) {
            $currency = Currency::find($request->currency_id);
        } else {
            $currency = Currency::find(Setting::where('setting_key', 'currency')->first()->setting_value);
            $currencyID = $currency->id;
        }
        $query = DB::table('payment_types')
            ->leftJoin('invoice_payments', function ($join) use ($currencyID, $coPayerID, $startDate, $endDate) {
                $join->on('invoice_payments.payment_type_id', '=', 'payment_types.id')
                    ->when($currencyID, function (Builder $query) use ($currencyID) {
                        return $query->where('invoice_payments.currency_id', $currencyID);
                    })
                    ->when($coPayerID, function (Builder $query) use ($coPayerID) {
                        return $query->where('invoice_payments.co_payer_id', $coPayerID);
                    })
                    ->when(($startDate && $endDate), function (Builder $query) use ($startDate, $endDate) {
                        return $query->whereBetween('invoice_payments.date', [$startDate, $endDate]);
                    });
            }
            )
            ->selectRaw("payment_types.name,coalesce(sum(invoice_payments.amount),0) total_payments")
            ->when($paymentTypeID, function (Builder $query) use ($paymentTypeID) {
                return $query->where('payment_types.id', $paymentTypeID);
            })
            ->where('payment_types.active', 1)
            ->groupBy('payment_types.id');
        $results = $query->get();
        if ($request->download) {
            if ($request->download_type === 'pdf') {
                $pdf = PDF::loadView('reports.payment_types_pdf', ['results' => $results, 'currency' => $currency, 'start_date' => $startDate, 'end_date' => $endDate]);
                return $pdf->download('Payment Types Report.pdf');
            }
            $view = view('reports.payment_types_excel', ['results' => $results, 'currency' => $currency, 'start_date' => $startDate, 'end_date' => $endDate]);
            if ($request->download_type == 'excel') {
                return Excel::download(new PaymentTypesReportExport($view), 'Payment Types Report.xls');
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new PaymentTypesReportExport($view), 'Payment Types Report.csv');
            }
        }
        return Inertia::render('Reports/PaymentTypes', [
            'results' => $results,
            'currency' => $currency,
            'paymentTypeID' => $paymentTypeID,
            'coPayerID' => $coPayerID,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'paymentTypes' => PaymentType::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'coPayers' => CourseMaterial::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'currencies' => Currency::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
        ]);
    }

    public function doctors(Request $request)
    {
        $branchID = $request->branch_id;
        $doctorID = $request->doctor_id;
        $coPayerID = $request->co_payer_id;
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $currencyID = $request->currency_id;
        if ($currencyID) {
            $currency = Currency::find($request->currency_id);
        } else {
            $currency = Currency::find(Setting::where('setting_key', 'currency')->first()->setting_value);
            $currencyID = $currency->id;
        }
        $invoicesSub = DB::table('invoices')
            ->when($currencyID, function (Builder $query) use ($currencyID) {
                return $query->where('invoices.currency_id', $currencyID);
            })
            ->when($coPayerID, function (Builder $query) use ($coPayerID) {
                return $query->where('invoices.co_payer_id', $coPayerID);
            })
            ->when(($startDate && $endDate), function (Builder $query) use ($startDate, $endDate) {
                return $query->whereBetween('invoices.date', [$startDate, $endDate]);
            })
            ->selectRaw("doctor_id,IF(currency_id={$currency->id},coalesce(sum(amount),0),IF(xrate>{$currency->xrate},coalesce(sum(amount/xrate),0),coalesce(sum(amount*xrate),0))) total_amount,IF(currency_id={$currency->id},coalesce(sum(balance),0),IF(xrate>{$currency->xrate},coalesce(sum(balance/xrate),0),coalesce(sum(balance*xrate),0))) total_balance")
            ->groupBy('doctor_id');

        $query = User::whereHas('roles', function ($query) {
            $query->where('name', 'doctor');
        })
            ->leftJoinSub($invoicesSub, 'invoices_sub', function ($join) {
                $join->on('invoices_sub.doctor_id', '=', 'users.id');
            }
            )
            ->leftJoin('consultations', 'consultations.doctor_id', 'users.id')
            ->selectRaw("concat(users.first_name,' ',users.last_name) full_name,users.practice_number,coalesce(total_amount,0) total_amount,coalesce(total_balance,0) total_balance,coalesce(sum(consultations.id),0) total_consultations")
            ->when($doctorID, function ($query) use ($doctorID) {
                return $query->where('users.id', $doctorID);
            })
            ->groupBy('users.id');
        $results = $query->get()->map(function ($item) {
            $item->total_payments = $item->total_amount - $item->total_balance;
            return $item;
        });

        if ($request->download) {
            if ($request->download_type === 'pdf') {
                $pdf = PDF::loadView('reports.doctors_pdf', ['results' => $results, 'currency' => $currency, 'start_date' => $startDate, 'end_date' => $endDate]);
                return $pdf->download('Doctors Report.pdf');
            }
            $view = view('reports.doctors_excel', ['results' => $results, 'currency' => $currency, 'start_date' => $startDate, 'end_date' => $endDate]);
            if ($request->download_type == 'excel') {
                return Excel::download(new DoctorsReportExport($view), 'Doctors Report.xls');
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new DoctorsReportExport($view), 'Doctors Report.csv');
            }
        }
        return Inertia::render('Reports/Doctors', [
            'results' => $results,
            'currency' => $currency,
            'doctorID' => $doctorID,
            'coPayerID' => $coPayerID,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'users' => User::whereHas('roles', function ($query) {
                $query->where('name', 'doctor');
            })->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name . '(' . $item->practice_number . ')'
                ];
            }),
            'coPayers' => CourseMaterial::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'currencies' => Currency::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
        ]);
    }

    public function debts(Request $request)
    {
        $branchID = $request->branch_id;
        $doctorID = $request->doctor_id;
        $coPayerID = $request->co_payer_id;
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $currencyID = $request->currency_id;
        if ($currencyID) {
            $currency = Currency::find($request->currency_id);
        } else {
            $currency = Currency::find(Setting::where('setting_key', 'currency')->first()->setting_value);
            $currencyID = $currency->id;
        }
        $query = User::whereHas('roles', function ($query) {
            $query->where('name', 'doctor');
        })
            ->leftJoin('invoices', function ($join) use ($currencyID, $coPayerID, $startDate, $endDate) {
                $join->on('invoices.doctor_id', '=', 'users.id')
                    ->when($currencyID, function (Builder $query) use ($currencyID) {
                        return $query->where('invoices.currency_id', $currencyID);
                    })
                    ->when($coPayerID, function (Builder $query) use ($coPayerID) {
                        return $query->where('invoices.co_payer_id', $coPayerID);
                    })
                    ->when(($startDate && $endDate), function (Builder $query) use ($startDate, $endDate) {
                        return $query->whereBetween('invoices.date', [$startDate, $endDate]);
                    });
            }
            )
            ->leftJoin('consultations', 'consultations.doctor_id', 'users.id')
            ->selectRaw("concat(users.first_name,' ',users.last_name) full_name,users.practice_number,coalesce(sum(invoices.amount),0) total_amount,coalesce(sum(invoices.balance),0) total_balance,coalesce(sum(consultations.id),0) total_consultations")
            ->when($doctorID, function ($query) use ($doctorID) {
                return $query->where('users.id', $doctorID);
            })
            ->groupBy('users.id');
        $results = $query->get()->map(function ($item) {
            $item->total_payments = $item->total_amount - $item->total_balance;
            return $item;
        });
        if ($request->download) {
            if ($request->download_type === 'pdf') {
                $pdf = PDF::loadView('reports.doctors_pdf', ['results' => $results, 'currency' => $currency, 'start_date' => $startDate, 'end_date' => $endDate]);
                return $pdf->download('Doctors Report.pdf');
            }
            $view = view('reports.doctors_excel', ['results' => $results, 'currency' => $currency, 'start_date' => $startDate, 'end_date' => $endDate]);
            if ($request->download_type == 'excel') {
                return Excel::download(new DoctorsReportExport($view), 'Doctors Report.xls');
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new DoctorsReportExport($view), 'Doctors Report.csv');
            }
        }
        return Inertia::render('Reports/Debts', [
            'results' => $results,
            'currency' => $currency,
            'doctorID' => $doctorID,
            'coPayerID' => $coPayerID,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'users' => User::whereHas('roles', function ($query) {
                $query->where('name', 'doctor');
            })->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name . '(' . $item->practice_number . ')'
                ];
            }),
            'coPayers' => CourseMaterial::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'currencies' => Currency::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
        ]);
    }

    public function graphical(Request $request)
    {

        return Inertia::render('Reports/Graphical', [
            'users' => User::whereHas('roles', function ($query) {
                $query->where('name', 'doctor');
            })->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name . '(' . $item->practice_number . ')'
                ];
            }),
            'coPayers' => CourseMaterial::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'currencies' => Currency::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
        ]);
    }

    public function getPeriodConsultations(Request $request)
    {
        $period = $request->period;
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $doctorID = $request->doctor_id;
        $chartData = [];
        $limit = 7;
        $add = 'day';
        if ($period === 'week') {
            $startDate = \Carbon\Carbon::today()->startOf('week');
            $limit = 7;
            $add = 'day';
        }
        if ($period === 'month') {
            $startDate = \Carbon\Carbon::today()->startOf('month');
            $limit = 31;
            $add = 'day';
        }
        if ($period === 'year') {
            $startDate = \Carbon\Carbon::today()->startOf('year');
            $limit = 13;
            $add = 'month';
        }

        for ($i = 0; $i < $limit; $i++) {
            $chartData[] = [
                'value' => Consultation::when($period, function ($query) use ($period, $startDate) {
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
                    ->count(),

                'label' => $startDate->format('Y-m-d')
            ];
            $startDate = $startDate->add($add, 1, false);
        }
        return response()->json($chartData);

    }

    public function getPeriodIncome(Request $request)
    {
        $period = $request->period;
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $doctorID = $request->doctor_id;
        $chartData = [];
        $limit = 7;
        $add = 'day';
        if ($period === 'week') {
            $startDate = \Carbon\Carbon::today()->startOf('week');
            $limit = 7;
            $add = 'day';
        }
        if ($period === 'month') {
            $startDate = \Carbon\Carbon::today()->startOf('month');
            $limit = 31;
            $add = 'day';
        }
        if ($period === 'year') {
            $startDate = \Carbon\Carbon::today()->startOf('year');
            $limit = 13;
            $add = 'month';
        }

        for ($i = 0; $i < $limit; $i++) {
            $chartData[] = [
                'value' => InvoicePayment::when($period, function ($query) use ($period, $startDate) {
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
                    ->sum('amount'),

                'label' => $startDate->format('Y-m-d')
            ];
            $startDate = $startDate->add($add, 1, false);
        }
        return response()->json($chartData);

    }

    public function getPeriodMemberRegistrations(Request $request)
    {

        $period = $request->period;
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $doctorID = $request->doctor_id;
        $chartData = [];
        $limit = 7;
        $add = 'day';
        if ($period === 'week') {
            $startDate = \Carbon\Carbon::today()->startOf('week');
            $limit = 7;
            $add = 'day';
            $labelFormat = 'Y-m-d';
        }
        if ($period === 'month') {
            $startDate = \Carbon\Carbon::today()->startOf('month');
            $limit = 31;
            $add = 'day';
            $labelFormat = 'Y-m-d';
        }
        if ($period === 'year') {
            $startDate = \Carbon\Carbon::today()->startOf('year');
            $limit = 13;
            $add = 'month';
            $labelFormat = 'M Y';
        }

        for ($i = 0; $i < $limit; $i++) {
            $chartData[] = [
                'value' => Client::when($period, function ($query) use ($period, $startDate) {
                    if ($period === 'week') {
                        $query->where('created_at', $startDate->format('Y-m-d'));
                    }
                    if ($period === 'month') {
                        $query->where('created_at', $startDate->format('Y-m-d'));
                    }
                    if ($period === 'year') {
                        $query->whereBetween('created_at', [$startDate->startOfMonth()->format('Y-m-d'), $startDate->endOfMonth()->format('Y-m-d')]);
                    }
                })
                    ->count(),

                'label' => $startDate->format($labelFormat)
            ];
            $startDate = $startDate->add($add, 1, false);
        }
        return response()->json($chartData);
    }


    public function getPeriodExpenses(Request $request)
    {
        $period = $request->period;
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $doctorID = $request->doctor_id;
        $chartData = [];
        $limit = 7;
        $add = 'day';
        if ($period === 'week') {
            $startDate = \Carbon\Carbon::today()->startOf('week');
            $limit = 7;
            $add = 'day';
        }
        if ($period === 'month') {
            $startDate = \Carbon\Carbon::today()->startOf('month');
            $limit = 31;
            $add = 'day';
        }
        if ($period === 'year') {
            $startDate = \Carbon\Carbon::today()->startOf('year');
            $limit = 13;
            $add = 'month';
        }

        for ($i = 0; $i < $limit; $i++) {
            $chartData[] = [
                'value' => Consultation::when($period, function ($query) use ($period, $startDate) {
                    if ($period === 'week') {
                        $query->where('date', $startDate->format('Y-m-d'));
                    }
                    if ($period === 'month') {
                        $query->where('date', $startDate->format('Y-m-d'));
                    }
                    if ($period === 'year') {
                        $query->whereBetween('start_date', [$startDate->startOfMonth()->format('Y-m-d'), $startDate->endOfMonth()->format('Y-m-d')]);
                    }
                })
                    ->count(),

                'label' => $startDate->format('Y-m-d')
            ];
            $startDate = $startDate->add($add, 1, false);
        }
        return response()->json($chartData);

    }

    public function getPeriodInventorySales(Request $request)
    {
        $period = $request->period;
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $doctorID = $request->doctor_id;
        $chartData = [];
        $limit = 7;
        $add = 'day';
        if ($period === 'week') {
            $startDate = \Carbon\Carbon::today()->startOf('week');
            $limit = 7;
            $add = 'day';
        }
        if ($period === 'month') {
            $startDate = \Carbon\Carbon::today()->startOf('month');
            $limit = 31;
            $add = 'day';
        }
        if ($period === 'year') {
            $startDate = \Carbon\Carbon::today()->startOf('year');
            $limit = 13;
            $add = 'month';
        }

        for ($i = 0; $i < $limit; $i++) {
            $chartData[] = [
                'value' => Consultation::when($period, function ($query) use ($period, $startDate) {
                    if ($period === 'week') {
                        $query->where('date', $startDate->format('Y-m-d'));
                    }
                    if ($period === 'month') {
                        $query->where('date', $startDate->format('Y-m-d'));
                    }
                    if ($period === 'year') {
                        $query->whereBetween('start_date', [$startDate->startOfMonth()->format('Y-m-d'), $startDate->endOfMonth()->format('Y-m-d')]);
                    }
                })
                    ->count(),

                'label' => $startDate->format('Y-m-d')
            ];
            $startDate = $startDate->add($add, 1, false);
        }
        return response()->json($chartData);

    }

    /*
     * Appointment Schedule Report
     */
    public function a1(Request $request)
    {

        $report = Report::where('symbol', 'a1')->first();
        $results = [];
        $data = [
            'start_date' => $request->start_date,
            'start_time' => $request->start_time,
            'end_date' => $request->end_date,
            'end_time' => $request->end_time,
            'date_range' => $request->date_range,
            'doctor_id' => $request->doctor_id,
            'member_id' => $request->member_id,
            'appointment_type' => $request->appointment_type,
            'status' => $request->status,
            'branch_id' => $request->branch_id,
            'created_by_type' => $request->created_by_type,
            'referring_practitioner_id' => $request->referring_practitioner_id,
        ];

        //only show when filter is applied
        if ($request->start_date && $request->end_date) {
            $results = $this->reports->getAppointments($data);
        }
        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.a1_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.a1_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }
        return Inertia::render('Reports/A1', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,
        ]);

    }

    /*
     * Appointment Worksheet Report
     */
    public function a2(Request $request)
    {

        $report = Report::where('symbol', 'a2')->first();
        $results = [];
        $data = [
            'start_date' => $request->start_date ?? date('Y-m-d'),
            'start_time' => $request->start_time,
            'end_date' => $request->end_date,
            'end_time' => $request->end_time,
            'date_range' => $request->date_range,
            'doctor_id' => $request->doctor_id,
            'member_id' => $request->member_id,
            'appointment_type' => $request->appointment_type,
            'status' => 'approved',
            'branch_id' => $request->branch_id,
            'created_by_type' => $request->created_by_type,
            'referring_practitioner_id' => $request->referring_practitioner_id,
        ];
        //only show when filter is applied
        if ($data['start_date']) {
            $results = $this->reports->getAppointments($data);
        }
        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.a2_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.a2_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }
        return Inertia::render('Reports/A2', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,
        ]);

    }

    /*
     * Appointment Status Report
     */
    public function a3(Request $request)
    {
        $report = Report::where('symbol', 'a3')->first();
        $results = [];
        $data = [
            'start_date' => $request->start_date,
            'start_time' => $request->start_time,
            'end_date' => $request->end_date,
            'end_time' => $request->end_time,
            'date_range' => $request->date_range,
            'doctor_id' => $request->doctor_id,
            'member_id' => $request->member_id,
            'appointment_type' => $request->appointment_type,
            'status' => $request->status,
            'branch_id' => $request->branch_id,
            'created_by_type' => $request->created_by_type,
            'referring_practitioner_id' => $request->referring_practitioner_id,
        ];
        //only show when filter is applied
        if ($request->start_date && $request->end_date) {
            $results = $this->reports->getAppointmentsByStatus($data);
        }
        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.a3_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.a3_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }
        return Inertia::render('Reports/A3', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,
        ]);

    }

    /*
     * Missed/Cancelled Appointments Report
     */
    public function a4(Request $request)
    {
        $report = Report::where('symbol', 'a4')->first();
        $results = [];
        $data = [
            'start_date' => $request->start_date,
            'start_time' => $request->start_time,
            'end_date' => $request->end_date,
            'end_time' => $request->end_time,
            'date_range' => $request->date_range,
            'doctor_id' => $request->doctor_id,
            'member_id' => $request->member_id,
            'appointment_type' => $request->appointment_type,
            'status' => $request->status,
            'branch_id' => $request->branch_id,
            'created_by_type' => $request->created_by_type,
            'referring_practitioner_id' => $request->referring_practitioner_id,
        ];
        if (!$request->status) {
            $data['status'] = ['missed', 'cancelled'];
        }
        //only show when filter is applied
        if ($request->start_date && $request->end_date) {
            $results = $this->reports->getAppointments($data);
        }
        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.a4_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.a4_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }
        return Inertia::render('Reports/A4', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,
        ]);

    }

    /*
     * Web Appointment Request Report
     */
    public function a5(Request $request)
    {
        $report = Report::where('symbol', 'a5')->first();
        $results = [];
        $data = [
            'start_date' => $request->start_date,
            'start_time' => $request->start_time,
            'end_date' => $request->end_date,
            'end_time' => $request->end_time,
            'date_range' => $request->date_range,
            'doctor_id' => $request->doctor_id,
            'member_id' => $request->member_id,
            'appointment_type' => $request->appointment_type,
            'status' => $request->status,
            'branch_id' => $request->branch_id,
            'created_by_type' => 'member',
            'referring_practitioner_id' => $request->referring_practitioner_id,
        ];

        //only show when filter is applied
        if ($request->start_date && $request->end_date) {
            $results = $this->reports->getAppointments($data);
        }
        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.a5_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.a5_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }
        return Inertia::render('Reports/A5', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,
        ]);

    }

    //c reports
    public function c1(Request $request)
    {
        $report = Report::where('symbol', 'c1')->first();
        $results = [];
        $data = [
            'start_date' => $request->start_date,
            'start_time' => $request->start_time,
            'end_date' => $request->end_date,
            'end_time' => $request->end_time,
            'date_range' => $request->date_range,
            'doctor_id' => $request->doctor_id,
            'currency_id' => $request->currency_id,
            'member_id' => $request->member_id,
            'appointment_type' => $request->appointment_type,
            'status' => $request->status,
            'branch_id' => $request->branch_id,
            'created_by_type' => 'member',
            'referring_practitioner_id' => $request->referring_practitioner_id,
        ];
        if (empty($request->download)) {
            $data['paginate'] = true;
        } else {
            $data['paginate'] = false;
        }
        $results = $this->reports->getSummaryInsuranceAgingData($data);
        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.c1_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.c1_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }

        return Inertia::render('Reports/C1', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,
            'currencies' => Currency::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'coPayers' => CourseMaterial::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'paymentTypes' => PaymentType::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
        ]);

    }

    public function c2(Request $request)
    {
        $report = Report::where('symbol', 'c2')->first();
        $results = [];
        $data = [
            'start_date' => $request->start_date,
            'start_time' => $request->start_time,
            'end_date' => $request->end_date ?? date('Y-m-d'),
            'end_time' => $request->end_time,
            'date_range' => $request->date_range,
            'doctor_id' => $request->doctor_id,
            'currency_id' => $request->currency_id,
            'member_id' => $request->member_id,
            'appointment_type' => $request->appointment_type,
            'status' => $request->status,
            'branch_id' => $request->branch_id,
            'referring_practitioner_id' => $request->referring_practitioner_id,
            'include_items' => true,
            'include_aging' => true,
        ];
        if ($request->member_id) {
            $results = $this->reports->getMemberStatementReport($data);
        }
        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.c2_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.c2_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }

        return Inertia::render('Reports/C2', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,
            'currencies' => Currency::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'coPayers' => CourseMaterial::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'paymentTypes' => PaymentType::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
        ]);

    }

    public function c3(Request $request)
    {
        $report = Report::where('symbol', 'c3')->first();
        $results = [];
        $data = [
            'start_date' => $request->start_date,
            'start_time' => $request->start_time,
            'end_date' => $request->end_date ?? date('Y-m-d'),
            'end_time' => $request->end_time,
            'date_range' => $request->date_range,
            'doctor_id' => $request->doctor_id,
            'currency_id' => $request->currency_id,
            'member_id' => $request->member_id,
            'appointment_type' => $request->appointment_type,
            'status' => $request->status,
            'branch_id' => $request->branch_id,
            'referring_practitioner_id' => $request->referring_practitioner_id,
        ];
        if (empty($request->download)) {
            $data['paginate'] = true;
        } else {
            $data['paginate'] = false;
        }
        $results = $this->reports->getMemberStatementSummaryReport($data);

        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.c3_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.c3_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }

        return Inertia::render('Reports/C3', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,
            'currencies' => Currency::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'coPayers' => CourseMaterial::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'paymentTypes' => PaymentType::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
        ]);

    }

    public function c4(Request $request)
    {
        $report = Report::where('symbol', 'c4')->first();
        $results = [];
        $data = [
            'start_date' => $request->start_date,
            'start_time' => $request->start_time,
            'end_date' => $request->end_date,
            'end_time' => $request->end_time,
            'dob_from' => $request->dob_from,
            'dob_to' => $request->dob_to,
            'gender' => $request->gender,
            'sponsor' => $request->sponsor,
            'date_range' => $request->date_range,
            'doctor_id' => $request->doctor_id,
            'currency_id' => $request->currency_id,
            'member_id' => $request->member_id,
            'status' => $request->status,
            'branch_id' => $request->branch_id,
            'referring_practitioner_id' => $request->referring_practitioner_id,
        ];
        if (empty($request->download)) {
            $data['paginate'] = true;
        } else {
            $data['paginate'] = false;
        }
        $results = $this->reports->getMemberDetailsReport($data);

        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.c4_pdf', $data);
                return $pdf->setPaper('a4','landscape')->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.c4_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }

        return Inertia::render('Reports/C4', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,
            'currencies' => Currency::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'coPayers' => CourseMaterial::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'paymentTypes' => PaymentType::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
        ]);

    }
    public function c5(Request $request)
    {
        $report = Report::where('symbol', 'c5')->first();
        $results = [];
        $data = [
            'start_date' => $request->start_date,
            'start_time' => $request->start_time,
            'end_date' => $request->end_date ?? date('Y-m-d'),
            'end_time' => $request->end_time,
            'date_range' => $request->date_range,
            'doctor_id' => $request->doctor_id,
            'currency_id' => $request->currency_id,
            'member_id' => $request->member_id,
            'appointment_type' => $request->appointment_type,
            'status' => $request->status,
            'branch_id' => $request->branch_id,
            'referring_practitioner_id' => $request->referring_practitioner_id,
            'include_items' => true,
            'include_payments' => true,
            'include_aging' => true,
        ];
        if ($request->member_id) {
            $results = $this->reports->getMemberChargesPaymentHistoryReport($data);
        }
        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.c5_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.c5_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }

        return Inertia::render('Reports/C5', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,
            'currencies' => Currency::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'coPayers' => CourseMaterial::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'paymentTypes' => PaymentType::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
        ]);

    }
    public function c6(Request $request)
    {
        $report = Report::where('symbol', 'c6')->first();
        $results = [];
        $data = [
            'start_date' => $request->start_date?? date('Y-m-d'),
            'start_time' => $request->start_time,
            'end_date' => $request->end_date ?? date('Y-m-d'),
            'end_time' => $request->end_time,
            'date_range' => $request->date_range,
            'doctor_id' => $request->doctor_id,
            'currency_id' => $request->currency_id,
            'member_id' => $request->member_id,
            'appointment_type' => $request->appointment_type,
            'status' => $request->status,
            'branch_id' => $request->branch_id,
            'referring_practitioner_id' => $request->referring_practitioner_id,
            'include_items' => true,
            'include_payments' => true,
            'include_aging' => true,
        ];
        if ($request->member_id) {
            $results = $this->reports->getMemberVisitSummaryReport($data);
        }
        if ($request->download) {
            $data['results'] = $results;
            $data['vitals'] = Vital::get();
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.c6_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.c6_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }

        return Inertia::render('Reports/C6', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,
            'currencies' => Currency::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'coPayers' => CourseMaterial::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'vitals' => Vital::get(),
        ]);

    }
//Aging Reports
    /*
        * Summary-Insurance Aging Report
        */
    public function d1(Request $request)
    {
        $report = Report::where('symbol', 'd1')->first();
        $results = [];
        $data = [
            'start_date' => $request->start_date,
            'start_time' => $request->start_time,
            'end_date' => $request->end_date,
            'end_time' => $request->end_time,
            'date_range' => $request->date_range,
            'doctor_id' => $request->doctor_id,
            'currency_id' => $request->currency_id,
            'member_id' => $request->member_id,
            'appointment_type' => $request->appointment_type,
            'status' => $request->status,
            'branch_id' => $request->branch_id,
            'created_by_type' => 'member',
            'referring_practitioner_id' => $request->referring_practitioner_id,
        ];
        if (empty($request->download)) {
            $data['paginate'] = true;
        } else {
            $data['paginate'] = false;
        }
        $results = $this->reports->getSummaryInsuranceAgingData($data);
        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.d1_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.d1_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }

        return Inertia::render('Reports/D1', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,
            'currencies' => Currency::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'coPayers' => CourseMaterial::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'paymentTypes' => PaymentType::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
        ]);

    }

    /*
           * Detailed-Insurance Aging Report
           */
    public function d2(Request $request)
    {
        $report = Report::where('symbol', 'd2')->first();
        $results = [];
        $data = [
            'start_date' => $request->start_date,
            'start_time' => $request->start_time,
            'end_date' => $request->end_date,
            'end_time' => $request->end_time,
            'date_range' => $request->date_range,
            'doctor_id' => $request->doctor_id,
            'currency_id' => $request->currency_id,
            'member_id' => $request->member_id,
            'appointment_type' => $request->appointment_type,
            'status' => $request->status,
            'branch_id' => $request->branch_id,
            'created_by_type' => 'member',
            'referring_practitioner_id' => $request->referring_practitioner_id,
        ];
        if (empty($request->download)) {
            $data['paginate'] = true;
        } else {
            $data['paginate'] = false;
        }
        $results = $this->reports->getSummaryInsuranceAgingData($data);
        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.d2_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.d2_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }

        return Inertia::render('Reports/D2', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,
            'currencies' => Currency::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'coPayers' => CourseMaterial::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'paymentTypes' => PaymentType::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
        ]);

    }

    /*
        * Summary-Member Aging Report
        */
    public function d3(Request $request)
    {
        $report = Report::where('symbol', 'd3')->first();
        $results = [];
        $data = [
            'start_date' => $request->start_date,
            'start_time' => $request->start_time,
            'end_date' => $request->end_date,
            'end_time' => $request->end_time,
            'date_range' => $request->date_range,
            'doctor_id' => $request->doctor_id,
            'currency_id' => $request->currency_id,
            'member_id' => $request->member_id,
            'appointment_type' => $request->appointment_type,
            'status' => $request->status,
            'branch_id' => $request->branch_id,
            'created_by_type' => 'member',
            'referring_practitioner_id' => $request->referring_practitioner_id,
        ];
        if (empty($request->download)) {
            $data['paginate'] = true;
        } else {
            $data['paginate'] = false;
        }
        $results = $this->reports->getSummaryMemberBalanceAgingData($data);
        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.d3_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.d3_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }

        return Inertia::render('Reports/D3', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,
            'currencies' => Currency::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'coPayers' => CourseMaterial::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'paymentTypes' => PaymentType::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
        ]);

    }

    /*
           * Detailed-Member Aging Report
           */
    public function d4(Request $request)
    {
        $report = Report::where('symbol', 'd4')->first();
        $results = [];
        $data = [
            'start_date' => $request->start_date,
            'start_time' => $request->start_time,
            'end_date' => $request->end_date,
            'end_time' => $request->end_time,
            'date_range' => $request->date_range,
            'doctor_id' => $request->doctor_id,
            'currency_id' => $request->currency_id,
            'member_id' => $request->member_id,
            'appointment_type' => $request->appointment_type,
            'status' => $request->status,
            'branch_id' => $request->branch_id,
            'created_by_type' => 'member',
            'referring_practitioner_id' => $request->referring_practitioner_id,
        ];
        if (empty($request->download)) {
            $data['paginate'] = true;
        } else {
            $data['paginate'] = false;
        }
        $results = $this->reports->getSummaryMemberBalanceAgingData($data);
        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.d4_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.d4_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }

        return Inertia::render('Reports/D4', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,
            'currencies' => Currency::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'coPayers' => CourseMaterial::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'paymentTypes' => PaymentType::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
        ]);

    }

    // A/R Aging summary report
    public function d6(Request $request)
    {
        $report = Report::where('symbol', 'd6')->first();
        $results = [];
        $data = [
            'start_date' => $request->start_date,
            'start_time' => $request->start_time,
            'end_date' => $request->end_date,
            'end_time' => $request->end_time,
            'date_range' => $request->date_range,
            'doctor_id' => $request->doctor_id,
            'currency_id' => $request->currency_id,
            'member_id' => $request->member_id,
            'appointment_type' => $request->appointment_type,
            'status' => $request->status,
            'branch_id' => $request->branch_id,
            'created_by_type' => 'member',
            'referring_practitioner_id' => $request->referring_practitioner_id,
        ];

        $results = $this->reports->getAgingData($data);
        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.d6_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.d6_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }
        return Inertia::render('Reports/D6', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,
            'currencies' => Currency::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'coPayers' => CourseMaterial::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'paymentTypes' => PaymentType::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
        ]);

    }
    //Listing Reports
    /*
     * Referring Practitioner Member Count Report
     */
    public function e1(Request $request)
    {
        $report = Report::where('symbol', 'e1')->first();
        $results = [];
        $data = [
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'date_range' => $request->date_range,
            'doctor_id' => $request->doctor_id,
            'member_id' => $request->member_id,
            'branch_id' => $request->branch_id,
            'gender' => $request->gender,
            'status' => $request->status,
            'referring_practitioner_id' => $request->referring_practitioner_id,
        ];
        if (empty($request->download)) {
            $data['paginate'] = true;
        } else {
            $data['paginate'] = false;
        }
        $results = $this->reports->getReferringPractitionerMemberCountReport($data);
        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.e1_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.e1_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }
        return Inertia::render('Reports/E1', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,
            'currencies' => Currency::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'coPayers' => CourseMaterial::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'paymentTypes' => PaymentType::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
        ]);

    }

    /*
     * Referring Practitioner Member Count Report
     */
    public function e2(Request $request)
    {
        $report = Report::where('symbol', 'e2')->first();
        $results = [];
        $data = [
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'date_range' => $request->date_range,
            'doctor_id' => $request->doctor_id,
            'member_id' => $request->member_id,
            'branch_id' => $request->branch_id,
            'gender' => $request->gender,
            'status' => $request->status,
            'referring_practitioner_id' => $request->referring_practitioner_id,
            'co_payer_id' => $request->co_payer_id,
        ];
        if (empty($request->download)) {
            $data['paginate'] = true;
        } else {
            $data['paginate'] = false;
        }
        $results = $this->reports->getMembersByInsuranceCountReport($data);
        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.e2_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.e2_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }
        return Inertia::render('Reports/E2', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,
            'currencies' => Currency::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'coPayers' => CourseMaterial::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'paymentTypes' => PaymentType::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
        ]);

    }

    /*
     * New Members Report
     */
    public function e3(Request $request)
    {
        $report = Report::where('symbol', 'e3')->first();
        $results = [];
        $data = [
            'start_date' => $request->start_date ?? Carbon::today()->startOf('month')->format('Y-m-d'),
            'end_date' => $request->end_date ?? Carbon::today()->endOf('month')->format('Y-m-d'),
            'date_range' => $request->date_range,
            'doctor_id' => $request->doctor_id,
            'member_id' => $request->member_id,
            'branch_id' => $request->branch_id,
            'gender' => $request->gender,
            'status' => $request->status,
            'referring_practitioner_id' => $request->referring_practitioner_id,
            'co_payer_id' => $request->co_payer_id,
        ];
        if (empty($request->download)) {
            $data['paginate'] = true;
        } else {
            $data['paginate'] = false;
        }
        $results = $this->reports->getNewMembersReport($data);
        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.e3_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.e3_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }
        return Inertia::render('Reports/E3', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,
            'coPayers' => CourseMaterial::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
        ]);

    }

    /*
    * Member Visit Count  By Insurance Report
    */
    public function e4(Request $request)
    {
        $report = Report::where('symbol', 'e1')->first();
        $results = [];
        $data = [
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'date_range' => $request->date_range,
            'doctor_id' => $request->doctor_id,
            'member_id' => $request->member_id,
            'branch_id' => $request->branch_id,
            'gender' => $request->gender,
            'status' => $request->status,
            'referring_practitioner_id' => $request->referring_practitioner_id,
        ];
        if (empty($request->download)) {
            $data['paginate'] = true;
        } else {
            $data['paginate'] = false;
        }
        $results = $this->reports->getReferringPractitionerMemberCountReport($data);
        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.e1_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.e1_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }
        return Inertia::render('Reports/E1', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,
            'currencies' => Currency::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'coPayers' => CourseMaterial::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'paymentTypes' => PaymentType::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
        ]);

    }

    /*
    * Member Visit Count  By Insurance Report
    */
    public function e5(Request $request)
    {
        $report = Report::where('symbol', 'e5')->first();
        $results = [];
        $data = [
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'date_range' => $request->date_range,
            'doctor_id' => $request->doctor_id,
            'member_id' => $request->member_id,
            'branch_id' => $request->branch_id,
            'gender' => $request->gender,
            'status' => $request->status,
            'referring_practitioner_id' => $request->referring_practitioner_id,
            'co_payer_id' => $request->co_payer_id,
        ];
        if (empty($request->download)) {
            $data['paginate'] = true;
        } else {
            $data['paginate'] = false;
        }
        $results = $this->reports->getMembersVisitCountByInsuranceReport($data);
        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.e5_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.e5_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }
        return Inertia::render('Reports/E5', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,
            'currencies' => Currency::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'coPayers' => CourseMaterial::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'paymentTypes' => PaymentType::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
        ]);

    }

    /*
    * Member Visit Count  By Practitioner Report
    */
    public function e6(Request $request)
    {
        $report = Report::where('symbol', 'e6')->first();
        $results = [];
        $data = [
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'date_range' => $request->date_range,
            'doctor_id' => $request->doctor_id,
            'member_id' => $request->member_id,
            'branch_id' => $request->branch_id,
            'co_payer_id' => $request->co_payer_id,
            'gender' => $request->gender,
            'status' => $request->status,
            'referring_practitioner_id' => $request->referring_practitioner_id,
        ];
        if (empty($request->download)) {
            $data['paginate'] = true;
        } else {
            $data['paginate'] = false;
        }
        $results = $this->reports->getMembersVisitCountByPractitionerReport($data);
        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.e6_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.e6_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }
        return Inertia::render('Reports/E6', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,
            'currencies' => Currency::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'coPayers' => CourseMaterial::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'paymentTypes' => PaymentType::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
        ]);

    }
    //F reports
    public function f1(Request $request)
    {
        $report = Report::where('symbol', 'f1')->first();
        $results = [];
        $data = [
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'date_range' => $request->date_range,
            'user_id' => $request->user_id,
            'member_id' => $request->member_id,
            'branch_id' => $request->branch_id,
            'co_payer_id' => $request->co_payer_id,
            'gender' => $request->gender,
            'status' => $request->status,
            'referring_practitioner_id' => $request->referring_practitioner_id,
        ];
        if (empty($request->download)) {
            $data['paginate'] = true;
        } else {
            $data['paginate'] = false;
        }
        $results = $this->reports->getAuditTrailReport($data);
        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.f1_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.f1_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }
        return Inertia::render('Reports/F1', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,
            'currencies' => Currency::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'coPayers' => CourseMaterial::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'paymentTypes' => PaymentType::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
        ]);

    }
    public function f2(Request $request)
    {
        $report = Report::where('symbol', 'f2')->first();
        $results = [];
        $data = [
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'date_range' => $request->date_range,
            'user_id' => $request->user_id,
            'role_id' => $request->role_id,
            'branch_id' => $request->branch_id,
            'co_payer_id' => $request->co_payer_id,
            'gender' => $request->gender,
            'status' => $request->status,
            'referring_practitioner_id' => $request->referring_practitioner_id,
        ];
        if (empty($request->download)) {
            $data['paginate'] = true;
        } else {
            $data['paginate'] = false;
        }
        $results = $this->reports->getActiveUsersReport($data);
        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.f2_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.f2_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }
        return Inertia::render('Reports/F2', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,
            'currencies' => Currency::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'roles' => Role::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->display_name
                ];
            }),
        ]);

    }
    //G reports
    public function g1(Request $request)
    {
        $report = Report::where('symbol', 'g1')->first();
        $results = [];
        $data = [
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'date_range' => $request->date_range,
            'claim_batch_id' => $request->claim_batch_id,
            'branch_id' => $request->branch_id,
            'co_payer_id' => $request->co_payer_id,
            'member_id' => $request->member_id,
            'status' => $request->status,
            'referring_practitioner_id' => $request->referring_practitioner_id,
        ];
        if (empty($request->download)) {
            $data['paginate'] = true;
        } else {
            $data['paginate'] = false;
        }
        $results = $this->reports->getClaimsDetailReport($data);
        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.g1_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.g1_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }
        return Inertia::render('Reports/G1', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,
            'currencies' => Currency::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'coPayers' => CourseMaterial::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
        ]);

    }
    public function g2(Request $request)
    {
        $report = Report::where('symbol', 'g2')->first();
        $results = [];
        $data = [
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'date_range' => $request->date_range,
            'claim_batch_id' => $request->claim_batch_id,
            'branch_id' => $request->branch_id,
            'co_payer_id' => $request->co_payer_id,
            'member_id' => $request->member_id,
            'status' => 'rejected',
            'referring_practitioner_id' => $request->referring_practitioner_id,
        ];
        if (empty($request->download)) {
            $data['paginate'] = true;
        } else {
            $data['paginate'] = false;
        }
        $results = $this->reports->getClaimsDetailReport($data);
        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.g2_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.g2_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }
        return Inertia::render('Reports/G2', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,
            'currencies' => Currency::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'coPayers' => CourseMaterial::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
        ]);

    }
    public function g3(Request $request)
    {
        $report = Report::where('symbol', 'g3')->first();
        $results = [];
        $data = [
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'date_range' => $request->date_range,
            'claim_batch_id' => $request->claim_batch_id,
            'branch_id' => $request->branch_id,
            'co_payer_id' => $request->co_payer_id,
            'member_id' => $request->member_id,
            'status' => 'partially_accepted',
            'referring_practitioner_id' => $request->referring_practitioner_id,
        ];
        if (empty($request->download)) {
            $data['paginate'] = true;
        } else {
            $data['paginate'] = false;
        }
        $results = $this->reports->getShortfallsReport($data);
        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.g3_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.g3_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }
        return Inertia::render('Reports/G3', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,
            'currencies' => Currency::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'coPayers' => CourseMaterial::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
        ]);

    }
    public function g4(Request $request)
    {
        $report = Report::where('symbol', 'g4')->first();
        $results = [];
        $data = [
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'date_range' => $request->date_range,
            'claim_batch_id' => $request->claim_batch_id,
            'branch_id' => $request->branch_id,
            'co_payer_id' => $request->co_payer_id,
            'member_id' => $request->member_id,
            'status' =>$request->status,
            'referring_practitioner_id' => $request->referring_practitioner_id,
        ];
        if (empty($request->download)) {
            $data['paginate'] = true;
        } else {
            $data['paginate'] = false;
        }
        $results = $this->reports->getClaimsLineLevelReport($data);
        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.g4_pdf', $data);
                return $pdf->setPaper('a4','landscape')->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.g4_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }
        return Inertia::render('Reports/G4', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,
            'currencies' => Currency::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'coPayers' => CourseMaterial::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
        ]);

    }
//dashboard reports
    /*
     * Web Appointment Request Report
     */
    public function h2(Request $request)
    {
        $report = Report::where('symbol', 'h2')->first();
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $doctorID = $request->doctor_id;
        $appointmentType = $request->appointment_type;
        $status = $request->status;
        $branchID = $request->branch_id;
        $referringPractitionerID = $request->referring_practitioner_id;

        return Inertia::render('Reports/H2', [
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'doctor_id' => $request->doctor_id,
            'payment_type_id' => $request->payment_type_id,
            'status' => $request->status,
            'branch_id' => $request->branch_id,
            'referring_practitioner_id' => $request->referring_practitioner_id,
            'report' => $report,
            'currencies' => Currency::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'coPayers' => CourseMaterial::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'paymentTypes' => PaymentType::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
        ]);

    }

    public function h4(Request $request)
    {
        $report = Report::where('symbol', 'h4')->first();
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $doctorID = $request->doctor_id;
        $appointmentType = $request->appointment_type;
        $status = $request->status;
        $branchID = $request->branch_id;
        $referringPractitionerID = $request->referring_practitioner_id;

        return Inertia::render('Reports/H4', [
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'doctor_id' => $request->doctor_id,
            'appointment_type' => $request->appointment_type,
            'status' => $request->status,
            'branch_id' => $request->branch_id,
            'referring_practitioner_id' => $request->referring_practitioner_id,
            'report' => $report,
        ]);

    }

    public function getAppointmentsByStatus(Request $request)
    {
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $doctorID = $request->doctor_id;
        $appointmentType = $request->appointment_type;
        $status = $request->status;
        $branchID = $request->branch_id;
        $referringPractitionerID = $request->referring_practitioner_id;
        $period = $request->period;
        $data = [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'doctor_id' => $doctorID,
            'appointment_type' => $appointmentType,
            'status' => $status,
            'branch_id' => $branchID,
            'referring_practitioner_id' => $referringPractitionerID,
            'period' => $period,
        ];
        return response()->json($this->reports->getAppointmentsByStatus($data));
    }

    public function getAppointmentsByPeriod(Request $request)
    {
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $doctorID = $request->doctor_id;
        $appointmentType = $request->appointment_type;
        $status = $request->status;
        $branchID = $request->branch_id;
        $referringPractitionerID = $request->referring_practitioner_id;
        $period = $request->period;
        $data = [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'doctor_id' => $doctorID,
            'appointment_type' => $appointmentType,
            'status' => $status,
            'branch_id' => $branchID,
            'referring_practitioner_id' => $referringPractitionerID,
            'period' => $period,
        ];
        return response()->json($this->reports->getAppointmentsByPeriod($data));
    }

    public function getPaymentsByPaymentType(Request $request)
    {
        $data = [
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'doctor_id' => $request->doctor_id,
            'branch_id' => $request->branch_id,
            'currency_id' => $request->currency_id,
            'co_payer_id' => $request->co_payer_id,
            'payment_type_id' => $request->payment_type_id,
            'paid_by' => $request->paid_by,
            'period' => $request->period,
        ];
        return response()->json($this->reports->getPaymentsByPaymentType($data));
    }

    public function getPaymentsByPeriod(Request $request)
    {
        $data = [
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'doctor_id' => $request->doctor_id,
            'branch_id' => $request->branch_id,
            'currency_id' => $request->currency_id,
            'co_payer_id' => $request->co_payer_id,
            'payment_type_id' => $request->payment_type_id,
            'paid_by' => $request->paid_by,
            'period' => $request->period,
        ];
        return response()->json($this->reports->getPaymentsByPeriod($data));
    }

    public function getIncomeExpenses(Request $request)
    {
        $data = [
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'doctor_id' => $request->doctor_id,
            'branch_id' => $request->branch_id,
            'currency_id' => $request->currency_id,
            'co_payer_id' => $request->co_payer_id,
            'payment_type_id' => $request->payment_type_id,
            'paid_by' => $request->paid_by,
            'period' => $request->period,
        ];
        return response()->json($this->reports->getIncomeExpenses($data));
    }

    public function getPeriodIncomeExpenses(Request $request)
    {
        $data = [
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'doctor_id' => $request->doctor_id,
            'branch_id' => $request->branch_id,
            'currency_id' => $request->currency_id,
            'co_payer_id' => $request->co_payer_id,
            'payment_type_id' => $request->payment_type_id,
            'paid_by' => $request->paid_by,
            'period' => $request->period,
        ];
        return response()->json($this->reports->getPeriodIncomeExpenses($data));
    }

//inventory L reports
    public function l1(Request $request)
    {
        $report = Report::where('symbol', 'l1')->first();
        $results = [];
        $data = [
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'inventory_warehouse_id' => $request->inventory_warehouse_id,
            'inventory_category_id' => $request->inventory_category_id,
            'inventory_sub_category_id' => $request->inventory_sub_category_id,
            'currency_id' => $request->currency_id,
            'product_type' => $request->product_type,
            'inventory_product_brand_id' => $request->inventory_product_brand_id,
            'branch_id' => $request->branch_id,
            'featured' => $request->featured,
            'search' => $request->search,
            'status' => $request->status,
        ];
        if (empty($request->download)) {
            $data['paginate'] = true;
        } else {
            $data['paginate'] = false;
        }
        $results = $this->reports->getProductsReport($data);
        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.l1_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.l1_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }
        return Inertia::render('Reports/L1', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,
            'brands' => InventoryProductBrand::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
            'warehouses' => InventoryWarehouse::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
            'parentCategories' => InventoryProductCategory::where('is_parent', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                    'parent_id' => $item->parent_id,
                    'is_parent' => $item->is_parent,
                ];
            }),
            'categories' => InventoryProductCategory::where('is_parent', 0)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                    'parent_id' => $item->parent_id,
                    'is_parent' => $item->is_parent,
                ];
            }),
        ]);

    }

    public function l2(Request $request)
    {
        $report = Report::where('symbol', 'l2')->first();
        $results = [];
        $data = [
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'inventory_warehouse_id' => $request->inventory_warehouse_id,
            'inventory_category_id' => $request->inventory_category_id,
            'inventory_sub_category_id' => $request->inventory_sub_category_id,
            'currency_id' => $request->currency_id,
            'product_type' => $request->product_type,
            'inventory_product_brand_id' => $request->inventory_product_brand_id,
            'branch_id' => $request->branch_id,
            'featured' => $request->featured,
            'search' => $request->search,
            'status' => $request->status,
            'order_by' => $request->order_by ?? 'quantity',
        ];
        if (empty($request->download)) {
            //$data['paginate'] = true;
        } else {
            //$data['paginate'] = false;
        }
        $results = $this->reports->getTopSellingProductsReport($data);
        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.l2_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.l2_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }
        return Inertia::render('Reports/L2', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,
            'brands' => InventoryProductBrand::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
            'warehouses' => InventoryWarehouse::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
            'parentCategories' => InventoryProductCategory::where('is_parent', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                    'parent_id' => $item->parent_id,
                    'is_parent' => $item->is_parent,
                ];
            }),
            'categories' => InventoryProductCategory::where('is_parent', 0)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                    'parent_id' => $item->parent_id,
                    'is_parent' => $item->is_parent,
                ];
            }),
        ]);

    }

    public function l3(Request $request)
    {
        $report = Report::where('symbol', 'l3')->first();
        $results = [];
        $data = [
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'inventory_warehouse_id' => $request->inventory_warehouse_id,
            'co_payer_id' => $request->co_payer_id,
            'member_id' => $request->member_id,
            'currency_id' => $request->currency_id,
            'sale_type' => $request->sale_type,
            'payment_status' => $request->payment_status,
            'branch_id' => $request->branch_id,
            'shipping_status' => $request->shipping_status,
            'search' => $request->search,
            'status' => $request->status,
            'sponsor' => $request->sponsor,
            'order_by' => $request->order_by ?? 'created_at',
        ];
        if (empty($request->download)) {
            $data['paginate'] = true;
        } else {
            $data['paginate'] = false;
        }
        $results = $this->reports->getSalesReport($data);
        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.l3_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.l3_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }
        return Inertia::render('Reports/L3', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,
            'warehouses' => InventoryWarehouse::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
            'coPayers' => CourseMaterial::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'currencies' => Currency::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'paymentTypes' => PaymentType::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
        ]);

    }

    public function l4(Request $request)
    {
        $report = Report::where('symbol', 'l4')->first();
        $results = [];
        $data = [
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'inventory_warehouse_id' => $request->inventory_warehouse_id,
            'supplier_id' => $request->supplier_id,
            'member_id' => $request->member_id,
            'currency_id' => $request->currency_id,
            'sale_type' => $request->sale_type,
            'payment_status' => $request->payment_status,
            'branch_id' => $request->branch_id,
            'search' => $request->search,
            'status' => $request->status,
            'order_by' => $request->order_by ?? 'created_at',
        ];
        if (empty($request->download)) {
            $data['paginate'] = true;
        } else {
            $data['paginate'] = false;
        }
        $results = $this->reports->getPurchasesReport($data);
        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.l4_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.l4_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }
        return Inertia::render('Reports/L4', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,
            'warehouses' => InventoryWarehouse::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
            'suppliers' => Message::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'currencies' => Currency::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
        ]);

    }

    public function l5(Request $request)
    {
        $report = Report::where('symbol', 'l5')->first();
        $results = [];
        $data = [
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'inventory_warehouse_id' => $request->inventory_warehouse_id,
            'supplier_id' => $request->supplier_id,
            'member_id' => $request->member_id,
            'currency_id' => $request->currency_id,
            'sale_type' => $request->sale_type,
            'payment_status' => $request->payment_status,
            'branch_id' => $request->branch_id,
            'search' => $request->search,
            'status' => $request->status,
            'order_by' => $request->order_by ?? 'created_at',
        ];
        if (empty($request->download)) {
            $data['paginate'] = true;
        } else {
            $data['paginate'] = false;
        }
        $results = $this->reports->getSuppliersReport($data);
        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.l5_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.l5_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }
        return Inertia::render('Reports/L5', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,
            'warehouses' => InventoryWarehouse::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
            'suppliers' => Message::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'currencies' => Currency::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
        ]);

    }

    public function l6(Request $request)
    {
        $report = Report::where('symbol', 'l6')->first();
        $results = [];
        $data = [
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'inventory_warehouse_id' => $request->inventory_warehouse_id,
            'member_id' => $request->member_id,
            'currency_id' => $request->currency_id,
            'sale_type' => $request->sale_type,
            'payment_status' => $request->payment_status,
            'branch_id' => $request->branch_id,
            'search' => $request->search,
            'status' => $request->status ?? 'completed',
            'order_by' => $request->order_by ?? 'total_sales_quantity',
        ];
        if (empty($request->download)) {
            $data['paginate'] = true;
        } else {
            $data['paginate'] = false;
        }
        $results = $this->reports->getCustomersReport($data);
        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.l6_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.l6_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }
        return Inertia::render('Reports/L6', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,
            'warehouses' => InventoryWarehouse::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
            'currencies' => Currency::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
        ]);

    }

    public function l7(Request $request)
    {
        $report = Report::where('symbol', 'l7')->first();
        $results = [];
        $data = [
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'inventory_warehouse_id' => $request->inventory_warehouse_id,
            'member_id' => $request->member_id,
            'currency_id' => $request->currency_id,
            'sale_type' => $request->sale_type,
            'payment_status' => $request->payment_status,
            'branch_id' => $request->branch_id,
            'search' => $request->search,
            'status' => $request->status ?? 'completed',
            'order_by' => $request->order_by ?? 'total_sales_quantity',
        ];
        if (empty($request->download)) {
            $data['paginate'] = true;
        } else {
            $data['paginate'] = false;
        }
        $results = $this->reports->getTopCustomersReport($data);
        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.l7_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.l7_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }
        return Inertia::render('Reports/L7', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,
            'warehouses' => InventoryWarehouse::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
            'currencies' => Currency::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
        ]);

    }

    public function l8(Request $request)
    {
        $report = Report::where('symbol', 'l8')->first();
        $results = [];
        $data = [
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'inventory_warehouse_id' => $request->inventory_warehouse_id,
            'co_payer_id' => $request->co_payer_id,
            'member_id' => $request->member_id,
            'currency_id' => $request->currency_id,
            'sale_type' => $request->sale_type,
            'payment_status' => $request->payment_status,
            'branch_id' => $request->branch_id,
            'shipping_status' => $request->shipping_status,
            'search' => $request->search,
            'status' => $request->status,
            'sponsor' => $request->sponsor,
            'order_by' => $request->order_by ?? 'created_at',
        ];
        if (empty($request->download)) {
            $data['paginate'] = true;
        } else {
            $data['paginate'] = false;
        }
        $results = $this->reports->getSaleReturnsReport($data);
        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.l8_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.l8_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }
        return Inertia::render('Reports/L8', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,
            'warehouses' => InventoryWarehouse::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
            'coPayers' => CourseMaterial::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'currencies' => Currency::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
        ]);

    }

    public function l9(Request $request)
    {
        $report = Report::where('symbol', 'l9')->first();
        $results = [];
        $data = [
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'inventory_warehouse_id' => $request->inventory_warehouse_id,
            'supplier_id' => $request->supplier_id,
            'member_id' => $request->member_id,
            'currency_id' => $request->currency_id,
            'sale_type' => $request->sale_type,
            'payment_status' => $request->payment_status,
            'branch_id' => $request->branch_id,
            'search' => $request->search,
            'status' => $request->status,
            'order_by' => $request->order_by ?? 'created_at',
        ];
        if (empty($request->download)) {
            $data['paginate'] = true;
        } else {
            $data['paginate'] = false;
        }
        $results = $this->reports->getPurchaseReturnsReport($data);
        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.l9_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.l9_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }
        return Inertia::render('Reports/L9', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,
            'warehouses' => InventoryWarehouse::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
            'suppliers' => Message::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'currencies' => Currency::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
        ]);

    }

    public function l10(Request $request)
    {
        $report = Report::where('symbol', 'l10')->first();
        $results = [];
        $data = [
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'inventory_warehouse_id' => $request->inventory_warehouse_id,
            'member_id' => $request->member_id,
            'created_by_id' => $request->created_by_id,
            'currency_id' => $request->currency_id,
            'sale_type' => $request->sale_type,
            'payment_status' => $request->payment_status,
            'branch_id' => $request->branch_id,
            'search' => $request->search,
            'status' => $request->status ?? 'completed',
            'order_by' => $request->order_by ?? 'total_sales_quantity',
        ];
        if (empty($request->download)) {
            $data['paginate'] = true;
        } else {
            $data['paginate'] = false;
        }
        $results = $this->reports->getStaffReport($data);
        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.l10_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.l10_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }
        return Inertia::render('Reports/L10', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,
            'warehouses' => InventoryWarehouse::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
            'currencies' => Currency::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
        ]);

    }

    public function l11(Request $request)
    {
        $report = Report::where('symbol', 'l11')->first();
        $results = [];
        $data = [
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'inventory_warehouse_id' => $request->inventory_warehouse_id,
            'member_id' => $request->member_id,
            'created_by_id' => $request->created_by_id,
            'currency_id' => $request->currency_id,
            'sale_type' => $request->sale_type,
            'payment_status' => $request->payment_status,
            'branch_id' => $request->branch_id,
            'search' => $request->search,
            'status' => $request->status ?? 'completed',
            'order_by' => $request->order_by ?? 'total_sales_quantity',
        ];
        if (empty($request->download)) {
            $data['paginate'] = true;
        } else {
            $data['paginate'] = false;
        }
        $results = $this->reports->getTopStaffReport($data);
        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.l11_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.l11_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }
        return Inertia::render('Reports/L11', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,
            'warehouses' => InventoryWarehouse::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
            'currencies' => Currency::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
        ]);

    }

    public function l12(Request $request)
    {
        $report = Report::where('symbol', 'l12')->first();
        $results = [];
        $data = [
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'inventory_warehouse_id' => $request->inventory_warehouse_id,
            'supplier_id' => $request->supplier_id,
            'payment_type_id' => $request->payment_type_id,
            'currency_id' => $request->currency_id,
            'branch_id' => $request->branch_id,
            'search' => $request->search,
            'order_by' => $request->order_by ?? 'date',
        ];
        if (empty($request->download)) {
            $data['paginate'] = true;
        } else {
            $data['paginate'] = false;
        }
        $results = $this->reports->getPurchasePaymentsReport($data);
        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.l12_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.l12_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }
        return Inertia::render('Reports/L12', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,
            'warehouses' => InventoryWarehouse::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
            'suppliers' => Message::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
            'currencies' => Currency::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
        ]);

    }

    public function l13(Request $request)
    {
        $report = Report::where('symbol', 'l13')->first();
        $results = [];
        $data = [
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'inventory_warehouse_id' => $request->inventory_warehouse_id,
            'member_id' => $request->member_id,
            'created_by_id' => $request->created_by_id,
            'currency_id' => $request->currency_id,
            'sale_type' => $request->sale_type,
            'payment_type_id' => $request->payment_type_id,
            'branch_id' => $request->branch_id,
            'search' => $request->search,
            'status' => $request->status ?? 'completed',
            'order_by' => $request->order_by ?? 'date',
        ];
        if (empty($request->download)) {
            $data['paginate'] = true;
        } else {
            $data['paginate'] = false;
        }
        $results = $this->reports->getSalePaymentsReport($data);
        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.l13_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.l13_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }
        return Inertia::render('Reports/L13', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,
            'warehouses' => InventoryWarehouse::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
            'currencies' => Currency::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'paymentTypes' => PaymentType::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
        ]);

    }

    public function l14(Request $request)
    {
        $report = Report::where('symbol', 'l14')->first();
        $results = [];
        $data = [
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'inventory_warehouse_id' => $request->inventory_warehouse_id,
            'supplier_id' => $request->supplier_id,
            'member_id' => $request->member_id,
            'currency_id' => $request->currency_id,
            'sale_type' => $request->sale_type,
            'payment_status' => $request->payment_status,
            'branch_id' => $request->branch_id,
            'search' => $request->search,
            'status' => $request->status,
            'order_by' => $request->order_by ?? 'created_at',
        ];
        if (empty($request->download)) {
            $data['paginate'] = true;
        } else {
            $data['paginate'] = false;
        }
        $results = $this->reports->getSuppliersDebtReport($data);
        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.l14_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.l14_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }
        return Inertia::render('Reports/L14', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,
            'warehouses' => InventoryWarehouse::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
            'suppliers' => Message::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'currencies' => Currency::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'paymentTypes' => PaymentType::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
        ]);

    }

    public function l15(Request $request)
    {
        $report = Report::where('symbol', 'l15')->first();
        $results = [];
        $data = [
            'start_date' => $request->start_date ?? Carbon::today()->format('Y-m-d'),
            'end_date' => $request->end_date ?? Carbon::today()->format('Y-m-d'),
            'inventory_warehouse_id' => $request->inventory_warehouse_id,
            'co_payer_id' => $request->co_payer_id,
            'member_id' => $request->member_id,
            'currency_id' => $request->currency_id,
            'sale_type' => $request->sale_type,
            'payment_status' => $request->payment_status,
            'branch_id' => $request->branch_id,
            'shipping_status' => $request->shipping_status,
            'search' => $request->search,
            'status' => $request->status,
            'sponsor' => $request->sponsor,
            'order_by' => $request->order_by ?? 'created_at',
        ];
        if (empty($request->download)) {
            $data['paginate'] = true;
        } else {
            $data['paginate'] = false;
        }
        $results = $this->reports->getSalesReport($data);
        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.l15_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.l15_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }
        return Inertia::render('Reports/L15', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,
            'warehouses' => InventoryWarehouse::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
            'coPayers' => CourseMaterial::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'currencies' => Currency::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'paymentTypes' => PaymentType::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
        ]);

    }

    public function l16(Request $request)
    {
        $report = Report::where('symbol', 'l16')->first();
        $results = [];
        $data = [
            'start_date' => $request->start_date ?? Carbon::today()->startOfWeek()->format('Y-m-d'),
            'end_date' => $request->end_date ?? Carbon::today()->endOfWeek()->format('Y-m-d'),
            'inventory_warehouse_id' => $request->inventory_warehouse_id,
            'co_payer_id' => $request->co_payer_id,
            'member_id' => $request->member_id,
            'currency_id' => $request->currency_id,
            'sale_type' => $request->sale_type,
            'payment_status' => $request->payment_status,
            'branch_id' => $request->branch_id,
            'shipping_status' => $request->shipping_status,
            'search' => $request->search,
            'status' => $request->status,
            'sponsor' => $request->sponsor,
            'order_by' => $request->order_by ?? 'created_at',
        ];
        if (empty($request->download)) {
            $data['paginate'] = true;
        } else {
            $data['paginate'] = false;
        }
        $results = $this->reports->getSalesReport($data);
        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.l16_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.l16_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }
        return Inertia::render('Reports/L16', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,
            'warehouses' => InventoryWarehouse::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
            'coPayers' => CourseMaterial::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'currencies' => Currency::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'paymentTypes' => PaymentType::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
        ]);

    }

    public function l17(Request $request)
    {
        $report = Report::where('symbol', 'l17')->first();
        $results = [];
        $data = [
            'start_date' => $request->start_date ?? Carbon::today()->startOfMonth()->format('Y-m-d'),
            'end_date' => $request->end_date ?? Carbon::today()->endOfMonth()->format('Y-m-d'),
            'inventory_warehouse_id' => $request->inventory_warehouse_id,
            'co_payer_id' => $request->co_payer_id,
            'member_id' => $request->member_id,
            'currency_id' => $request->currency_id,
            'sale_type' => $request->sale_type,
            'payment_status' => $request->payment_status,
            'branch_id' => $request->branch_id,
            'shipping_status' => $request->shipping_status,
            'search' => $request->search,
            'status' => $request->status,
            'sponsor' => $request->sponsor,
            'order_by' => $request->order_by ?? 'created_at',
        ];
        if (empty($request->download)) {
            $data['paginate'] = true;
        } else {
            $data['paginate'] = false;
        }
        $results = $this->reports->getSalesReport($data);
        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.l17_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.l17_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }
        return Inertia::render('Reports/L17', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,
            'warehouses' => InventoryWarehouse::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
            'coPayers' => CourseMaterial::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'currencies' => Currency::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'paymentTypes' => PaymentType::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
        ]);

    }

    public function l18(Request $request)
    {
        $report = Report::where('symbol', 'l18')->first();
        $results = [];
        $data = [
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'inventory_warehouse_id' => $request->inventory_warehouse_id,
            'member_id' => $request->member_id,
            'created_by_id' => $request->created_by_id,
            'currency_id' => $request->currency_id,
            'sale_type' => $request->sale_type,
            'payment_type_id' => $request->payment_type_id,
            'branch_id' => $request->branch_id,
            'search' => $request->search,
            'status' => $request->status ?? 'completed',
            'order_by' => $request->order_by ?? 'amount',
        ];
        $results = $this->reports->getSalesByPaymentsType($data);
        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.l18_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.l18_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }
        return Inertia::render('Reports/L18', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,
            'warehouses' => InventoryWarehouse::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
            'currencies' => Currency::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'paymentTypes' => PaymentType::where('active', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
        ]);

    }

    public function l19(Request $request)
    {
        $report = Report::where('symbol', 'l19')->first();
        $results = [];
        $data = [
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'inventory_warehouse_id' => $request->inventory_warehouse_id,
            'inventory_category_id' => $request->inventory_category_id,
            'inventory_sub_category_id' => $request->inventory_sub_category_id,
            'currency_id' => $request->currency_id,
            'product_type' => $request->product_type,
            'inventory_product_brand_id' => $request->inventory_product_brand_id,
            'branch_id' => $request->branch_id,
            'featured' => $request->featured,
            'search' => $request->search,
            'status' => $request->status,
        ];
        if (empty($request->download)) {
            $data['paginate'] = true;
        } else {
            $data['paginate'] = false;
        }
        $results = $this->reports->getStockReport($data);
        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.l19_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.l19_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }
        return Inertia::render('Reports/L19', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,
            'brands' => InventoryProductBrand::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
            'warehouses' => InventoryWarehouse::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
            'parentCategories' => InventoryProductCategory::where('is_parent', 1)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                    'parent_id' => $item->parent_id,
                    'is_parent' => $item->is_parent,
                ];
            }),
            'categories' => InventoryProductCategory::where('is_parent', 0)->get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                    'parent_id' => $item->parent_id,
                    'is_parent' => $item->is_parent,
                ];
            }),
        ]);

    }

    //charges and payments report
    public function i1(Request $request)
    {
        $report = Report::where('symbol', 'I1')->first();
        $results = [];
        $data = [
            'start_date' => $request->start_date ?? Carbon::today()->startOfMonth()->format('Y-m-d'),
            'end_date' => $request->end_date ?? Carbon::today()->endOfMonth()->format('Y-m-d'),
            'currency_id' => $request->currency_id,
            'doctor_id' => $request->doctor_id,
            'branch_id' => $request->branch_id,
            'featured' => $request->featured,
            'search' => $request->search,
            'financial_period_id' => $request->financial_period_id ?? FinancialPeriod::where('closed', 0)->first()->id ?? '',
            'reconciled' => $request->reconciled,
        ];
        if (empty($request->download)) {
            $data['paginate'] = true;
        } else {
            $data['paginate'] = false;
        }
        $results = $this->reports->getChargesByProviderReport($data);

        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.i1_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.i1_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }
        return Inertia::render('Reports/I1', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,

            'branches' => Branch::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
            'currencies' => Currency::where('active', 1)->get()->transform(function ($currency) {
                return [
                    'value' => $currency->id,
                    'label' => $currency->name,
                ];
            }),
            'financialPeriods' => FinancialPeriod::get()->transform(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name ?? $item->start_date . ' to ' . $item->end_date,
                ];
            }),
        ]);

    }

    public function i2(Request $request)
    {
        $report = Report::where('symbol', 'I2')->first();
        $results = [
            'data' => [],
            'user' => ''
        ];
        $data = [
            'start_date' => $request->start_date ?? Carbon::today()->startOfMonth()->format('Y-m-d'),
            'end_date' => $request->end_date ?? Carbon::today()->endOfMonth()->format('Y-m-d'),
            'currency_id' => $request->currency_id,
            'doctor_id' => $request->doctor_id,
            'branch_id' => $request->branch_id,
            'featured' => $request->featured,
            'search' => $request->search,
            'financial_period_id' => $request->financial_period_id ?? FinancialPeriod::where('closed', 0)->first()->id ?? '',
            'reconciled' => $request->reconciled,
        ];

        $results = $this->reports->getChargesByProviderByMonthReport($data);

        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.i2_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.i2_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }
        return Inertia::render('Reports/I2', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,

            'branches' => Branch::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
            'currencies' => Currency::where('active', 1)->get()->transform(function ($currency) {
                return [
                    'value' => $currency->id,
                    'label' => $currency->name,
                ];
            }),
            'financialPeriods' => FinancialPeriod::get()->transform(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name ?? $item->start_date . ' to ' . $item->end_date,
                ];
            }),
        ]);

    }

    public function i3(Request $request)
    {
        $report = Report::where('symbol', 'I3')->first();
        $results = [
            'data' => [],
            'user' => ''
        ];
        $data = [
            'start_date' => $request->start_date ?? Carbon::today()->startOfMonth()->format('Y-m-d'),
            'end_date' => $request->end_date ?? Carbon::today()->endOfMonth()->format('Y-m-d'),
            'currency_id' => $request->currency_id,
            'doctor_id' => $request->doctor_id,
            'branch_id' => $request->branch_id,
            'co_payer_id' => $request->co_payer_id,
            'search' => $request->search,
            'financial_period_id' => $request->financial_period_id ?? FinancialPeriod::where('closed', 0)->first()->id ?? '',
            'reconciled' => $request->reconciled,
        ];

        $results = $this->reports->getChargesByProviderByMonthByCoPayerReport($data);

        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.i3_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.i3_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }
        return Inertia::render('Reports/I3', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,

            'branches' => Branch::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
            'currencies' => Currency::where('active', 1)->get()->transform(function ($currency) {
                return [
                    'value' => $currency->id,
                    'label' => $currency->name,
                ];
            }),
            'financialPeriods' => FinancialPeriod::get()->transform(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name ?? $item->start_date . ' to ' . $item->end_date,
                ];
            }),
            'coPayers' => CourseMaterial::where('active', 1)->get()->transform(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
        ]);

    }

    public function i4(Request $request)
    {
        $report = Report::where('symbol', 'I4')->first();
        $results = [
            'data' => [],
            'user' => ''
        ];
        $data = [
            'start_date' => $request->start_date ?? Carbon::today()->startOfMonth()->format('Y-m-d'),
            'end_date' => $request->end_date ?? Carbon::today()->endOfMonth()->format('Y-m-d'),
            'currency_id' => $request->currency_id,
            'doctor_id' => $request->doctor_id,
            'branch_id' => $request->branch_id,
            'payment_type_id' => $request->payment_type_id,
            'co_payer_id' => $request->co_payer_id,
            'member_id' => $request->member_id,
            'search' => $request->search,
            'financial_period_id' => $request->financial_period_id ?? FinancialPeriod::where('closed', 0)->first()->id ?? '',
            'reconciled' => $request->reconciled,
        ];
        if (empty($request->download)) {
            $data['paginate'] = true;
        } else {
            $data['paginate'] = false;
        }
        $results = $this->reports->getPaymentsReport($data);

        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.i4_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.i4_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }
        return Inertia::render('Reports/I4', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,

            'branches' => Branch::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
            'currencies' => Currency::where('active', 1)->get()->transform(function ($currency) {
                return [
                    'value' => $currency->id,
                    'label' => $currency->name,
                ];
            }),
            'financialPeriods' => FinancialPeriod::get()->transform(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name ?? $item->start_date . ' to ' . $item->end_date,
                ];
            }),
            'coPayers' => CourseMaterial::where('active', 1)->get()->transform(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
            'paymentTypes' => PaymentType::where('active', 1)->get()->transform(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
        ]);

    }

    public function i5(Request $request)
    {
        $report = Report::where('symbol', 'I5')->first();
        $results = [
            'data' => [],
            'user' => ''
        ];
        $data = [
            'start_date' => $request->start_date ?? Carbon::today()->startOfMonth()->format('Y-m-d'),
            'end_date' => $request->end_date ?? Carbon::today()->endOfMonth()->format('Y-m-d'),
            'currency_id' => $request->currency_id,
            'doctor_id' => $request->doctor_id,
            'branch_id' => $request->branch_id,
            'payment_type_id' => $request->payment_type_id,
            'co_payer_id' => $request->co_payer_id,
            'member_id' => $request->member_id,
            'search' => $request->search,
            'financial_period_id' => $request->financial_period_id ?? FinancialPeriod::where('closed', 0)->first()->id ?? '',
            'reconciled' => $request->reconciled,
        ];
        $results = $this->reports->getPaymentsByInsuranceReport($data);

        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.i5_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.i5_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }
        return Inertia::render('Reports/I5', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,

            'branches' => Branch::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
            'currencies' => Currency::where('active', 1)->get()->transform(function ($currency) {
                return [
                    'value' => $currency->id,
                    'label' => $currency->name,
                ];
            }),
            'financialPeriods' => FinancialPeriod::get()->transform(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name ?? $item->start_date . ' to ' . $item->end_date,
                ];
            }),
            'coPayers' => CourseMaterial::where('active', 1)->get()->transform(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
            'paymentTypes' => PaymentType::where('active', 1)->get()->transform(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
        ]);

    }

    public function i6(Request $request)
    {
        $report = Report::where('symbol', 'I6')->first();
        $results = [
            'data' => [],
            'user' => ''
        ];
        $data = [
            'start_date' => $request->start_date ?? Carbon::today()->startOfMonth()->format('Y-m-d'),
            'end_date' => $request->end_date ?? Carbon::today()->endOfMonth()->format('Y-m-d'),
            'currency_id' => $request->currency_id,
            'doctor_id' => $request->doctor_id,
            'branch_id' => $request->branch_id,
            'payment_type_id' => $request->payment_type_id,
            'co_payer_id' => $request->co_payer_id,
            'member_id' => $request->member_id,
            'search' => $request->search,
            'financial_period_id' => $request->financial_period_id ?? FinancialPeriod::where('closed', 0)->first()->id ?? '',
            'reconciled' => $request->reconciled,
        ];
        $results = $this->reports->procedureProductivityByInsuranceReport($data);

        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.i6_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.i6_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }
        return Inertia::render('Reports/I6', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,

            'branches' => Branch::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
            'currencies' => Currency::where('active', 1)->get()->transform(function ($currency) {
                return [
                    'value' => $currency->id,
                    'label' => $currency->name,
                ];
            }),
            'financialPeriods' => FinancialPeriod::get()->transform(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name ?? $item->start_date . ' to ' . $item->end_date,
                ];
            }),
            'coPayers' => CourseMaterial::where('active', 1)->get()->transform(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
            'paymentTypes' => PaymentType::where('active', 1)->get()->transform(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
        ]);

    }

    public function i7(Request $request)
    {
        $report = Report::where('symbol', 'I7')->first();
        $results = [
            'data' => [],
            'user' => ''
        ];
        $data = [
            'start_date' => $request->start_date ?? Carbon::today()->startOfMonth()->format('Y-m-d'),
            'end_date' => $request->end_date ?? Carbon::today()->endOfMonth()->format('Y-m-d'),
            'currency_id' => $request->currency_id,
            'doctor_id' => $request->doctor_id,
            'branch_id' => $request->branch_id,
            'payment_type_id' => $request->payment_type_id,
            'co_payer_id' => $request->co_payer_id,
            'member_id' => $request->member_id,
            'search' => $request->search,
            'financial_period_id' => $request->financial_period_id ?? FinancialPeriod::where('closed', 0)->first()->id ?? '',
            'reconciled' => $request->reconciled,
        ];
        if (empty($request->download)) {
            $data['paginate'] = true;
        } else {
            $data['paginate'] = false;
        }
        $results = $this->reports->getMembersPaymentsDetailedReport($data);

        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.i7_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.i7_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }
        return Inertia::render('Reports/I7', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,

            'branches' => Branch::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
            'currencies' => Currency::where('active', 1)->get()->transform(function ($currency) {
                return [
                    'value' => $currency->id,
                    'label' => $currency->name,
                ];
            }),
            'financialPeriods' => FinancialPeriod::get()->transform(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name ?? $item->start_date . ' to ' . $item->end_date,
                ];
            }),
            'coPayers' => CourseMaterial::where('active', 1)->get()->transform(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
            'paymentTypes' => PaymentType::where('active', 1)->get()->transform(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
        ]);

    }

    public function i8(Request $request)
    {
        $report = Report::where('symbol', 'I8')->first();
        $results = [
            'data' => [],
            'user' => ''
        ];
        $data = [
            'start_date' => $request->start_date ?? Carbon::today()->startOfMonth()->format('Y-m-d'),
            'end_date' => $request->end_date ?? Carbon::today()->endOfMonth()->format('Y-m-d'),
            'currency_id' => $request->currency_id,
            'doctor_id' => $request->doctor_id,
            'branch_id' => $request->branch_id,
            'payment_type_id' => $request->payment_type_id,
            'co_payer_id' => $request->co_payer_id,
            'member_id' => $request->member_id,
            'paid_by' => $request->paid_by,
            'order_by' => $request->order_by,
            'search' => $request->search,
            'financial_period_id' => $request->financial_period_id ?? FinancialPeriod::where('closed', 0)->first()->id ?? '',
            'reconciled' => $request->reconciled,
        ];

        $results = $this->reports->getPaymentsByPaymentType($data);

        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.i8_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.i8_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }
        return Inertia::render('Reports/I8', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,

            'branches' => Branch::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
            'currencies' => Currency::where('active', 1)->get()->transform(function ($currency) {
                return [
                    'value' => $currency->id,
                    'label' => $currency->name,
                ];
            }),
            'financialPeriods' => FinancialPeriod::get()->transform(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name ?? $item->start_date . ' to ' . $item->end_date,
                ];
            }),
            'coPayers' => CourseMaterial::where('active', 1)->get()->transform(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
            'paymentTypes' => PaymentType::where('active', 1)->get()->transform(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
        ]);

    }

    public function i9(Request $request)
    {
        $report = Report::where('symbol', 'I9')->first();
        $results = [
            'data' => [],
            'user' => ''
        ];
        $data = [
            'start_date' => $request->start_date ?? Carbon::today()->startOfMonth()->format('Y-m-d'),
            'end_date' => $request->end_date ?? Carbon::today()->endOfMonth()->format('Y-m-d'),
            'currency_id' => $request->currency_id,
            'doctor_id' => $request->doctor_id,
            'branch_id' => $request->branch_id,
            'payment_type_id' => $request->payment_type_id,
            'co_payer_id' => $request->co_payer_id,
            'referring_practitioner_id' => $request->referring_practitioner_id,
            'member_id' => $request->member_id,
            'paid_by' => $request->paid_by,
            'search' => $request->search,
            'financial_period_id' => $request->financial_period_id ?? FinancialPeriod::where('closed', 0)->first()->id ?? '',
            'reconciled' => $request->reconciled,
        ];
        if (empty($request->download)) {
            $data['paginate'] = true;
        } else {
            $data['paginate'] = false;
        }
        $results = $this->reports->getPaymentsByReferringPractitionerReport($data);

        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.i9_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.i9_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }
        return Inertia::render('Reports/I9', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,

            'branches' => Branch::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
            'currencies' => Currency::where('active', 1)->get()->transform(function ($currency) {
                return [
                    'value' => $currency->id,
                    'label' => $currency->name,
                ];
            }),
            'financialPeriods' => FinancialPeriod::get()->transform(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name ?? $item->start_date . ' to ' . $item->end_date,
                ];
            }),
            'coPayers' => CourseMaterial::where('active', 1)->get()->transform(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
            'paymentTypes' => PaymentType::where('active', 1)->get()->transform(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
        ]);

    }

    public function i10(Request $request)
    {
        $report = Report::where('symbol', 'I10')->first();
        $results = [
            'data' => [],
            'user' => ''
        ];
        $data = [
            'start_date' => $request->start_date ?? Carbon::today()->startOfMonth()->format('Y-m-d'),
            'end_date' => $request->end_date ?? Carbon::today()->endOfMonth()->format('Y-m-d'),
            'currency_id' => $request->currency_id,
            'doctor_id' => $request->doctor_id,
            'branch_id' => $request->branch_id,
            'payment_type_id' => $request->payment_type_id,
            'co_payer_id' => $request->co_payer_id,
            'member_id' => $request->member_id,
            'paid_by' => $request->paid_by,
            'search' => $request->search,
            'financial_period_id' => $request->financial_period_id ?? FinancialPeriod::where('closed', 0)->first()->id ?? '',
            'reconciled' => $request->reconciled,
        ];
        if (empty($request->download)) {
            $data['paginate'] = true;
        } else {
            $data['paginate'] = false;
        }
        $results = $this->reports->getSummaryEncounterLineActivitiesReport($data);

        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.i10_pdf', $data);
                return $pdf->setPaper('a4', 'landscape')->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.i10_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }
        return Inertia::render('Reports/I10', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,

            'branches' => Branch::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
            'currencies' => Currency::where('active', 1)->get()->transform(function ($currency) {
                return [
                    'value' => $currency->id,
                    'label' => $currency->name,
                ];
            }),
            'financialPeriods' => FinancialPeriod::get()->transform(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name ?? $item->start_date . ' to ' . $item->end_date,
                ];
            }),
            'coPayers' => CourseMaterial::where('active', 1)->get()->transform(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
            'paymentTypes' => PaymentType::where('active', 1)->get()->transform(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
        ]);

    }

    public function i11(Request $request)
    {
        $report = Report::where('symbol', 'I11')->first();
        $results = [
            'data' => [],
            'user' => ''
        ];
        $data = [
            'start_date' => $request->start_date ?? Carbon::today()->startOfMonth()->format('Y-m-d'),
            'end_date' => $request->end_date ?? Carbon::today()->endOfMonth()->format('Y-m-d'),
            'currency_id' => $request->currency_id,
            'doctor_id' => $request->doctor_id,
            'branch_id' => $request->branch_id,
            'payment_type_id' => $request->payment_type_id,
            'co_payer_id' => $request->co_payer_id,
            'member_id' => $request->member_id,
            'paid_by' => $request->paid_by,
            'search' => $request->search,
            'financial_period_id' => $request->financial_period_id ?? FinancialPeriod::where('closed', 0)->first()->id ?? '',
            'reconciled' => $request->reconciled,
        ];
        if (empty($request->download)) {
            $data['paginate'] = true;
        } else {
            $data['paginate'] = false;
        }
        $results = $this->reports->getSummaryEncounterLineActivitiesReport($data);

        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.i11_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.i11_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }
        return Inertia::render('Reports/I11', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,

            'branches' => Branch::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
            'currencies' => Currency::where('active', 1)->get()->transform(function ($currency) {
                return [
                    'value' => $currency->id,
                    'label' => $currency->name,
                ];
            }),
            'financialPeriods' => FinancialPeriod::get()->transform(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name ?? $item->start_date . ' to ' . $item->end_date,
                ];
            }),
            'coPayers' => CourseMaterial::where('active', 1)->get()->transform(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
            'paymentTypes' => PaymentType::where('active', 1)->get()->transform(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
        ]);

    }

    //Financial J reports
    public function j1(Request $request)
    {
        $report = Report::where('symbol', 'j1')->first();
        $results = [];
        $data = [
            'start_date' => $request->start_date ?? Carbon::today()->startOfMonth()->format('Y-m-d'),
            'end_date' => $request->end_date ?? Carbon::today()->endOfMonth()->format('Y-m-d'),
            'currency_id' => $request->currency_id,
            'doctor_id' => $request->doctor_id,
            'branch_id' => $request->branch_id,
            'featured' => $request->featured,
            'search' => $request->search,
            'financial_period_id' => $request->financial_period_id ?? FinancialPeriod::where('closed', 0)->first()->id ?? '',
            'reconciled' => $request->reconciled,
        ];
        $results = $this->reports->getSystemFinancialSummaryReport($data);

        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.j1_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.j1_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }
        return Inertia::render('Reports/J1', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,

            'branches' => Branch::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
            'currencies' => Currency::where('active', 1)->get()->transform(function ($currency) {
                return [
                    'value' => $currency->id,
                    'label' => $currency->name,
                ];
            }),
            'financialPeriods' => FinancialPeriod::get()->transform(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name ?? $item->start_date . ' to ' . $item->end_date,
                ];
            }),
        ]);

    }

    public function j2(Request $request)
    {
        $report = Report::where('symbol', 'j2')->first();
        $results = [];
        $data = [
            'start_date' => $request->start_date ?? Carbon::today()->startOfMonth()->format('Y-m-d'),
            'end_date' => $request->end_date ?? Carbon::today()->endOfMonth()->format('Y-m-d'),
            'currency_id' => $request->currency_id,
            'doctor_id' => $request->doctor_id,
            'member_id' => $request->member_id,
            'branch_id' => $request->branch_id,
            'featured' => $request->featured,
            'search' => $request->search,
            'financial_period_id' => $request->financial_period_id ?? FinancialPeriod::where('closed', 0)->first()->id ?? '',
            'reconciled' => $request->reconciled,
        ];
        $results = $this->reports->getMembersFinancialSummaryReport($data);
        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.j2_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.j2_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }
        return Inertia::render('Reports/J2', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,

            'branches' => Branch::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
            'currencies' => Currency::where('active', 1)->get()->transform(function ($currency) {
                return [
                    'value' => $currency->id,
                    'label' => $currency->name,
                ];
            }),
            'financialPeriods' => FinancialPeriod::get()->transform(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name ?? $item->start_date . ' to ' . $item->end_date,
                ];
            }),
        ]);

    }

    public function j3(Request $request)
    {
        $report = Report::where('symbol', 'j3')->first();
        $results = [];
        $data = [
            'start_date' => $request->start_date ?? Carbon::today()->startOfMonth()->format('Y-m-d'),
            'end_date' => $request->end_date ?? Carbon::today()->endOfMonth()->format('Y-m-d'),
            'currency_id' => $request->currency_id,
            'doctor_id' => $request->doctor_id,
            'co_payer_id' => $request->co_payer_id,
            'member_id' => $request->member_id,
            'branch_id' => $request->branch_id,
            'featured' => $request->featured,
            'search' => $request->search,
            'financial_period_id' => $request->financial_period_id ?? FinancialPeriod::where('closed', 0)->first()->id ?? '',
            'reconciled' => $request->reconciled,
        ];
        $results = $this->reports->getMembersInsuranceOnAccountReport($data);
        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.j3_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.j3_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }
        return Inertia::render('Reports/J3', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,

            'branches' => Branch::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
            'coPayers' => CourseMaterial::where('active', 1)->get()->transform(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
            'currencies' => Currency::where('active', 1)->get()->transform(function ($currency) {
                return [
                    'value' => $currency->id,
                    'label' => $currency->name,
                ];
            }),
            'financialPeriods' => FinancialPeriod::get()->transform(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name ?? $item->start_date . ' to ' . $item->end_date,
                ];
            }),
        ]);

    }

    public function j4(Request $request)
    {
        $report = Report::where('symbol', 'j4')->first();
        $results = [];
        $data = [
            'start_date' => $request->start_date ?? Carbon::today()->startOfMonth()->format('Y-m-d'),
            'end_date' => $request->end_date ?? Carbon::today()->endOfMonth()->format('Y-m-d'),
            'currency_id' => $request->currency_id,
            'doctor_id' => $request->doctor_id,
            'co_payer_id' => $request->co_payer_id,
            'member_id' => $request->member_id,
            'branch_id' => $request->branch_id,
            'featured' => $request->featured,
            'search' => $request->search,
            'financial_period_id' => $request->financial_period_id ?? FinancialPeriod::where('closed', 0)->first()->id ?? '',
            'reconciled' => $request->reconciled,
        ];
        $results = $this->reports->getMembersOnAccountBalanceAnalysisReport($data);
        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.j4_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.j4_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }
        return Inertia::render('Reports/J4', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,

            'branches' => Branch::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
            'coPayers' => CourseMaterial::where('active', 1)->get()->transform(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
            'currencies' => Currency::where('active', 1)->get()->transform(function ($currency) {
                return [
                    'value' => $currency->id,
                    'label' => $currency->name,
                ];
            }),
            'financialPeriods' => FinancialPeriod::get()->transform(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name ?? $item->start_date . ' to ' . $item->end_date,
                ];
            }),
        ]);

    }

    public function j5(Request $request)
    {
        $report = Report::where('symbol', 'j5')->first();
        $results = [];
        $data = [
            'start_date' => $request->start_date ?? Carbon::today()->startOfMonth()->format('Y-m-d'),
            'end_date' => $request->end_date ?? Carbon::today()->endOfMonth()->format('Y-m-d'),
            'currency_id' => $request->currency_id,
            'doctor_id' => $request->doctor_id,
            'co_payer_id' => $request->co_payer_id,
            'member_id' => $request->member_id,
            'branch_id' => $request->branch_id,
            'featured' => $request->featured,
            'search' => $request->search,
            'financial_period_id' => $request->financial_period_id ?? FinancialPeriod::where('closed', 0)->first()->id ?? '',
            'reconciled' => $request->reconciled,
        ];
        if (empty($request->download)) {
            $data['paginate'] = true;
        } else {
            $data['paginate'] = false;
        }
        $results = $this->reports->getProviderProductivityReport($data);
        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.j5_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.j5_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }
        return Inertia::render('Reports/J5', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,

            'branches' => Branch::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
            'coPayers' => CourseMaterial::where('active', 1)->get()->transform(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
            'currencies' => Currency::where('active', 1)->get()->transform(function ($currency) {
                return [
                    'value' => $currency->id,
                    'label' => $currency->name,
                ];
            }),
            'financialPeriods' => FinancialPeriod::get()->transform(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name ?? $item->start_date . ' to ' . $item->end_date,
                ];
            }),
        ]);

    }
    public function j6(Request $request)
    {
        $report = Report::where('symbol', 'j6')->first();
        $results = [];
        $data = [
            'start_date' => $request->start_date ?? Carbon::today()->startOfMonth()->format('Y-m-d'),
            'end_date' => $request->end_date ?? Carbon::today()->endOfMonth()->format('Y-m-d'),
            'currency_id' => $request->currency_id,
            'doctor_id' => $request->doctor_id,
            'co_payer_id' => $request->co_payer_id,
            'member_id' => $request->member_id,
            'branch_id' => $request->branch_id,
            'featured' => $request->featured,
            'search' => $request->search,
            'financial_period_id' => $request->financial_period_id ?? FinancialPeriod::where('closed', 0)->first()->id ?? '',
            'reconciled' => $request->reconciled,
        ];
        if (empty($request->download)) {
            $data['paginate'] = true;
        } else {
            $data['paginate'] = false;
        }
        $results = $this->reports->getChargesPaymentsReconciliationReport($data);
        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.j6_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.j6_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }
        return Inertia::render('Reports/J6', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,

            'branches' => Branch::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
            'coPayers' => CourseMaterial::where('active', 1)->get()->transform(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
            'currencies' => Currency::where('active', 1)->get()->transform(function ($currency) {
                return [
                    'value' => $currency->id,
                    'label' => $currency->name,
                ];
            }),
            'financialPeriods' => FinancialPeriod::get()->transform(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name ?? $item->start_date . ' to ' . $item->end_date,
                ];
            }),
        ]);

    }
    public function j7(Request $request)
    {
        $report = Report::where('symbol', 'j7')->first();
        $results = [];
        $data = [
            'start_date' => $request->start_date ?? Carbon::today()->startOfMonth()->format('Y-m-d'),
            'end_date' => $request->end_date ?? Carbon::today()->format('Y-m-d'),
            'currency_id' => $request->currency_id,
            'doctor_id' => $request->doctor_id,
            'co_payer_id' => $request->co_payer_id,
            'member_id' => $request->member_id,
            'branch_id' => $request->branch_id,
            'featured' => $request->featured,
            'search' => $request->search,
            'financial_period_id' => $request->financial_period_id ?? FinancialPeriod::where('closed', 0)->first()->id ?? '',
            'reconciled' => $request->reconciled,
        ];
        $results = $this->reports->getMonthlyActivityAnalysisReport($data);
        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.j7_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.j7_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }
        return Inertia::render('Reports/J7', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,

            'branches' => Branch::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
            'coPayers' => CourseMaterial::where('active', 1)->get()->transform(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
            'currencies' => Currency::where('active', 1)->get()->transform(function ($currency) {
                return [
                    'value' => $currency->id,
                    'label' => $currency->name,
                ];
            }),
            'financialPeriods' => FinancialPeriod::get()->transform(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name ?? $item->start_date . ' to ' . $item->end_date,
                ];
            }),
        ]);

    }
    public function j8(Request $request)
    {
        $report = Report::where('symbol', 'j8')->first();
        $results = [];
        $data = [
            'start_date' => $request->start_date ?? Carbon::today()->startOfMonth()->format('Y-m-d'),
            'end_date' => $request->end_date ?? Carbon::today()->endOfMonth()->format('Y-m-d'),
            'currency_id' => $request->currency_id,
            'doctor_id' => $request->doctor_id,
            'co_payer_id' => $request->co_payer_id,
            'member_id' => $request->member_id,
            'branch_id' => $request->branch_id,
            'featured' => $request->featured,
            'search' => $request->search,
            'financial_period_id' => $request->financial_period_id ?? FinancialPeriod::where('closed', 0)->first()->id ?? '',
            'reconciled' => $request->reconciled,
        ];
        $results = $this->reports->getFinancialProductivityByMonthReport($data);
        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.j8_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.j8_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }
        return Inertia::render('Reports/J8', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,

            'branches' => Branch::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
            'coPayers' => CourseMaterial::where('active', 1)->get()->transform(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
            'currencies' => Currency::where('active', 1)->get()->transform(function ($currency) {
                return [
                    'value' => $currency->id,
                    'label' => $currency->name,
                ];
            }),
            'financialPeriods' => FinancialPeriod::get()->transform(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name ?? $item->start_date . ' to ' . $item->end_date,
                ];
            }),
        ]);

    }

    public function j22(Request $request)
    {
        $report = Report::where('symbol', 'j22')->first();
        $results = [];
        $data = [
            'start_date' => $request->start_date,
            'end_date' => $request->end_date ?? date('Y-m-d'),
            'currency_id' => $request->currency_id,
            'product_type' => $request->product_type,
            'branch_id' => $request->branch_id,
            'featured' => $request->featured,
            'search' => $request->search,
            'financial_period_id' => $request->financial_period_id ?? FinancialPeriod::where('closed', 0)->first()->id ?? '',
            'reconciled' => $request->reconciled,
        ];
        $results = $this->reports->getBalanceSheet($data);

        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.j22_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.j22_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }
        return Inertia::render('Reports/J22', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,

            'branches' => Branch::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
            'currencies' => Currency::where('active', 1)->get()->transform(function ($currency) {
                return [
                    'value' => $currency->id,
                    'label' => $currency->name,
                ];
            }),
            'financialPeriods' => FinancialPeriod::get()->transform(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name ?? $item->start_date . ' to ' . $item->end_date,
                ];
            }),
        ]);

    }

    public function j23(Request $request)
    {
        $report = Report::where('symbol', 'j23')->first();
        $results = [];
        $data = [
            'start_date' => $request->start_date,
            'end_date' => $request->end_date ?? date('Y-m-d'),
            'currency_id' => $request->currency_id,
            'product_type' => $request->product_type,
            'branch_id' => $request->branch_id,
            'featured' => $request->featured,
            'search' => $request->search,
            'reconciled' => $request->reconciled,
        ];
        $results = $this->reports->getTrialBalance($data);
        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.j23_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.j23_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }

        return Inertia::render('Reports/J23', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,

            'branches' => Branch::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
            'currencies' => Currency::where('active', 1)->get()->transform(function ($currency) {
                return [
                    'value' => $currency->id,
                    'label' => $currency->name,
                ];
            }),
        ]);

    }

    public function j24(Request $request)
    {
        $report = Report::where('symbol', 'j24')->first();
        $results = [];
        $data = [
            'start_date' => $request->start_date ?? Carbon::today()->startOfMonth()->format('Y-m-d'),
            'end_date' => $request->end_date ?? Carbon::today()->endOfMonth()->format('Y-m-d'),
            'currency_id' => $request->currency_id,
            'product_type' => $request->product_type,
            'branch_id' => $request->branch_id,
            'featured' => $request->featured,
            'search' => $request->search,
            'financial_period_id' => $request->financial_period_id,
            'reconciled' => $request->reconciled,
        ];
        $results = $this->reports->getIncomeStatement($data);

        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.j24_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.j24_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }
        return Inertia::render('Reports/J24', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,

            'branches' => Branch::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
            'currencies' => Currency::where('active', 1)->get()->transform(function ($currency) {
                return [
                    'value' => $currency->id,
                    'label' => $currency->name,
                ];
            }),
            'financialPeriods' => FinancialPeriod::get()->transform(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name ?? $item->start_date . ' to ' . $item->end_date,
                ];
            }),
        ]);

    }

    public function j25(Request $request)
    {
        $report = Report::where('symbol', 'j25')->first();
        $results = [];
        $data = [
            'start_date' => $request->start_date ?? Carbon::today()->startOfMonth()->format('Y-m-d'),
            'end_date' => $request->end_date ?? Carbon::today()->endOfMonth()->format('Y-m-d'),
            'currency_id' => $request->currency_id,
            'product_type' => $request->product_type,
            'branch_id' => $request->branch_id,
            'featured' => $request->featured,
            'search' => $request->search,
            'financial_period_id' => $request->financial_period_id,
            'reconciled' => $request->reconciled,
        ];
        $results = $this->reports->getLedgerReport($data);

        if ($request->download) {
            $data['results'] = $results;
            $data['download_type'] = $request->download_type;
            if ($request->download_type === 'pdf') {
                $pdf = Pdf::loadView('reports.j25_pdf', $data);
                return $pdf->download(generate_report_friendly_name($report->name . '.pdf'));
            }
            $view = view('reports.j25_excel', $data);

            if ($request->download_type == 'excel') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.xls'));
            }
            if ($request->download_type == 'csv') {
                return Excel::download(new BaseExport($view), generate_report_friendly_name($report->name . '.csv'));
            }
        }
        return Inertia::render('Reports/J25', [
            'filters' => $data,
            'results' => $results,
            'report' => $report,

            'branches' => Branch::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
            'currencies' => Currency::where('active', 1)->get()->transform(function ($currency) {
                return [
                    'value' => $currency->id,
                    'label' => $currency->name,
                ];
            }),
            'financialPeriods' => FinancialPeriod::get()->transform(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name ?? $item->start_date . ' to ' . $item->end_date,
                ];
            }),
        ]);

    }
}
