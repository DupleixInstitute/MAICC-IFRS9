<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                <inertia-link class="text-indigo-400 hover:text-indigo-600" :href="route('clients.index')">Clients
                </inertia-link>
                <span class="text-indigo-400 font-medium">/</span> {{ client.name }}
            </h2>
        </template>
        <div class="mx-auto">
            <div class="md:flex md:items-start">
                <div class="bg-white relative shadow-xl mb-4 mt-20 w-full md:w-3/12">
                    <div class="col-span-12 lg:col-span-4 xxl:col-span-3 flex lg:block flex-col-reverse">
                        <div class="intro-y box mt-5 lg:mt-0">
                            <client-menu :client="client"></client-menu>
                        </div>
                    </div>

                </div>
                <div class="w-full md:w-9/12 p-4 md:ml-4 bg-white sm:mt-4">
                    <div class="flex justify-between ">
                        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Ratio Analysis</h2>
                        <button class="btn btn-blue" v-if="can('clients.ratio_analysis.create')" @click="submit">
                            <span>Save </span>
                        </button>
                    </div>
                    <div class="mt-4 relative overflow-x-auto">
                        <table class="w-full whitespace-no-wrap table-auto">
                            <thead>
                            <tr class="bg-blue-950 text-white">
                                <th class="p-2"><h5>RATIO ANALYSIS</h5></th>
                                <th class="p-2">Symbol</th>
                                <th class="p-2">Z Score<br>Weights</th>
                                <th class="p-2">Z""Score<br>Weights</th>
                                <th class="p-2" v-for="item in data">{{ item.year }}</th>
                                <th class="p-2">Weighted<br>Average</th>
                                <th class="p-2">Industry<br>Benchmark</th>
                                <th class="p-2" colspan="2">Comment</th>
                            </tr>
                            </thead>
                            <tbody>

                            <tr class="bg-blue-200">
                                <td class="p-2" colspan="10"><strong>Bankruptcy Prediction Ratios</strong></td>
                            </tr>
                            <tr class="border-b">
                                <td class="p-2 ">Working Capital/Total Assets</td>
                                <td class="p-2">X1</td>
                                <td class="p-2">1.2</td>
                                <td class="p-2">6.56</td>
                                <td class="p-2" v-for="item in data">
                                    <jet-input type="text" class="w-20" v-model="item.working_capital_total_assets"
                                               readonly=""/>
                                </td>
                                <td class="p-2">
                                    <jet-input type="text" class="w-20" v-model="ratio.working_capital_total_assets"
                                               readonly=""/>
                                </td>
                                <td class="p-2"></td>
                                <td class="p-2" rowspan="5">
                                    <textarea-input id="description" rows="16" cols="40" class="block h-full w-full"
                                                    v-model="form.bankruptcy_prediction_ratios"/>
                                </td>
                            </tr>
                            <tr class="border-b">
                                <td class="p-2">Retained Earnings/Total Assets</td>
                                <td class="p-2">X2</td>
                                <td class="p-2">1.4</td>
                                <td class="p-2">3.26</td>
                                <td class="p-2" v-for="item in data">
                                    <jet-input type="text" class="w-20" v-model="item.retained_earnings_total_assets"
                                               readonly=""/>
                                </td>
                                <td class="p-2">
                                    <jet-input type="text" class="w-20" v-model="ratio.retained_earnings_total_assets"
                                               readonly=""/>
                                </td>
                                <td class="p-2"></td>
                            </tr>

                            <tr class="border-b">
                                <td class="p-2">EBIT/Total Assets</td>
                                <td class="p-2">X3</td>
                                <td class="p-2">3.3</td>
                                <td class="p-2">6.72</td>
                                <td class="p-2" v-for="item in data">
                                    <jet-input type="text" class="w-20" v-model="item.ebit_total_assets" readonly=""/>
                                </td>
                                <td class="p-2">
                                    <jet-input type="text" class="w-20" v-model="ratio.ebit_total_assets" readonly=""/>
                                </td>
                                <td class="p-2"></td>
                            </tr>
                            <tr class="border-b">
                                <td class="p-2">Equity(market value)/Total Liabilities</td>
                                <td class="p-2">X4</td>
                                <td class="p-2">0.6</td>
                                <td class="p-2">1.05</td>
                                <td class="p-2" v-for="item in data">
                                    <jet-input type="text" class="w-20" v-model="item.equity_total_liabilities"
                                               readonly=""/>
                                </td>
                                <td class="p-2">
                                    <jet-input type="text" class="w-20" v-model="ratio.equity_total_liabilities"
                                               readonly=""/>
                                </td>
                                <td class="p-2"></td>
                            </tr>
                            <tr class="border-b">
                                <td class="p-2">Sales/Total Assets</td>
                                <td class="p-2">X5</td>
                                <td class="p-2">1</td>
                                <td class="p-2">0</td>
                                <td class="p-2" v-for="item in data">
                                    <jet-input type="text" class="w-20" v-model="item.sales_total_assets" readonly=""/>
                                </td>
                                <td class="p-2">
                                    <jet-input type="text" class="w-20" v-model="ratio.sales_total_assets" readonly=""/>
                                </td>
                                <td class="p-2"></td>
                            </tr>
                            <tr>
                                <td>.</td>
                            </tr>
                            <tr class="border-b">
                                <td colspan="4" class="p-2 font-bold">Z Score</td>
                                <td class="p-2" v-for="item in data">
                                    <jet-input type="text" class="w-20" v-model="item.z_score" readonly=""/>
                                </td>
                                <td class="p-2">
                                    <jet-input type="text" class="w-20" v-model="ratio.z_score" readonly=""/>
                                </td>
                                <td class="p-2"></td>
                                <td class="p-2" colspan="2">
                                    <jet-input type="text" class="w-full" v-model="form.z_score_comment"/>
                                </td>
                            </tr>
                            <tr class="border-b">
                                <td colspan="4" class="p-2 font-bold">Z" Score</td>
                                <td class="p-2" v-for="item in data">
                                    <jet-input type="text" class="w-20" v-model="item.z_second_score" readonly=""/>
                                </td>
                                <td class="p-2">
                                    <jet-input type="text" class="w-20" v-model="ratio.z_second_score" readonly=""/>
                                </td>
                                <td class="p-2"></td>
                                <td class="p-2" colspan="2">
                                    <jet-input type="text" class="w-full" v-model="form.z_second_score_comment"/>
                                </td>
                            </tr>
                            <tr class="border-b">
                                <td class="p-2 font-bold">Z Score Thresholds - Healthy</td>
                                <td class="p-2"></td>
                                <td class="p-2 bg-green-800">3</td>
                                <td class="p-2 bg-green-800">2.6</td>
                                <td class="p-2" colspan="6"></td>
                            </tr>
                            <tr class="border-b">
                                <td class="p-2 font-bold">Z" Score Thresholds - Bankrupt</td>
                                <td class="p-2"></td>
                                <td class="p-2 bg-red-600">1.8</td>
                                <td class="p-2 bg-red-600">1.1</td>
                                <td class="p-2" colspan="6"></td>
                            </tr>
                            <tr class="border-b bg-blue-200">
                                <td class="p-2 font-bold" colspan="10">Liquidity Ratios</td>
                            </tr>
                            <tr class="border-b">
                                <td colspan="4" class="p-2">Current Ratio</td>
                                <td class="p-2" v-for="item in data">
                                    <jet-input type="text" class="w-20" v-model="item.current_ratio" readonly=""/>
                                </td>
                                <td class="p-2">
                                    <jet-input type="text" class="w-20" v-model="ratio.current_ratio" readonly=""/>
                                </td>
                                <td class="p-2">
                                    <jet-input type="text" class="w-20" v-model="client.industry_type.current_ratio"
                                               readonly=""/>
                                </td>
                                <td class="p-2" colspan="2">
                                    <jet-input type="text" class="w-full" v-model="form.current_ratio_comment"/>
                                </td>
                            </tr>
                            <tr class="border-b">
                                <td colspan="4" class="p-2">Quick Ratio</td>
                                <td class="p-2" v-for="item in data">
                                    <jet-input type="text" class="w-20" v-model="item.quick_ratio" readonly=""/>
                                </td>
                                <td class="p-2">
                                    <jet-input type="text" class="w-20" v-model="ratio.quick_ratio" readonly=""/>
                                </td>
                                <td class="p-2">
                                    <jet-input type="text" class="w-20" v-model="client.industry_type.quick_ratio"
                                               readonly=""/>
                                </td>
                                <td class="p-2" colspan="2">
                                    <jet-input type="text" class="w-full" v-model="form.quick_ratio_comment"/>
                                </td>
                            </tr>
                            <tr class="border-b">
                                <td colspan="4" class="p-2">Debtor Days</td>
                                <td class="p-2" v-for="item in data">
                                    <jet-input type="text" class="w-20" v-model="item.debtor_days" readonly=""/>
                                </td>
                                <td class="p-2">
                                    <jet-input type="text" class="w-20" v-model="ratio.debtor_days" readonly=""/>
                                </td>
                                <td class="p-2">
                                    <jet-input type="text" class="w-20" v-model="client.industry_type.debtor_days"
                                               readonly=""/>
                                </td>
                                <td class="p-2" colspan="2">
                                    <jet-input type="text" class="w-full" v-model="form.debtor_days_comment"/>
                                </td>
                            </tr>
                            <tr class="border-b">
                                <td colspan="4" class="p-2">Creditor Days</td>
                                <td class="p-2" v-for="item in data">
                                    <jet-input type="text" class="w-20" v-model="item.creditor_days" readonly=""/>
                                </td>
                                <td class="p-2">
                                    <jet-input type="text" class="w-20" v-model="ratio.creditor_days" readonly=""/>
                                </td>
                                <td class="p-2">
                                    <jet-input type="text" class="w-20" v-model="client.industry_type.creditor_days"
                                               readonly=""/>
                                </td>
                                <td class="p-2" colspan="2">
                                    <jet-input type="text" class="w-full" v-model="form.creditor_days_comment"/>
                                </td>
                            </tr>
                            <tr class="border-b">
                                <td colspan="4" class="p-2">Turnover/Working Capital</td>
                                <td class="p-2" v-for="item in data">
                                    <jet-input type="text" class="w-20" v-model="item.working_capital" readonly=""/>
                                </td>
                                <td class="p-2">
                                    <jet-input type="text" class="w-20" v-model="ratio.working_capital" readonly=""/>
                                </td>
                                <td class="p-2">
                                    <jet-input type="text" class="w-20" v-model="client.industry_type.working_capital"
                                               readonly=""/>
                                </td>
                                <td class="p-2" colspan="2">
                                    <jet-input type="text" class="w-full" v-model="form.working_capital_comment"/>
                                </td>

                            </tr>
                            <tr class="border-b bg-blue-200">
                                <td class="p-2 font-bold" colspan="10">Profitability Ratios</td>
                            </tr>
                            <tr class="border-b">
                                <td colspan="4" class="p-2">Turnover Growth%</td>
                                <td class="p-2" v-for="item in data">
                                    <div class="flex items-center">
                                        <jet-input type="text" class="w-20" v-model="item.turnover_growth" readonly=""/>
                                        %
                                    </div>
                                </td>
                                <td class="p-2">
                                    <div class="flex items-center">
                                        <jet-input type="text" class="w-20" v-model="ratio.turnover_growth"
                                                   readonly=""/>
                                        %
                                    </div>
                                </td>
                                <td class="p-2">
                                    <div class="flex items-center">
                                        <jet-input type="text" class="w-20"
                                                   v-model="client.industry_type.turnover_growth"
                                                   readonly=""/>
                                        %
                                    </div>
                                </td>
                                <td class="p-2" colspan="2">
                                    <jet-input type="text" class="w-full" v-model="form.turnover_growth_comment"/>
                                </td>
                            </tr>
                            <tr class="border-b">
                                <td colspan="4" class="p-2">Gross Profit%</td>
                                <td class="p-2" v-for="item in data">
                                    <div class="flex items-center">
                                        <jet-input type="text" class="w-20" v-model="item.gross_profit" readonly=""/>
                                        %
                                    </div>
                                </td>
                                <td class="p-2">
                                    <div class="flex items-center">
                                        <jet-input type="text" class="w-20" v-model="ratio.gross_profit" readonly=""/>
                                        %
                                    </div>
                                </td>
                                <td class="p-2">
                                    <div class="flex items-center">
                                        <jet-input type="text" class="w-20" v-model="client.industry_type.gross_profit"
                                                   readonly=""/>
                                        %
                                    </div>
                                </td>
                                <td class="p-2" colspan="2">
                                    <jet-input type="text" class="w-full" v-model="form.gross_profit_comment"/>
                                </td>
                            </tr>
                            <tr class="border-b">
                                <td colspan="4" class="p-2">Operating Profit Margin</td>
                                <td class="p-2" v-for="item in data">
                                    <div class="flex items-center">
                                        <jet-input type="text" class="w-20" v-model="item.operating_profit_margin"
                                                   readonly=""/>
                                        %
                                    </div>
                                </td>
                                <td class="p-2">
                                    <div class="flex items-center">
                                        <jet-input type="text" class="w-20" v-model="ratio.operating_profit_margin"
                                                   readonly=""/>
                                        %
                                    </div>
                                </td>
                                <td class="p-2">
                                    <div class="flex items-center">
                                        <jet-input type="text" class="w-20"
                                                   v-model="client.industry_type.operating_profit_margin"
                                                   readonly=""/>
                                        %
                                    </div>
                                </td>
                                <td class="p-2" colspan="2">
                                    <jet-input type="text" class="w-full"
                                               v-model="form.operating_profit_margin_comment"/>
                                </td>
                            </tr>
                            <tr class="border-b">
                                <td colspan="4" class="p-2">Net Profit Margin</td>
                                <td class="p-2" v-for="item in data">
                                    <div class="flex items-center">
                                        <jet-input type="text" class="w-20" v-model="item.net_profit_margin"
                                                   readonly=""/>
                                        %
                                    </div>
                                </td>
                                <td class="p-2">
                                    <div class="flex items-center">
                                        <jet-input type="text" class="w-20" v-model="ratio.net_profit_margin"
                                                   readonly=""/>
                                        %
                                    </div>
                                </td>
                                <td class="p-2">
                                    <div class="flex items-center">
                                        <jet-input type="text" class="w-20"
                                                   v-model="client.industry_type.net_profit_margin"
                                                   readonly=""/>
                                        %
                                    </div>
                                </td>
                                <td class="p-2" colspan="2">
                                    <jet-input type="text" class="w-full" v-model="form.net_profit_margin_comment"/>
                                </td>
                            </tr>
                            <tr class="border-b">
                                <td colspan="4" class="p-2">Return On Equity(ROE)</td>
                                <td class="p-2" v-for="item in data">
                                    <div class="flex items-center">
                                        <jet-input type="text" class="w-20" v-model="item.return_on_equity"
                                                   readonly=""/>
                                        %
                                    </div>
                                </td>
                                <td class="p-2">
                                    <div class="flex items-center">
                                        <jet-input type="text" class="w-20" v-model="ratio.return_on_equity"
                                                   readonly=""/>
                                        %
                                    </div>
                                </td>
                                <td class="p-2">
                                    <div class="flex items-center">
                                        <jet-input type="text" class="w-20"
                                                   v-model="client.industry_type.return_on_equity"
                                                   readonly=""/>
                                        %
                                    </div>
                                </td>
                                <td class="p-2" colspan="2">
                                    <jet-input type="text" class="w-full" v-model="form.return_on_equity_comment"/>
                                </td>
                            </tr>
                            <tr class="border-b">
                                <td colspan="4" class="p-2">Return On Assets(ROA)</td>
                                <td class="p-2" v-for="item in data">
                                    <div class="flex items-center">
                                        <jet-input type="text" class="w-20" v-model="item.return_on_assets"
                                                   readonly=""/>
                                        %
                                    </div>
                                </td>
                                <td class="p-2">
                                    <div class="flex items-center">
                                        <jet-input type="text" class="w-20" v-model="ratio.return_on_assets"
                                                   readonly=""/>
                                        %
                                    </div>
                                </td>
                                <td class="p-2">
                                    <div class="flex items-center">
                                        <jet-input type="text" class="w-20"
                                                   v-model="client.industry_type.return_on_assets"
                                                   readonly=""/>
                                        %
                                    </div>
                                </td>
                                <td class="p-2" colspan="2">
                                    <jet-input type="text" class="w-full" v-model="form.return_on_assets_comment"/>
                                </td>
                            </tr>
                            <tr class="border-b">
                                <td colspan="4" class="p-2">Return On Investments(ROI)</td>
                                <td class="p-2" v-for="item in data">
                                    <div class="flex items-center">
                                        <jet-input type="text" class="w-20" v-model="item.return_on_investment"
                                                   readonly=""/>
                                        %
                                    </div>
                                </td>
                                <td class="p-2">
                                    <div class="flex items-center">
                                        <jet-input type="text" class="w-20" v-model="ratio.return_on_investment"
                                                   readonly=""/>
                                        %
                                    </div>
                                </td>
                                <td class="p-2">
                                    <div class="flex items-center">
                                        <jet-input type="text" class="w-20"
                                                   v-model="client.industry_type.return_on_investment"
                                                   readonly=""/>
                                        %
                                    </div>
                                </td>
                                <td class="p-2" colspan="2">
                                    <jet-input type="text" class="w-full" v-model="form.return_on_investment_comment"/>
                                </td>
                            </tr>

                            <tr class="bg-blue-200">
                                <td class="p-2" colspan="10">Capital Structure Ratios</td>
                            </tr>
                            <tr class="border-b">
                                <td colspan="4" class="p-2">Gearing Ratio</td>
                                <td class="p-2" v-for="item in data">
                                    <div class="flex items-center">
                                        <jet-input type="text" class="w-20" v-model="item.gearing_ratio" readonly=""/>
                                        %
                                    </div>
                                </td>
                                <td class="p-2">
                                    <div class="flex items-center">
                                        <jet-input type="text" class="w-20" v-model="ratio.gearing_ratio" readonly=""/>
                                        %
                                    </div>
                                </td>
                                <td class="p-2">
                                    <div class="flex items-center">
                                        <jet-input type="text" class="w-20" v-model="client.industry_type.gearing_ratio"
                                                   readonly=""/>
                                        %
                                    </div>
                                </td>
                                <td class="p-2" colspan="2">
                                    <jet-input type="text" class="w-full" v-model="form.gearing_ratio_comment"/>
                                </td>
                            </tr>
                            <tr class="border-b">
                                <td colspan="4" class="p-2">Long-term Debt/Equity</td>
                                <td class="p-2" v-for="item in data">
                                    <div class="flex items-center">
                                        <jet-input type="text" class="w-20" v-model="item.long_term_debt" readonly=""/>
                                        %
                                    </div>
                                </td>
                                <td class="p-2">
                                    <div class="flex items-center">
                                        <jet-input type="text" class="w-20" v-model="ratio.long_term_debt" readonly=""/>
                                        %
                                    </div>
                                </td>
                                <td class="p-2">
                                    <div class="flex items-center">
                                        <jet-input type="text" class="w-20"
                                                   v-model="client.industry_type.long_term_debt"
                                                   readonly=""/>
                                        %
                                    </div>
                                </td>
                                <td class="p-2" colspan="2">
                                    <jet-input type="text" class="w-full" v-model="form.long_term_debt_comment"/>
                                </td>
                            </tr>
                            <tr class="border-b">
                                <td colspan="4" class="p-2">Debt/Tangible Net Worth</td>
                                <td class="p-2" v-for="item in data">
                                    <div class="flex items-center">
                                        <jet-input type="text" class="w-20" v-model="item.tangible_net_worth"
                                                   readonly=""/>
                                        %
                                    </div>
                                </td>
                                <td class="p-2">
                                    <div class="flex items-center">
                                        <jet-input type="text" class="w-20" v-model="ratio.tangible_net_worth"
                                                   readonly=""/>
                                        %
                                    </div>
                                </td>
                                <td class="p-2">
                                    <div class="flex items-center">
                                        <jet-input type="text" class="w-20"
                                                   v-model="client.industry_type.tangible_net_worth"
                                                   readonly=""/>
                                        %
                                    </div>
                                </td>
                                <td class="p-2" colspan="2">
                                    <jet-input type="text" class="w-full" v-model="form.tangible_net_worth_comment"/>
                                </td>
                            </tr>

                            <tr class="border-b">
                                <td colspan="4" class="p-2">Equity/Total Assets</td>
                                <td class="p-2" v-for="item in data">
                                    <div class="flex items-center">
                                        <jet-input type="text" class="w-20" v-model="item.total_assets" readonly=""/>
                                        %
                                    </div>
                                </td>
                                <td class="p-2">
                                    <div class="flex items-center">
                                        <jet-input type="text" class="w-20" v-model="ratio.total_assets" readonly=""/>
                                        %
                                    </div>
                                </td>
                                <td class="p-2">
                                    <div class="flex items-center">
                                        <jet-input type="text" class="w-20"
                                                   v-model="client.industry_type.total_assets"
                                                   readonly=""/>
                                        %
                                    </div>
                                </td>
                                <td class="p-2" colspan="2">
                                    <jet-input type="text" class="w-full" v-model="form.total_assets_comment"/>
                                </td>
                            </tr>
                            <tr class="border-b">
                                <td colspan="4" class="p-2">Solvency</td>
                                <td class="p-2" v-for="item in data">
                                    <div class="flex items-center">
                                        <jet-input type="text" class="w-20" v-model="item.solvency" readonly=""/>
                                        %
                                    </div>
                                </td>
                                <td class="p-2">
                                    <div class="flex items-center">
                                        <jet-input type="text" class="w-20" v-model="ratio.solvency" readonly=""/>
                                        %
                                    </div>
                                </td>
                                <td class="p-2">
                                    <div class="flex items-center">
                                        <jet-input type="text" class="w-20"
                                                   v-model="client.industry_type.solvency"
                                                   readonly=""/>
                                        %
                                    </div>
                                </td>
                                <td class="p-2" colspan="2">
                                    <jet-input type="text" class="w-full" v-model="form.solvency_comment"/>
                                </td>
                            </tr>
                            <tr class="bg-blue-200">
                                <td class="p-2" colspan="10">Debt Service Ratios</td>
                            </tr>
                            <tr class="border-b">
                                <td colspan="4" class="p-2">Interest Cover</td>
                                <td class="p-2" v-for="item in data">
                                    <div class="flex items-center">
                                        <jet-input type="text" class="w-20" v-model="item.interest_cover" readonly=""/>
                                        %
                                    </div>
                                </td>
                                <td class="p-2">
                                    <div class="flex items-center">
                                        <jet-input type="text" class="w-20" v-model="ratio.interest_cover" readonly=""/>
                                        %
                                    </div>
                                </td>
                                <td class="p-2">
                                    <div class="flex items-center">
                                        <jet-input type="text" class="w-20"
                                                   v-model="client.industry_type.interest_cover"
                                                   readonly=""/>
                                        %
                                    </div>
                                </td>
                                <td class="p-2" colspan="2">
                                    <jet-input type="text" class="w-full" v-model="form.interest_cover_comment"/>
                                </td>
                            </tr>
                            <tr class="border-b">
                                <td colspan="4" class="p-2">EBITDA/Gross Interest Debts</td>
                                <td class="p-2" v-for="item in data">
                                    <div class="flex items-center">
                                        <jet-input type="text" class="w-20" v-model="item.gross_interest_debts"
                                                   readonly=""/>
                                        %
                                    </div>
                                </td>
                                <td class="p-2">
                                    <div class="flex items-center">
                                        <jet-input type="text" class="w-20" v-model="ratio.gross_interest_debts"
                                                   readonly=""/>
                                        %
                                    </div>
                                </td>
                                <td class="p-2">
                                    <div class="flex items-center">
                                        <jet-input type="text" class="w-20"
                                                   v-model="client.industry_type.gross_interest_debts"
                                                   readonly=""/>
                                        %
                                    </div>
                                </td>
                                <td class="p-2" colspan="2">
                                    <jet-input type="text" class="w-full" v-model="form.gross_interest_debts_comment"/>
                                </td>
                            </tr>
                            <tr class="bg-blue-200">
                                <td class="p-2" colspan="10">Activity Ratios</td>
                            </tr>
                            <tr class="border-b">
                                <td colspan="4" class="p-2">Total Assets/Turnover</td>
                                <td class="p-2" v-for="item in data">
                                    <div class="flex items-center">
                                        <jet-input type="text" class="w-20" v-model="item.total_assets_turnover"
                                                   readonly=""/>
                                        %
                                    </div>
                                </td>
                                <td class="p-2">
                                    <div class="flex items-center">
                                        <jet-input type="text" class="w-20" v-model="ratio.total_assets_turnover"
                                                   readonly=""/>
                                        %
                                    </div>
                                </td>
                                <td class="p-2">
                                    <div class="flex items-center">
                                        <jet-input type="text" class="w-20"
                                                   v-model="client.industry_type.total_assets_turnover"
                                                   readonly=""/>
                                        %
                                    </div>
                                </td>
                                <td class="p-2" colspan="2">
                                    <jet-input type="text" class="w-full" v-model="form.total_assets_turnover_comment"/>
                                </td>
                            </tr>
                            <tr class="border-b">
                                <td colspan="4" class="p-2">Fixed Assets Turnover</td>
                                <td class="p-2" v-for="item in data">
                                    <div class="flex items-center">
                                        <jet-input type="text" class="w-20" v-model="item.fixed_assets_turn_over"
                                                   readonly=""/>
                                        %
                                    </div>
                                </td>
                                <td class="p-2">
                                    <div class="flex items-center">
                                        <jet-input type="text" class="w-20" v-model="ratio.fixed_assets_turn_over"
                                                   readonly=""/>
                                        %
                                    </div>
                                </td>
                                <td class="p-2">
                                    <div class="flex items-center">
                                        <jet-input type="text" class="w-20"
                                                   v-model="client.industry_type.fixed_assets_turn_over"
                                                   readonly=""/>
                                        %
                                    </div>
                                </td>
                                <td class="p-2" colspan="2">
                                    <jet-input type="text" class="w-full"
                                               v-model="form.fixed_assets_turn_over_comment"/>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                        <div class="flex justify-end">
                            <button class="btn btn-blue mt-4" v-if="can('clients.ratio_analysis.create')"
                                    @click="submit">
                                <span>Save </span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <teleport to="head">
            <title>{{ pageTitle }}</title>
            <meta property="og:description" :content="pageDescription">
        </teleport>
    </app-layout>

</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import Icon from '@/Jetstream/Icon.vue'
import Pagination from '@/Jetstream/Pagination.vue'
import SearchFilter from '@/Jetstream/SearchFilter.vue'
import FilterSearch from '@/Jetstream/FilterSearch.vue'
import mapValues from 'lodash/mapValues'
import pickBy from 'lodash/pickBy'
import throttle from 'lodash/throttle'
import JetLabel from '@/Jetstream/Label.vue'
import SelectInput from '@/Jetstream/SelectInput.vue'
import JetConfirmationModal from '@/Jetstream/ConfirmationModal.vue'
import JetDangerButton from '@/Jetstream/DangerButton.vue'
import JetSecondaryButton from '@/Jetstream/SecondaryButton.vue'
import ClientMenu from '@/Pages/Clients/ClientMenu.vue'
import TextareaInput from "@/Jetstream/TextareaInput.vue";
import JetCheckbox from "@/Jetstream/Checkbox.vue";
import JetInput from "@/Jetstream/Input.vue";
import JetInputError from "@/Jetstream/InputError.vue";
import JetButton from "@/Jetstream/Button.vue";
import Button from "../../../Jetstream/Button.vue";

export default {
    components: {
        Button,
        AppLayout,
        Icon,
        Pagination,
        SearchFilter,
        FilterSearch,
        JetLabel,
        SelectInput,
        JetConfirmationModal,
        JetDangerButton,
        JetSecondaryButton,
        ClientMenu,
        JetInput,
        JetInputError,
        JetButton,
        JetCheckbox,
        TextareaInput,
    },
    props: {
        client: Object,
        data: Object,
        ratio: Object,
        sheets: Object,

    },
    data() {
        return {
            form: this.$inertia.form({
                bankruptcy_prediction_ratios: this.ratio.bankruptcy_prediction_ratios,
                z_score_comment: this.ratio.z_score_comment,
                z_second_score_comment: this.ratio.z_second_score_comment,
                current_ratio_comment: this.ratio.current_ratio_comment,
                quick_ratio_comment: this.ratio.quick_ratio_comment,
                debtor_days_comment: this.ratio.debtor_days_comment,
                creditor_days_comment: this.ratio.creditor_days_comment,
                working_capital_comment: this.ratio.working_capital_comment,
                turnover_growth_comment: this.ratio.turnover_growth_comment,
                gross_profit_comment: this.ratio.gross_profit_comment,
                operating_profit_margin_comment: this.ratio.operating_profit_margin_comment,
                net_profit_margin_comment: this.ratio.net_profit_margin_comment,
                return_on_equity_comment: this.ratio.return_on_equity_comment,
                return_on_assets_comment: this.ratio.return_on_assets_comment,
                return_on_investment_comment: this.ratio.return_on_investment_comment,
                gearing_ratio_comment: this.ratio.gearing_ratio_comment,
                long_term_debt_comment: this.ratio.long_term_debt_comment,
                tangible_net_worth_comment: this.ratio.tangible_net_worth_comment,
                total_assets_comment: this.ratio.total_assets_comment,
                solvency_comment: this.ratio.solvency_comment,
                interest_cover_comment: this.ratio.interest_cover_comment,
                gross_interest_debts_comment: this.ratio.gross_interest_debts_comment,
                total_assets_turnover_comment: this.ratio.total_assets_turnover_comment,
                fixed_assets_turn_over_comment: this.ratio.fixed_assets_turn_over_comment,
            }),
            confirmingDeletion: false,
            selectedRecord: null,
            pageTitle: "Ratio Analysis",
            pageDescription: "Ratio Analysis",

        }
    },
    methods: {
        deleteAction(id) {
            this.confirmingDeletion = true
            this.selectedRecord = id
        },
        submit() {
            this.form.post(this.route('clients.ratio_analysis.store', this.client.id))
            this.confirmingDeletion = false
        },
    },
}
</script>

<style scoped>

</style>
