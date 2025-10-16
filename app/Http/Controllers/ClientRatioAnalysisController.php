<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Client;
use App\Models\Setting;
use App\Models\BalanceSheet;
use Illuminate\Http\Request;
use App\Models\RatioAnalysis;
use App\Models\ChartOfAccount;
use App\Models\IncomeStatement;
use App\Models\BalanceSheetData;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class ClientRatioAnalysisController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(['permission:clients.ratio_analysis.index'])->only(['index', 'show']);
        $this->middleware(['permission:clients.ratio_analysis.create'])->only(['create', 'store']);
        $this->middleware(['permission:clients.ratio_analysis.update'])->only(['edit', 'update']);
        $this->middleware(['permission:clients.ratio_analysis.destroy'])->only(['destroy']);
    }

    public function index(Client $client)
    {

        $client = $client->load(['industryType']);
        //check if has a industry type
        if (empty($client->industryType)) {
            return back()->with('error', 'Please select an industry type for this client. Go to client page to update client industry type.');
        }
        $sheets = BalanceSheet::where('client_id', $client->id)
            ->orderBy('year')
            ->groupBy('year')
            ->get();
        $data = [
        ];
        foreach ($sheets as $sheet) {
          Log::info($sheet->total_fixed_assets);
            $incomeStatement = IncomeStatement::where('year', $sheet->year)->first();
            if (empty($incomeStatement)) {
                return redirect()->back()->with('error', 'No income statement for year ' . $sheet->year);
            }
            $turnoverGrowth = 0;
            if ($statement = IncomeStatement::where('year', $sheet->year - 1)->first()) {
                $turnoverGrowth = ($statement->total_sales) ? round(($incomeStatement->total_sales / $statement->total_sales - 1) * 100) : 0;
            }
            $workingCapitalTotalAssets = round(($sheet->total_current_assets + $sheet->total_other_current_assets - $sheet->total_current_liabilities) / ($sheet->total_assets), 2);
            $retainedEarningsTotalAssets =($sheet->total_assets>0)? round(($sheet->total_retained_earnings) / ($sheet->total_assets), 2):0;
            $ebitTotalAssets =( $sheet->total_assets>0)? round($incomeStatement->total_operating_profit / $sheet->total_assets, 2):0;
            $equityTotalLiabilities = ($sheet->total_liabilities>0)?round($sheet->total_equity / $sheet->total_liabilities, 2):0;
            $salesTotalAssets =($sheet->total_assets>0)? round($incomeStatement->total_sales / $sheet->total_assets, 2):0;
            // dd($sheet->total_fixed_assets);
            $data[] = [
                'year' => $sheet->year,
                'working_capital_total_assets' => $workingCapitalTotalAssets,
                'retained_earnings_total_assets' => $retainedEarningsTotalAssets,
                'ebit_total_assets' => $ebitTotalAssets,
                'equity_total_liabilities' => $equityTotalLiabilities,
                'sales_total_assets' => $salesTotalAssets,
                'z_score' => round(($workingCapitalTotalAssets * 1.2) + ($retainedEarningsTotalAssets * 1.4) + ($ebitTotalAssets * 3.3) + ($equityTotalLiabilities * 0.6) + ($salesTotalAssets * 1), 2),
                'z_second_score' => round(($workingCapitalTotalAssets * 6.56) + ($retainedEarningsTotalAssets * 3.26) + ($ebitTotalAssets * 6.72) + ($equityTotalLiabilities * 1.05) + ($salesTotalAssets * 0), 2),
                'z_score_thresholds_healthy' => 0,
                'current_ratio' => ($sheet->total_current_liabilities>0) ? round($sheet->total_current_assets / $sheet->total_current_liabilities, 2) : 2,
                'quick_ratio' => ($sheet->total_current_liabilities>0) ? round(($sheet->total_current_assets - $incomeStatement->total_stock) / $sheet->total_current_liabilities, 2) : 2,
                'debtor_days' => round($sheet->total_accounts_receivable / $incomeStatement->total_sales, 2),
                'creditor_days' => round($sheet->total_accounts_payable / $incomeStatement->total_sales, 2),
                'working_capital' => ($sheet->total_working_capital>0) ? round($incomeStatement->total_sales / ($sheet->total_working_capital), 2) : 0,
                'turnover_growth' => $turnoverGrowth,
                'gross_profit' => ($incomeStatement->total_sales>0)?round(($incomeStatement->total_gross_margin / $incomeStatement->total_sales) * 100, 2):0,
                'operating_profit_margin' => ($incomeStatement->total_sales>0)?round(($incomeStatement->total_operating_profit / $incomeStatement->total_sales) * 100, 2):0,
                'net_profit_margin' => ($incomeStatement->total_sales>0)?round(($incomeStatement->net_profit / $incomeStatement->total_sales) * 100, 2):0,
                'return_on_equity' => ($sheet->total_equity>0)?round(($incomeStatement->net_profit / $sheet->total_equity) * 100, 2):0,
                'return_on_assets' => ($sheet->total_assets>0)?round(($incomeStatement->net_profit / $sheet->total_assets), 2):0,
                'return_on_investment' => round(($incomeStatement->total_operating_profit / ($sheet->total_assets + $sheet->total_long_term_liabilities)), 2),
                'gearing_ratio' => ($sheet->total_equity)?round($sheet->total_liabilities / $sheet->total_equity, 2):0,
                'long_term_debt' => ($sheet->total_equity)?round($sheet->total_long_term_liabilities / $sheet->total_equity, 2):0,
                'tangible_net_worth' => ($sheet->total_tangible_net_worth>0)?round($sheet->total_liabilities / $sheet->total_tangible_net_worth, 2):0,
                'total_assets' => ($sheet->total_assets>0)?round($sheet->total_equity / $sheet->total_assets, 2):0,
                'solvency' => ($sheet->total_liabilities>0) ? round($sheet->total_assets / $sheet->total_liabilities, 2) : 'n/a',
                'interest_cover' => ($incomeStatement->total_net_finance_costs>0) ? round($incomeStatement->total_operating_profit / $incomeStatement->total_net_finance_costs, 2) : 0,
                'gross_interest_debts' => ($sheet->total_long_term_liabilities>0) ? round(($incomeStatement->total_cost_of_goods_sold_depreciation + $incomeStatement->total_depreciation_property_plant_equipment + $incomeStatement->total_amortisation) / $sheet->total_long_term_liabilities, 2) : 0,
                'total_assets_turnover' => round($incomeStatement->total_sales / $sheet->total_assets, 2),
                'fixed_assets_turn_over' => $sheet->total_fixed_assets != 0 ? round($incomeStatement->total_sales / $sheet->total_fixed_assets, 2) : 0,
            ];
        }
        // dd($data);

        $ratio = RatioAnalysis::where('client_id', $client->id)->first();
        if (empty($ratio)) {
            $ratio = new RatioAnalysis();
            $ratio->client_id = $client->id;
            $ratio->created_by_id = Auth::id();
        }
        $count = $sheets->count();
        //weighting setting is in asc year order
        $weighting = explode(',', Setting::where('setting_key', 'year_weighting')->first()->setting_value);
        //save the weighted averages
        $skip = count($weighting) - $count;
        if ($skip) {
            //unset those that we are skipping
            for ($i = 0; $i < $skip; $i++) {
                unset($data[$i]);
            }
        }
        $weightedWorkingCapitalTotalAssets = 0;
        $weightedRetainedEarningsTotalAssets = 0;
        $weightedEbitTotalAssets = 0;
        $weightedEquityTotalLiabilities = 0;
        $weightedSalesTotalAssets = 0;
        $weightedZScore = 0;
        $weightedZSecondScore = 0;
        $weightedCurrentRatio = 0;
        $weightedQuickRatio = 0;
        $weightedDebtorDays = 0;
        $weightedCreditorDays = 0;
        $weightedWorkingCapital = 0;
        $weightedTurnoverGrowth = 0;
        $weightedGrossProfit = 0;
        $weightedOperatingProfitMargin = 0;
        $weightedNetProfitMargin = 0;
        $weightedReturnOnEquity = 0;
        $weightedReturnOnAssets = 0;
        $weightedReturnOnInvestment = 0;
        $weightedGearingRatio = 0;
        $weightedLongTermDebt = 0;
        $weightedTangibleNetWorth = 0;
        $weightedTotalAssets = 0;
        $weightedSolvency = 0;
        $weightedInterestCover = 0;
        $weightedGrossInterestDebts = 0;
        $weightedTotalAssetsTurnOver = 0;
        $weightedFixedAssetsTurnOver = 0;
        $i = 0;
        foreach ($data as $key) {
            $weightedWorkingCapitalTotalAssets += $key['working_capital_total_assets'] * $weighting[$i] / 100;
            $weightedRetainedEarningsTotalAssets += $key['retained_earnings_total_assets'] * $weighting[$i] / 100;
            $weightedEbitTotalAssets += $key['ebit_total_assets'] * $weighting[$i] / 100;
            $weightedEquityTotalLiabilities += $key['equity_total_liabilities'] * $weighting[$i] / 100;
            $weightedSalesTotalAssets += $key['sales_total_assets'] * $weighting[$i] / 100;
            $weightedZScore += (($key['working_capital_total_assets'] * $weighting[$i] / 100) * 1.2) + (($key['retained_earnings_total_assets'] * $weighting[$i] / 100) * 1.4) + (($key['ebit_total_assets'] * $weighting[$i] / 100) * 3.3) + (($key['equity_total_liabilities'] * $weighting[$i] / 100) * 0.6) + (($key['sales_total_assets'] * $weighting[$i] / 100) * 1);
            $weightedZSecondScore += (($key['working_capital_total_assets'] * $weighting[$i] / 100) * 6.56) + (($key['retained_earnings_total_assets'] * $weighting[$i] / 100) * 3.26) + (($key['ebit_total_assets'] * $weighting[$i] / 100) * 6.72) + (($key['equity_total_liabilities'] * $weighting[$i] / 100) * 1.05) + (($key['sales_total_assets'] * $weighting[$i] / 100) * 0);;
            $weightedCurrentRatio += $key['current_ratio'] * $weighting[$i] / 100;
            $weightedQuickRatio += $key['quick_ratio'] * $weighting[$i] / 100;
            $weightedDebtorDays += $key['debtor_days'] * $weighting[$i] / 100;
            $weightedCreditorDays += $key['creditor_days'] * $weighting[$i] / 100;
            $weightedWorkingCapital += $key['working_capital'] * $weighting[$i] / 100;
            $weightedTurnoverGrowth += $key['turnover_growth'] * $weighting[$i] / 100;
            $weightedGrossProfit += $key['gross_profit'] * $weighting[$i] / 100;
            $weightedOperatingProfitMargin += $key['operating_profit_margin'] * $weighting[$i] / 100;
            $weightedNetProfitMargin += $key['net_profit_margin'] * $weighting[$i] / 100;
            $weightedReturnOnEquity += $key['return_on_equity'] * $weighting[$i] / 100;
            $weightedReturnOnAssets += $key['return_on_assets'] * $weighting[$i] / 100;
            $weightedReturnOnInvestment += $key['return_on_investment'] * $weighting[$i] / 100;
            $weightedGearingRatio += $key['gearing_ratio'] * $weighting[$i] / 100;
            $weightedLongTermDebt += $key['long_term_debt'] * $weighting[$i] / 100;
            $weightedTangibleNetWorth += $key['tangible_net_worth'] * $weighting[$i] / 100;
            $weightedTotalAssets += $key['total_assets'] * $weighting[$i] / 100;
            $weightedSolvency += $key['solvency'] * $weighting[$i] / 100;
            $weightedInterestCover += $key['interest_cover'] * $weighting[$i] / 100;
            $weightedGrossInterestDebts += $key['gross_interest_debts'] * $weighting[$i] / 100;
            $weightedTotalAssetsTurnOver += $key['total_assets_turnover'] * $weighting[$i] / 100;
            $weightedFixedAssetsTurnOver += $key['fixed_assets_turn_over'] * $weighting[$i] / 100;
            $i++;
        }
        // dd($weightedZScore);
        //todo:move the save code to save method
        $ratio->working_capital_total_assets = round($weightedWorkingCapitalTotalAssets, 2);
        $ratio->retained_earnings_total_assets = round($weightedRetainedEarningsTotalAssets, 2);
        $ratio->ebit_total_assets = round($weightedEbitTotalAssets, 2);
        $ratio->equity_total_liabilities = round($weightedEquityTotalLiabilities, 2);
        $ratio->sales_total_assets = round($weightedSalesTotalAssets, 2);
        $ratio->z_score = round($weightedZScore, 2);
        $ratio->z_second_score = round($weightedZSecondScore, 2);
        $ratio->current_ratio = round($weightedCurrentRatio, 2);
        $ratio->quick_ratio = round($weightedQuickRatio, 2);
        $ratio->debtor_days = round($weightedDebtorDays, 2);
        $ratio->creditor_days = round($weightedCreditorDays, 2);
        $ratio->working_capital = round($weightedWorkingCapital, 2);
        $ratio->turnover_growth = round($weightedTurnoverGrowth, 2);
        $ratio->gross_profit = round($weightedGrossProfit, 2);
        $ratio->operating_profit_margin = round($weightedOperatingProfitMargin, 2);
        $ratio->net_profit_margin = round($weightedNetProfitMargin, 2);
        $ratio->return_on_equity = round($weightedReturnOnEquity, 2);
        $ratio->return_on_assets = round($weightedReturnOnAssets, 2);
        $ratio->return_on_investment = round($weightedReturnOnInvestment, 2);
        $ratio->gearing_ratio = round($weightedGearingRatio, 2);
        $ratio->long_term_debt = round($weightedLongTermDebt, 2);
        $ratio->tangible_net_worth = round($weightedTangibleNetWorth, 2);
        $ratio->total_assets = round($weightedTotalAssets, 2);
        $ratio->solvency = round($weightedSolvency, 2);
        $ratio->interest_cover = round($weightedInterestCover, 2);
        $ratio->gross_interest_debts = round($weightedGrossInterestDebts, 2);
        $ratio->total_assets_turnover = round($weightedTotalAssetsTurnOver, 2);
        $ratio->fixed_assets_turn_over = round($weightedFixedAssetsTurnOver, 2);
        $ratio->save();
        $ratio->refresh();
        return Inertia::render('Clients/RatioAnalysis/Index', [
            'client' => $client,
            'sheets' => $sheets,
            'ratio' => $ratio,
            'data' => $data,
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

        $ratio = RatioAnalysis::where('client_id', $client->id)->first();
        $ratio->bankruptcy_prediction_ratios = $request->bankruptcy_prediction_ratios;
        $ratio->z_score_comment = $request->z_score_comment;
        $ratio->z_second_score_comment = $request->z_second_score_comment;
        $ratio->current_ratio_comment = $request->current_ratio_comment;
        $ratio->quick_ratio_comment = $request->quick_ratio_comment;
        $ratio->debtor_days_comment = $request->debtor_days_comment;
        $ratio->creditor_days_comment = $request->creditor_days_comment;
        $ratio->working_capital_comment = $request->working_capital_comment;
        $ratio->turnover_growth_comment = $request->turnover_growth_comment;
        $ratio->gross_profit_comment = $request->gross_profit_comment;
        $ratio->operating_profit_margin_comment = $request->operating_profit_margin_comment;
        $ratio->net_profit_margin_comment = $request->net_profit_margin_comment;
        $ratio->return_on_equity_comment = $request->return_on_equity_comment;
        $ratio->return_on_assets_comment = $request->return_on_assets_comment;
        $ratio->return_on_investment_comment = $request->return_on_investment_comment;
        $ratio->gearing_ratio_comment = $request->gearing_ratio_comment;
        $ratio->long_term_debt_comment = $request->long_term_debt_comment;
        $ratio->tangible_net_worth_comment = $request->tangible_net_worth_comment;
        $ratio->total_assets_comment = $request->total_assets_comment;
        $ratio->solvency_comment = $request->solvency_comment;
        $ratio->interest_cover_comment = $request->interest_cover_comment;
        $ratio->gross_interest_debts_comment = $request->gross_interest_debts_comment;
        $ratio->total_assets_turnover_comment = $request->total_assets_turnover_comment;
        $ratio->fixed_assets_turn_over_comment = $request->fixed_assets_turn_over_comment;
        $ratio->save();

        activity()
            ->performedOn($client)
            ->log('Update Ratio Analysis');
        return redirect()->route('clients.ratio_analysis.index', [$client->id])->with('success', 'Saved successfully.');

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
        $currentAssets = ChartOfAccount::whereIn('account_type', ['current_asset', 'cash', 'bank', 'stock'])->get();
        $otherAssets = ChartOfAccount::whereIn('account_type', ['other_asset'])->get();
        $otherCurrentAssets = ChartOfAccount::whereIn('account_type', ['other_current_asset'])->get();
        $fixedAssets = ChartOfAccount::whereIn('account_type', ['fixed_asset', 'non_current_asset', 'property_plant_equipment', 'investment_property', 'right_of_use_asset', 'intangible_asset'])->get();
        $currentLiabilities = ChartOfAccount::whereIn('account_type', ['current_liability', 'income_tax', 'credit_card'])->get();
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
        $sheet->total_liabilities = $request->total_liabilities;
        $sheet->total_equity = $request->total_equity;
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
            $sheetData->ratio_analysis_id = $sheet->id;
            $sheetData->chart_of_account_id = $item['chart_of_account_id'];
            $sheetData->name = $item['name'];
            $sheetData->amount = $item['amount'];
            $sheetData->save();
        }
        foreach ($request->charts['other_assets'] as $item) {
            $sheetData = new BalanceSheetData();
            $sheetData->client_id = $client->id;
            $sheetData->ratio_analysis_id = $sheet->id;
            $sheetData->chart_of_account_id = $item['chart_of_account_id'];
            $sheetData->name = $item['name'];
            $sheetData->amount = $item['amount'];
            $sheetData->save();
        }
        foreach ($request->charts['other_current_assets'] as $item) {
            $sheetData = new BalanceSheetData();
            $sheetData->client_id = $client->id;
            $sheetData->ratio_analysis_id = $sheet->id;
            $sheetData->chart_of_account_id = $item['chart_of_account_id'];
            $sheetData->name = $item['name'];
            $sheetData->amount = $item['amount'];
            $sheetData->save();
        }
        foreach ($request->charts['fixed_assets'] as $item) {
            $sheetData = new BalanceSheetData();
            $sheetData->client_id = $client->id;
            $sheetData->ratio_analysis_id = $sheet->id;
            $sheetData->chart_of_account_id = $item['chart_of_account_id'];
            $sheetData->name = $item['name'];
            $sheetData->amount = $item['amount'];
            $sheetData->save();
        }
        foreach ($request->charts['current_liabilities'] as $item) {
            $sheetData = new BalanceSheetData();
            $sheetData->client_id = $client->id;
            $sheetData->ratio_analysis_id = $sheet->id;
            $sheetData->chart_of_account_id = $item['chart_of_account_id'];
            $sheetData->name = $item['name'];
            $sheetData->amount = $item['amount'];
            $sheetData->save();
        }
        foreach ($request->charts['long_term_liabilities'] as $item) {
            $sheetData = new BalanceSheetData();
            $sheetData->client_id = $client->id;
            $sheetData->ratio_analysis_id = $sheet->id;
            $sheetData->chart_of_account_id = $item['chart_of_account_id'];
            $sheetData->name = $item['name'];
            $sheetData->amount = $item['amount'];
            $sheetData->save();
        }
        foreach ($request->charts['equity'] as $item) {
            $sheetData = new BalanceSheetData();
            $sheetData->client_id = $client->id;
            $sheetData->ratio_analysis_id = $sheet->id;
            $sheetData->chart_of_account_id = $item['chart_of_account_id'];
            $sheetData->name = $item['name'];
            $sheetData->amount = $item['amount'];
            $sheetData->save();
        }
        activity()
            ->performedOn($client)
            ->log('Update Balance Sheet');
        return redirect()->route('clients.ratio_analysiss.index', [$client->id])->with('success', 'Balance Sheet updated successfully.');

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
        return redirect()->route('clients.ratio_analysiss.index', [$sheet->client_id])->with('success', 'Client sheet deleted successfully.');

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
