<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\Client;
use App\Models\IncomeStatement;
use App\Models\IncomeStatementData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ClientIncomeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(['permission:clients.income_statement.index'])->only(['index', 'show']);
        $this->middleware(['permission:clients.income_statement.create'])->only(['create', 'store']);
        $this->middleware(['permission:clients.income_statement.update'])->only(['edit', 'update']);
        $this->middleware(['permission:clients.income_statement.destroy'])->only(['destroy']);
    }

    public function index(Client $client)
    {
        $statements = IncomeStatement::where('client_id', $client->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        return Inertia::render('Clients/IncomeStatements/Index', [
            'client' => $client,
            'statements' => $statements,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Inertia\Response
     */
    public function create(Client $client)
    {
        $sales = ChartOfAccount::where('account_type', 'income')->get();
        $costsOfGoodsSold = ChartOfAccount::whereIn('account_type', ['cost_of_goods_sold','cost_of_goods_sold_depreciation'])->get();
        $expenses = ChartOfAccount::whereIn('account_type', ['expense','cost_of_goods_sold_depreciation','depreciation_property_plant_equipment','depreciation_right_of_use_assets','depreciation_investment_property','amortisation_intangible_assets','short_term_leases','income_tax_expense'])->get();
        $otherExpenses = ChartOfAccount::whereIn('account_type', ['other_expense'])->get();
        $otherIncome = ChartOfAccount::whereIn('account_type', ['other_income'])->get();
        $incomeTax = ChartOfAccount::whereIn('account_type', ['income_tax'])->get();
        $netFinanceCosts = ChartOfAccount::whereIn('account_type', ['net_finance_costs_banks','net_finance_costs_finance_leases'])->get();
        return Inertia::render('Clients/IncomeStatements/Create', [
            'client' => $client,
            'sales' => $sales,
            'costsOfGoodsSold' => $costsOfGoodsSold,
            'otherExpenses' => $otherExpenses,
            'expenses' => $expenses,
            'otherIncome' => $otherIncome,
            'incomeTax' => $incomeTax,
            'netFinanceCosts' => $netFinanceCosts,
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
        $statement = new IncomeStatement();
        $statement->created_by_id = Auth::id();
        $statement->client_id = $client->id;
        $statement->year = $request->year;
        $statement->as_at_date = $request->as_at_date;
        $statement->reporting_month = $request->reporting_month;
        $statement->months_in_year = $request->months_in_year;
        $statement->audit_status = $request->audit_status;
        $statement->real_annual_inflation_rate = $request->real_annual_inflation_rate;
        $statement->nominal_annual_inflation_rate = $request->nominal_annual_inflation_rate;
        $statement->total_sales = $request->total_sales;
        $statement->total_operating_expenses = $request->total_operating_expenses;
        $statement->total_gross_margin = $request->total_gross_margin;
        $statement->total_other_income = $request->total_other_income;
        $statement->total_other_expenses = $request->total_other_expenses;
        $statement->total_income_before_tax = $request->total_income_before_tax;
        $statement->total_income_tax = $request->total_income_tax;
        $statement->total_cost_of_goods_sold = $request->total_cost_of_goods_sold;
        $statement->total_operating_profit = $request->total_operating_profit;
        $statement->total_net_finance_costs = $request->total_net_finance_costs;
        $statement->net_profit = $request->net_profit;
        $statement->description = $request->description;
        $statement->save();
        //save the charts
        foreach ($request->charts['sales'] as $item) {
            $statementData = new IncomeStatementData();
            $statementData->client_id = $client->id;
            $statementData->income_statement_id = $statement->id;
            $statementData->chart_of_account_id = $item['chart_of_account_id'];
            $statementData->name = $item['name'];
            $statementData->amount = $item['amount'];
            $statementData->save();
        }
        foreach ($request->charts['cost_of_goods_sold'] as $item) {
            $statementData = new IncomeStatementData();
            $statementData->client_id = $client->id;
            $statementData->income_statement_id = $statement->id;
            $statementData->chart_of_account_id = $item['chart_of_account_id'];
            $statementData->name = $item['name'];
            $statementData->amount = $item['amount'];
            $statementData->save();
        }
        foreach ($request->charts['operating_expenses'] as $item) {
            $statementData = new IncomeStatementData();
            $statementData->client_id = $client->id;
            $statementData->income_statement_id = $statement->id;
            $statementData->chart_of_account_id = $item['chart_of_account_id'];
            $statementData->name = $item['name'];
            $statementData->amount = $item['amount'];
            $statementData->save();
        }
        foreach ($request->charts['other_income'] as $item) {
            $statementData = new IncomeStatementData();
            $statementData->client_id = $client->id;
            $statementData->income_statement_id = $statement->id;
            $statementData->chart_of_account_id = $item['chart_of_account_id'];
            $statementData->name = $item['name'];
            $statementData->amount = $item['amount'];
            $statementData->save();
        }
        foreach ($request->charts['other_expenses'] as $item) {
            $statementData = new IncomeStatementData();
            $statementData->client_id = $client->id;
            $statementData->income_statement_id = $statement->id;
            $statementData->chart_of_account_id = $item['chart_of_account_id'];
            $statementData->name = $item['name'];
            $statementData->amount = $item['amount'];
            $statementData->save();
        }
        foreach ($request->charts['income_tax'] as $item) {
            $statementData = new IncomeStatementData();
            $statementData->client_id = $client->id;
            $statementData->income_statement_id = $statement->id;
            $statementData->chart_of_account_id = $item['chart_of_account_id'];
            $statementData->name = $item['name'];
            $statementData->amount = $item['amount'];
            $statementData->save();
        }
        foreach ($request->charts['net_finance_costs'] as $item) {
            $statementData = new IncomeStatementData();
            $statementData->client_id = $client->id;
            $statementData->income_statement_id = $statement->id;
            $statementData->chart_of_account_id = $item['chart_of_account_id'];
            $statementData->name = $item['name'];
            $statementData->amount = $item['amount'];
            $statementData->save();
        }

        activity()
            ->performedOn($client)
            ->log('Create Income Statement');
        return redirect()->route('clients.income_statements.index', [$client->id])->with('success', 'Income Statement created successfully.');

    }

    /**
     * Display the specified resource.
     *
     * @param IncomeStatement $statement
     * @return \Illuminate\Http\Response
     */
    public function show(IncomeStatement $statement)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param IncomeStatement $statement
     * @return \Inertia\Response
     */
    public function edit(IncomeStatement $statement)
    {
        $client = $statement->client;

        $sales = ChartOfAccount::where('account_type', 'income')->get();
        $costsOfGoodsSold = ChartOfAccount::whereIn('account_type', ['cost_of_goods_sold','cost_of_goods_sold_depreciation'])->get();
        $expenses = ChartOfAccount::whereIn('account_type', ['expense','cost_of_goods_sold_depreciation','depreciation_property_plant_equipment','depreciation_right_of_use_assets','depreciation_investment_property','amortisation_intangible_assets','short_term_leases','income_tax_expense'])->get();
        $otherExpenses = ChartOfAccount::whereIn('account_type', ['other_expense'])->get();
        $otherIncome = ChartOfAccount::whereIn('account_type', ['other_income'])->get();
        $incomeTax = ChartOfAccount::whereIn('account_type', ['income_tax'])->get();
        $netFinanceCosts = ChartOfAccount::whereIn('account_type', ['net_finance_costs_banks','net_finance_costs_finance_leases'])->get();
        $chartData = [
            'sales' => [],
            'cost_of_goods_sold' => [],
            'operating_expenses' => [],
            'other_income' => [],
            'other_expenses' => [],
            'income_tax' => [],
            'net_finance_costs' => [],
        ];
        foreach ($sales as $key) {
            $chartData['sales'][] = [
                'name' => $key->name,
                'chart_of_account_id' => $key->id,
                'id' => $statement->data->where('chart_of_account_id', $key->id)->first()->id ?? '',
                'amount' => $statement->data->where('chart_of_account_id', $key->id)->first()->amount ?? '',
            ];
        }
        foreach ($costsOfGoodsSold as $key) {
            $chartData['cost_of_goods_sold'][] = [
                'name' => $key->name,
                'chart_of_account_id' => $key->id,
                'id' => $statement->data->where('chart_of_account_id', $key->id)->first()->id ?? '',
                'amount' => $statement->data->where('chart_of_account_id', $key->id)->first()->amount ?? '',
            ];
        }
        foreach ($expenses as $key) {
            $chartData['operating_expenses'][] = [
                'name' => $key->name,
                'chart_of_account_id' => $key->id,
                'id' => $statement->data->where('chart_of_account_id', $key->id)->first()->id ?? '',
                'amount' => $statement->data->where('chart_of_account_id', $key->id)->first()->amount ?? '',
            ];
        }
        foreach ($otherExpenses as $key) {
            $chartData['other_expenses'][] = [
                'name' => $key->name,
                'chart_of_account_id' => $key->id,
                'id' => $statement->data->where('chart_of_account_id', $key->id)->first()->id ?? '',
                'amount' => $statement->data->where('chart_of_account_id', $key->id)->first()->amount ?? '',
            ];
        }
        foreach ($otherIncome as $key) {
            $chartData['other_income'][] = [
                'name' => $key->name,
                'chart_of_account_id' => $key->id,
                'id' => $statement->data->where('chart_of_account_id', $key->id)->first()->id ?? '',
                'amount' => $statement->data->where('chart_of_account_id', $key->id)->first()->amount ?? '',
            ];
        }
        foreach ($incomeTax as $key) {
            $chartData['income_tax'][] = [
                'name' => $key->name,
                'chart_of_account_id' => $key->id,
                'id' => $statement->data->where('chart_of_account_id', $key->id)->first()->id ?? '',
                'amount' => $statement->data->where('chart_of_account_id', $key->id)->first()->amount ?? '',
            ];

        }
        foreach ($netFinanceCosts as $key) {
            $chartData['net_finance_costs'][] = [
                'name' => $key->name,
                'chart_of_account_id' => $key->id,
                'id' => $statement->data->where('chart_of_account_id', $key->id)->first()->id ?? '',
                'amount' => $statement->data->where('chart_of_account_id', $key->id)->first()->amount ?? '',
            ];

        }
        $statement->charts = $chartData;
        return Inertia::render('Clients/IncomeStatements/Edit', [
            'client' => $client,
            'statement' => $statement,
            'sales' => $sales,
            'costsOfGoodsSold' => $costsOfGoodsSold,
            'otherExpenses' => $otherExpenses,
            'expenses' => $expenses,
            'otherIncome' => $otherIncome,
            'incomeTax' => $incomeTax,
            'netFinanceCosts' => $netFinanceCosts,
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param IncomeStatement $statement
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, IncomeStatement $statement)
    {

        $client = $statement->client;
        $request->validate([
            'year' => ['required'],
        ]);
        $statement->year = $request->year;
        $statement->as_at_date = $request->as_at_date;
        $statement->reporting_month = $request->reporting_month;
        $statement->months_in_year = $request->months_in_year;
        $statement->audit_status = $request->audit_status;
        $statement->real_annual_inflation_rate = $request->real_annual_inflation_rate;
        $statement->nominal_annual_inflation_rate = $request->nominal_annual_inflation_rate;
        $statement->total_sales = $request->total_sales;
        $statement->total_operating_expenses = $request->total_operating_expenses;
        $statement->total_gross_margin = $request->total_gross_margin;
        $statement->total_other_income = $request->total_other_income;
        $statement->total_other_expenses = $request->total_other_expenses;
        $statement->total_income_before_tax = $request->total_income_before_tax;
        $statement->total_income_tax = $request->total_income_tax;
        $statement->total_cost_of_goods_sold = $request->total_cost_of_goods_sold;
        $statement->total_operating_profit = $request->total_operating_profit;
        $statement->total_net_finance_costs = $request->total_net_finance_costs;
        $statement->net_profit = $request->net_profit;
        $statement->description = $request->description;
        $statement->save();
        //delete current linked data
        $statement->data->each->delete();
        //save the charts
        foreach ($request->charts['sales'] as $item) {
            $statementData = new IncomeStatementData();
            $statementData->client_id = $client->id;
            $statementData->income_statement_id = $statement->id;
            $statementData->chart_of_account_id = $item['chart_of_account_id'];
            $statementData->name = $item['name'];
            $statementData->amount = $item['amount'];
            $statementData->save();
        }
        foreach ($request->charts['cost_of_goods_sold'] as $item) {
            $statementData = new IncomeStatementData();
            $statementData->client_id = $client->id;
            $statementData->income_statement_id = $statement->id;
            $statementData->chart_of_account_id = $item['chart_of_account_id'];
            $statementData->name = $item['name'];
            $statementData->amount = $item['amount'];
            $statementData->save();
        }
        foreach ($request->charts['operating_expenses'] as $item) {
            $statementData = new IncomeStatementData();
            $statementData->client_id = $client->id;
            $statementData->income_statement_id = $statement->id;
            $statementData->chart_of_account_id = $item['chart_of_account_id'];
            $statementData->name = $item['name'];
            $statementData->amount = $item['amount'];
            $statementData->save();
        }
        foreach ($request->charts['other_income'] as $item) {
            $statementData = new IncomeStatementData();
            $statementData->client_id = $client->id;
            $statementData->income_statement_id = $statement->id;
            $statementData->chart_of_account_id = $item['chart_of_account_id'];
            $statementData->name = $item['name'];
            $statementData->amount = $item['amount'];
            $statementData->save();
        }
        foreach ($request->charts['other_expenses'] as $item) {
            $statementData = new IncomeStatementData();
            $statementData->client_id = $client->id;
            $statementData->income_statement_id = $statement->id;
            $statementData->chart_of_account_id = $item['chart_of_account_id'];
            $statementData->name = $item['name'];
            $statementData->amount = $item['amount'];
            $statementData->save();
        }
        foreach ($request->charts['income_tax'] as $item) {
            $statementData = new IncomeStatementData();
            $statementData->client_id = $client->id;
            $statementData->income_statement_id = $statement->id;
            $statementData->chart_of_account_id = $item['chart_of_account_id'];
            $statementData->name = $item['name'];
            $statementData->amount = $item['amount'];
            $statementData->save();
        }
        foreach ($request->charts['net_finance_costs'] as $item) {
            $statementData = new IncomeStatementData();
            $statementData->client_id = $client->id;
            $statementData->income_statement_id = $statement->id;
            $statementData->chart_of_account_id = $item['chart_of_account_id'];
            $statementData->name = $item['name'];
            $statementData->amount = $item['amount'];
            $statementData->save();
        }
        activity()
            ->performedOn($client)
            ->log('Update Income Statement');
        return redirect()->route('clients.income_statements.index', [$client->id])->with('success', 'Income Statement updated successfully.');

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param IncomeStatement $statement
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(IncomeStatement $statement)
    {
        $statement->data->each->delete();
        $statement->delete();
        activity()
            ->performedOn($statement)
            ->log('Delete Income Statement');
        return redirect()->route('clients.income_statements.index', [$statement->client_id])->with('success', 'Client statement deleted successfully.');

    }
}
