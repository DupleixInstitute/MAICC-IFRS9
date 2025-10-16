<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\Client;
use App\Models\BalanceSheet;
use App\Models\BalanceSheetData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ClientBalanceSheetController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(['permission:clients.balance_sheet.index'])->only(['index', 'show']);
        $this->middleware(['permission:clients.balance_sheet.create'])->only(['create', 'store']);
        $this->middleware(['permission:clients.balance_sheet.update'])->only(['edit', 'update']);
        $this->middleware(['permission:clients.balance_sheet.destroy'])->only(['destroy']);
    }

    public function index(Client $client)
    {
        $sheets = BalanceSheet::where('client_id', $client->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return Inertia::render('Clients/BalanceSheets/Index', [
            'client' => $client,
            'sheets' => $sheets,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Inertia\Response
     */
    public function create(Client $client)
    {
        $currentAssets = ChartOfAccount::whereIn('account_type', ['current_asset', 'cash', 'bank', 'stock','accounts_receivable'])->get();
        $otherAssets = ChartOfAccount::whereIn('account_type', ['other_asset'])->get();
        $otherCurrentAssets = ChartOfAccount::whereIn('account_type', ['other_current_asset'])->get();
        $fixedAssets = ChartOfAccount::whereIn('account_type', ['fixed_asset', 'non_current_asset', 'property_plant_equipment', 'investment_property', 'right_of_use_asset', 'intangible_asset'])->get();
        $currentLiabilities = ChartOfAccount::whereIn('account_type', ['current_liability', 'income_tax', 'credit_card','accounts_payable'])->get();
        $longTermLiabilities = ChartOfAccount::whereIn('account_type', ['long_term_liability', 'other_liability', 'finance_lease_liability', 'other_long_term_liability', 'deferred_income_tax'])->get();
        $equity = ChartOfAccount::whereIn('account_type', ['retained_earning', 'capital_contribution', 'equity', 'reserve'])->get();
        return Inertia::render('Clients/BalanceSheets/Create', [
            'client' => $client,
            'currentAssets' => $currentAssets,
            'otherAssets' => $otherAssets,
            'otherCurrentAssets' => $otherCurrentAssets,
            'fixedAssets' => $fixedAssets,
            'currentLiabilities' => $currentLiabilities,
            'longTermLiabilities' => $longTermLiabilities,
            'equity' => $equity,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request, Client $client)
    {
        $request->validate([
            'year' => ['required'],
        ]);
        $sheet = new BalanceSheet();
        $sheet->created_by_id = Auth::id();
        $sheet->client_id = $client->id;
        $sheet->year = $request->year;
        $sheet->as_at_date = $request->as_at_date;
        $sheet->total_assets = $request->total_assets;
        $sheet->total_equity = $request->total_equity;
        $sheet->total_liabilities = $request->total_liabilities;
        $sheet->total_equity_liabilities = $request->total_equity_liabilities;
        $sheet->total_working_capital = $request->total_working_capital;
        $sheet->total_current_assets = $request->total_current_assets;
        $sheet->total_current_liabilities = $request->total_current_liabilities;
        $sheet->total_long_term_liabilities = $request->total_long_term_liabilities;
        $sheet->total_other_current_assets = $request->total_other_current_assets;
        $sheet->total_other_assets = $request->total_other_assets;
        $sheet->total_fixed_assets = $request->total_fixed_assets;
        $sheet->description = $request->description;
        $sheet->save();
        //save the charts
        foreach ($request->charts['current_assets'] as $item) {
            $sheetData = new BalanceSheetData();
            $sheetData->client_id = $client->id;
            $sheetData->balance_sheet_id = $sheet->id;
            $sheetData->chart_of_account_id = $item['chart_of_account_id'];
            $sheetData->name = $item['name'];
            $sheetData->amount = $item['amount'];
            $sheetData->save();
        }
        foreach ($request->charts['other_assets'] as $item) {
            $sheetData = new BalanceSheetData();
            $sheetData->client_id = $client->id;
            $sheetData->balance_sheet_id = $sheet->id;
            $sheetData->chart_of_account_id = $item['chart_of_account_id'];
            $sheetData->name = $item['name'];
            $sheetData->amount = $item['amount'];
            $sheetData->save();
        }
        foreach ($request->charts['other_current_assets'] as $item) {
            $sheetData = new BalanceSheetData();
            $sheetData->client_id = $client->id;
            $sheetData->balance_sheet_id = $sheet->id;
            $sheetData->chart_of_account_id = $item['chart_of_account_id'];
            $sheetData->name = $item['name'];
            $sheetData->amount = $item['amount'];
            $sheetData->save();
        }
        foreach ($request->charts['fixed_assets'] as $item) {
            $sheetData = new BalanceSheetData();
            $sheetData->client_id = $client->id;
            $sheetData->balance_sheet_id = $sheet->id;
            $sheetData->chart_of_account_id = $item['chart_of_account_id'];
            $sheetData->name = $item['name'];
            $sheetData->amount = $item['amount'];
            $sheetData->save();
        }
        foreach ($request->charts['current_liabilities'] as $item) {
            $sheetData = new BalanceSheetData();
            $sheetData->client_id = $client->id;
            $sheetData->balance_sheet_id = $sheet->id;
            $sheetData->chart_of_account_id = $item['chart_of_account_id'];
            $sheetData->name = $item['name'];
            $sheetData->amount = $item['amount'];
            $sheetData->save();
        }
        foreach ($request->charts['long_term_liabilities'] as $item) {
            $sheetData = new BalanceSheetData();
            $sheetData->client_id = $client->id;
            $sheetData->balance_sheet_id = $sheet->id;
            $sheetData->chart_of_account_id = $item['chart_of_account_id'];
            $sheetData->name = $item['name'];
            $sheetData->amount = $item['amount'];
            $sheetData->save();
        }
        foreach ($request->charts['equity'] as $item) {
            $sheetData = new BalanceSheetData();
            $sheetData->client_id = $client->id;
            $sheetData->balance_sheet_id = $sheet->id;
            $sheetData->chart_of_account_id = $item['chart_of_account_id'];
            $sheetData->name = $item['name'];
            $sheetData->amount = $item['amount'];
            $sheetData->save();
        }

        activity()
            ->performedOn($client)
            ->log('Create Balance Sheet');
        return redirect()->route('clients.balance_sheets.index', [$client->id])->with('success', 'Balance Sheet created successfully.');

    }

    /**
     * Display the specified resource.
     *
     * @param BalanceSheet $sheet
     * @return \Illuminate\Http\Response
     */
    public function show(BalanceSheet $sheet)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param BalanceSheet $sheet
     * @return \Inertia\Response
     */
    public function edit(BalanceSheet $sheet)
    {
        $client = $sheet->client;
        $currentAssets = ChartOfAccount::whereIn('account_type', ['current_asset', 'cash', 'bank', 'stock','accounts_receivable'])->get();
        $otherAssets = ChartOfAccount::whereIn('account_type', ['other_asset'])->get();
        $otherCurrentAssets = ChartOfAccount::whereIn('account_type', ['other_current_asset'])->get();
        $fixedAssets = ChartOfAccount::whereIn('account_type', ['fixed_asset', 'non_current_asset', 'property_plant_equipment', 'investment_property', 'right_of_use_asset', 'intangible_asset'])->get();
        $currentLiabilities = ChartOfAccount::whereIn('account_type', ['current_liability', 'income_tax', 'credit_card','accounts_payable'])->get();
        $longTermLiabilities = ChartOfAccount::whereIn('account_type', ['long_term_liability', 'other_liability', 'finance_lease_liability', 'other_long_term_liability', 'deferred_income_tax'])->get();
        $equity = ChartOfAccount::whereIn('account_type', ['retained_earning', 'capital_contribution', 'equity', 'reserve'])->get();
        $chartData = [
            'current_assets' => [],
            'other_assets' => [],
            'other_current_assets' => [],
            'fixed_assets' => [],
            'current_liabilities' => [],
            'long_term_liabilities' => [],
            'equity' => [],
        ];
        foreach ($currentAssets as $key) {
            $chartData['current_assets'][] = [
                'name' => $key->name,
                'chart_of_account_id' => $key->id,
                'id' => $sheet->data->where('chart_of_account_id', $key->id)->first()->id ?? '',
                'amount' => $sheet->data->where('chart_of_account_id', $key->id)->first()->amount ?? '',
            ];
        }
        foreach ($otherCurrentAssets as $key) {
            $chartData['other_current_assets'][] = [
                'name' => $key->name,
                'chart_of_account_id' => $key->id,
                'id' => $sheet->data->where('chart_of_account_id', $key->id)->first()->id ?? '',
                'amount' => $sheet->data->where('chart_of_account_id', $key->id)->first()->amount ?? '',
            ];
        }
        foreach ($otherAssets as $key) {
            $chartData['other_assets'][] = [
                'name' => $key->name,
                'chart_of_account_id' => $key->id,
                'id' => $sheet->data->where('chart_of_account_id', $key->id)->first()->id ?? '',
                'amount' => $sheet->data->where('chart_of_account_id', $key->id)->first()->amount ?? '',
            ];
        }
        foreach ($fixedAssets as $key) {
            $chartData['fixed_assets'][] = [
                'name' => $key->name,
                'chart_of_account_id' => $key->id,
                'id' => $sheet->data->where('chart_of_account_id', $key->id)->first()->id ?? '',
                'amount' => $sheet->data->where('chart_of_account_id', $key->id)->first()->amount ?? '',
            ];
        }
        foreach ($currentLiabilities as $key) {
            $chartData['current_liabilities'][] = [
                'name' => $key->name,
                'chart_of_account_id' => $key->id,
                'id' => $sheet->data->where('chart_of_account_id', $key->id)->first()->id ?? '',
                'amount' => $sheet->data->where('chart_of_account_id', $key->id)->first()->amount ?? '',
            ];
        }
        foreach ($longTermLiabilities as $key) {
            $chartData['long_term_liabilities'][] = [
                'name' => $key->name,
                'chart_of_account_id' => $key->id,
                'id' => $sheet->data->where('chart_of_account_id', $key->id)->first()->id ?? '',
                'amount' => $sheet->data->where('chart_of_account_id', $key->id)->first()->amount ?? '',
            ];
        }
        foreach ($equity as $key) {
            $chartData['equity'][] = [
                'name' => $key->name,
                'chart_of_account_id' => $key->id,
                'id' => $sheet->data->where('chart_of_account_id', $key->id)->first()->id ?? '',
                'amount' => $sheet->data->where('chart_of_account_id', $key->id)->first()->amount ?? '',
            ];

        }
        $sheet->charts = $chartData;
        return Inertia::render('Clients/BalanceSheets/Edit', [
            'client' => $client,
            'sheet' => $sheet,
            'currentAssets' => $currentAssets,
            'otherAssets' => $otherAssets,
            'otherCurrentAssets' => $otherCurrentAssets,
            'fixedAssets' => $fixedAssets,
            'currentLiabilities' => $currentLiabilities,
            'longTermLiabilities' => $longTermLiabilities,
            'equity' => $equity,
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param BalanceSheet $sheet
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, BalanceSheet $sheet)
    {

        $client = $sheet->client;
        $request->validate([
            'year' => ['required'],
        ]);
        $sheet->year = $request->year;
        $sheet->as_at_date = $request->as_at_date;
        $sheet->total_assets = $request->total_assets;
        $sheet->total_equity = $request->total_equity;
        $sheet->total_equity_liabilities = $request->total_equity_liabilities;
        $sheet->total_liabilities = $request->total_liabilities;
        $sheet->total_working_capital = $request->total_working_capital;
        $sheet->total_current_assets = $request->total_current_assets;
        $sheet->total_current_liabilities = $request->total_current_liabilities;
        $sheet->total_long_term_liabilities = $request->total_long_term_liabilities;
        $sheet->total_other_current_assets = $request->total_other_current_assets;
        $sheet->total_other_assets = $request->total_other_assets;
        $sheet->total_fixed_assets = $request->total_fixed_assets;
        $sheet->description = $request->description;
        $sheet->save();
        //delete current sheet data
        $sheet->data->each->delete();
        //save the charts
        foreach ($request->charts['current_assets'] as $item) {
            $sheetData = new BalanceSheetData();
            $sheetData->client_id = $client->id;
            $sheetData->balance_sheet_id = $sheet->id;
            $sheetData->chart_of_account_id = $item['chart_of_account_id'];
            $sheetData->name = $item['name'];
            $sheetData->amount = $item['amount'];
            $sheetData->save();
        }
        foreach ($request->charts['other_assets'] as $item) {
            $sheetData = new BalanceSheetData();
            $sheetData->client_id = $client->id;
            $sheetData->balance_sheet_id = $sheet->id;
            $sheetData->chart_of_account_id = $item['chart_of_account_id'];
            $sheetData->name = $item['name'];
            $sheetData->amount = $item['amount'];
            $sheetData->save();
        }
        foreach ($request->charts['other_current_assets'] as $item) {
            $sheetData = new BalanceSheetData();
            $sheetData->client_id = $client->id;
            $sheetData->balance_sheet_id = $sheet->id;
            $sheetData->chart_of_account_id = $item['chart_of_account_id'];
            $sheetData->name = $item['name'];
            $sheetData->amount = $item['amount'];
            $sheetData->save();
        }
        foreach ($request->charts['fixed_assets'] as $item) {
            $sheetData = new BalanceSheetData();
            $sheetData->client_id = $client->id;
            $sheetData->balance_sheet_id = $sheet->id;
            $sheetData->chart_of_account_id = $item['chart_of_account_id'];
            $sheetData->name = $item['name'];
            $sheetData->amount = $item['amount'];
            $sheetData->save();
        }
        foreach ($request->charts['current_liabilities'] as $item) {
            $sheetData = new BalanceSheetData();
            $sheetData->client_id = $client->id;
            $sheetData->balance_sheet_id = $sheet->id;
            $sheetData->chart_of_account_id = $item['chart_of_account_id'];
            $sheetData->name = $item['name'];
            $sheetData->amount = $item['amount'];
            $sheetData->save();
        }
        foreach ($request->charts['long_term_liabilities'] as $item) {
            $sheetData = new BalanceSheetData();
            $sheetData->client_id = $client->id;
            $sheetData->balance_sheet_id = $sheet->id;
            $sheetData->chart_of_account_id = $item['chart_of_account_id'];
            $sheetData->name = $item['name'];
            $sheetData->amount = $item['amount'];
            $sheetData->save();
        }
        foreach ($request->charts['equity'] as $item) {
            $sheetData = new BalanceSheetData();
            $sheetData->client_id = $client->id;
            $sheetData->balance_sheet_id = $sheet->id;
            $sheetData->chart_of_account_id = $item['chart_of_account_id'];
            $sheetData->name = $item['name'];
            $sheetData->amount = $item['amount'];
            $sheetData->save();
        }
        activity()
            ->performedOn($client)
            ->log('Update Balance Sheet');
        return redirect()->route('clients.balance_sheets.index', [$client->id])->with('success', 'Balance Sheet updated successfully.');

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param BalanceSheet $sheet
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(BalanceSheet $sheet)
    {
        $sheet->data->each->delete();
        $sheet->delete();
        activity()
            ->performedOn($sheet)
            ->log('Delete Balance Sheet');
        return redirect()->route('clients.balance_sheets.index', [$sheet->client_id])->with('success', 'Client sheet deleted successfully.');

    }

    public function summary(Client $client)
    {
        $sheets = BalanceSheet::where('client_id', $client->id)
            ->orderBy('year')
            ->groupBy('year')
            ->get();
        return Inertia::render('Clients/BalanceSheets/Summary', [
            'client' => $client,
            'sheets' => $sheets,
        ]);
    }
}
