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
                        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Porter's Five Forces Analysis</h2>
                        <button @click="submit" class="btn btn-blue"
                                v-if="can('clients.poters_five_forces_analysis.create')">
                            <span>Save </span>
                        </button>
                    </div>
                    <div class="mt-4 relative overflow-x-auto">
                        <table class="w-full whitespace-no-wrap table-auto">
                            <thead class="bg-gray-50">
                            <tr class="text-left font-bold">
                                <th class="px-6 pt-4 pb-4 font-medium text-gray-500"></th>
                                <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Input</th>
                                <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Score</th>
                                <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Comment</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr
                                class="hover:bg-gray-100 focus-within:bg-gray-100">
                                <td class="border-t px-2 py-2">
                                    <span class="font-bold">
                                    Threats of New Entry
                                    </span>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <div class="border border-gray-300 p-2 rounded-md shadow-sm w-26"
                                         :class="{'bg-green-600':form.threats_of_new_entry>0},{'bg-yellow-400':form.threats_of_new_entry==0},{'bg-red-600':form.threats_of_new_entry<0}">
                                        <span v-if="form.threats_of_new_entry>0">Low</span>
                                        <span v-if="form.threats_of_new_entry==0">Medium</span>
                                        <span v-if="form.threats_of_new_entry<0">High</span>
                                    </div>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <jet-input disabled="disabled"
                                               id="threats_of_new_entry" v-model="form.threats_of_new_entry"
                                               type="text" class="block w-16"/>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <span class="">

                                    </span>
                                </td>
                            </tr>
                            <tr
                                class="hover:bg-gray-100 focus-within:bg-gray-100">
                                <td class="border-t px-2 py-2">
                                    <span class="">
                                    Time and cost of entry
                                    </span>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <select @change="updateTotal"
                                            class="border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm w-26"
                                            name="threats_of_new_entry" v-model="form.time_and_cost_of_entry"
                                            id="threats_of_new_entry"
                                            :class="{'bg-green-600':form.time_and_cost_of_entry>0},{'bg-yellow-400':form.time_and_cost_of_entry==0},{'bg-red-600':form.time_and_cost_of_entry<0}">
                                        <option value="1">Low</option>
                                        <option value="0">Medium</option>
                                        <option value="-1">High</option>
                                    </select>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <jet-input v-model="form.time_and_cost_of_entry" id="time_and_cost_of_entry"
                                               type="text" class="block w-16" disabled="disabled"/>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <jet-input id="time_and_cost_of_entry_comment"
                                               type="text" class="block w-full"
                                               v-model="form.time_and_cost_of_entry_comment"/>
                                </td>
                            </tr>
                            <tr
                                class="hover:bg-gray-100 focus-within:bg-gray-100">
                                <td class="border-t px-2 py-2">
                                    <span class="">
                                    Specialist Knowledge
                                    </span>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <select @change="updateTotal"
                                            class="border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm w-26"
                                            name="threats_of_new_entry" v-model="form.specialist_knowledge"
                                            id="threats_of_new_entry"
                                            :class="{'bg-green-600':form.specialist_knowledge>0},{'bg-yellow-400':form.specialist_knowledge==0},{'bg-red-600':form.specialist_knowledge<0}">
                                        <option value="1">Low</option>
                                        <option value="0">Medium</option>
                                        <option value="-1">High</option>
                                    </select>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <jet-input v-model="form.specialist_knowledge" id="specialist_knowledge"
                                               type="text" class="block w-16" disabled="disabled"/>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <jet-input id="time_and_cost_of_entry_comment"
                                               type="text" class="block w-full"
                                               v-model="form.specialist_knowledge_comment"/>
                                </td>
                            </tr>
                            <tr
                                class="hover:bg-gray-100 focus-within:bg-gray-100">
                                <td class="border-t px-2 py-2">
                                    <span class="">
                                    Economies Of Scale
                                    </span>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <select @change="updateTotal"
                                            class="border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm w-26"
                                            name="threats_of_new_entry" v-model="form.economies_of_scale"
                                            id="threats_of_new_entry"
                                            :class="{'bg-green-600':form.economies_of_scale>0},{'bg-yellow-400':form.economies_of_scale==0},{'bg-red-600':form.economies_of_scale<0}">
                                        <option value="1">Low</option>
                                        <option value="0">Medium</option>
                                        <option value="-1">High</option>
                                    </select>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <jet-input v-model="form.economies_of_scale" id="economies_of_scale"
                                               type="text" class="block w-16" disabled="disabled"/>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <jet-input id="time_and_cost_of_entry_comment"
                                               type="text" class="block w-full"
                                               v-model="form.economies_of_scale_comment"/>
                                </td>
                            </tr>
                            <tr
                                class="hover:bg-gray-100 focus-within:bg-gray-100">
                                <td class="border-t px-2 py-2">
                                    <span class="">
                                    Cost Advantages
                                    </span>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <select @change="updateTotal"
                                            class="border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm w-26"
                                            name="threats_of_new_entry" v-model="form.cost_advantages"
                                            id="threats_of_new_entry"
                                            :class="{'bg-green-600':form.cost_advantages>0},{'bg-yellow-400':form.cost_advantages==0},{'bg-red-600':form.cost_advantages<0}">
                                        <option value="1">Low</option>
                                        <option value="0">Medium</option>
                                        <option value="-1">High</option>
                                    </select>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <jet-input v-model="form.cost_advantages" id="cost_advantages"
                                               type="text" class="block w-16" disabled="disabled"/>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <jet-input id="cost_advantages_comment"
                                               type="text" class="block w-full" v-model="form.cost_advantages_comment"/>
                                </td>
                            </tr>
                            <tr
                                class="hover:bg-gray-100 focus-within:bg-gray-100">
                                <td class="border-t px-2 py-2">
                                    <span class="">
                                    Technology Protection
                                    </span>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <select @change="updateTotal"
                                            class="border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm w-26"
                                            name="threats_of_new_entry" v-model="form.technology_protection"
                                            id="threats_of_new_entry"
                                            :class="{'bg-green-600':form.technology_protection>0},{'bg-yellow-400':form.technology_protection==0},{'bg-red-600':form.technology_protection<0}">
                                        <option value="1">Low</option>
                                        <option value="0">Medium</option>
                                        <option value="-1">High</option>
                                    </select>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <jet-input v-model="form.technology_protection" id="technology_protection"
                                               type="text" class="block w-16" disabled="disabled"/>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <jet-input id="technology_protection_comment"
                                               type="text" class="block w-full"
                                               v-model="form.technology_protection_comment"/>
                                </td>
                            </tr>
                            <tr
                                class="hover:bg-gray-100 focus-within:bg-gray-100">
                                <td class="border-t px-2 py-2">
                                    <span class="">
                                    Barriers to Entry
                                    </span>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <select @change="updateTotal"
                                            class="border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm w-26"
                                            name="threats_of_new_entry" v-model="form.barriers_to_entry"
                                            id="threats_of_new_entry"
                                            :class="{'bg-green-600':form.barriers_to_entry>0},{'bg-yellow-400':form.barriers_to_entry==0},{'bg-red-600':form.barriers_to_entry<0}">
                                        <option value="1">Low</option>
                                        <option value="0">Medium</option>
                                        <option value="-1">High</option>
                                    </select>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <jet-input v-model="form.barriers_to_entry" id="barriers_to_entry"
                                               type="text" class="block w-16" disabled="disabled"/>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <jet-input id="barriers_to_entry_comment"
                                               type="text" class="block w-full"
                                               v-model="form.barriers_to_entry_comment"/>
                                </td>
                            </tr>
                            <tr
                                class="hover:bg-gray-100 focus-within:bg-gray-100">
                                <td class="border-t px-2 py-2">
                                    <span class="font-bold">
                                   Competitive Rivalry
                                    </span>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <div class="border border-gray-300 p-2 rounded-md shadow-sm w-26"
                                         :class="{'bg-green-600':form.competitive_rivalry>0},{'bg-yellow-400':form.competitive_rivalry==0},{'bg-red-600':form.competitive_rivalry<0}">
                                        <span v-if="form.competitive_rivalry>0">Low</span>
                                        <span v-if="form.competitive_rivalry==0">Medium</span>
                                        <span v-if="form.competitive_rivalry<0">High</span>
                                    </div>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <jet-input disabled="disabled"
                                               id="threats_of_new_entry" v-model="form.competitive_rivalry"
                                               type="text" class="block w-16"/>
                                </td>
                                <td class="border-t px-2 py-2">

                                </td>
                            </tr>
                            <tr
                                class="hover:bg-gray-100 focus-within:bg-gray-100">
                                <td class="border-t px-2 py-2">
                                    <span class="">
                                        Number Of Competitors
                                    </span>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <select @change="updateTotal"
                                            class="border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm w-26"
                                            name="number_of_competitors" v-model="form.number_of_competitors"
                                            id="number_of_competitors"
                                            :class="{'bg-green-600':form.number_of_competitors>0},{'bg-yellow-400':form.number_of_competitors==0},{'bg-red-600':form.number_of_competitors<0}">
                                        <option value="1">Low</option>
                                        <option value="0">Medium</option>
                                        <option value="-1">High</option>
                                    </select>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <jet-input v-model="form.number_of_competitors" id="number_of_competitors"
                                               type="text" class="block w-16" disabled="disabled"/>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <jet-input id="number_of_competitors_comment"
                                               type="text" class="block w-full"
                                               v-model="form.number_of_competitors_comment"/>
                                </td>
                            </tr>
                            <tr
                                class="hover:bg-gray-100 focus-within:bg-gray-100">
                                <td class="border-t px-2 py-2">
                                    <span class="">
                                        Quality Differences
                                    </span>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <select @change="updateTotal"
                                            class="border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm w-26"
                                            name="quality_differences" v-model="form.quality_differences"
                                            id="quality_differences"
                                            :class="{'bg-green-600':form.quality_differences>0},{'bg-yellow-400':form.quality_differences==0},{'bg-red-600':form.quality_differences<0}">
                                        <option value="1">Low</option>
                                        <option value="0">Medium</option>
                                        <option value="-1">High</option>
                                    </select>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <jet-input v-model="form.quality_differences" id="quality_differences"
                                               type="text" class="block w-16" disabled="disabled"/>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <jet-input id="quality_differences_comment"
                                               type="text" class="block w-full"
                                               v-model="form.quality_differences_comment"/>
                                </td>
                            </tr>
                            <tr
                                class="hover:bg-gray-100 focus-within:bg-gray-100">
                                <td class="border-t px-2 py-2">
                                    <span class="">
                                       Other Differences
                                    </span>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <select @change="updateTotal"
                                            class="border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm w-26"
                                            name="other_differences" v-model="form.other_differences"
                                            id="other_differences"
                                            :class="{'bg-green-600':form.other_differences>0},{'bg-yellow-400':form.other_differences==0},{'bg-red-600':form.other_differences<0}">
                                        <option value="1">Low</option>
                                        <option value="0">Medium</option>
                                        <option value="-1">High</option>
                                    </select>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <jet-input v-model="form.other_differences" id="other_differences"
                                               type="text" class="block w-16" disabled="disabled"/>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <jet-input id="other_differences_comment"
                                               type="text" class="block w-full"
                                               v-model="form.other_differences_comment"/>
                                </td>
                            </tr>
                            <tr
                                class="hover:bg-gray-100 focus-within:bg-gray-100">
                                <td class="border-t px-2 py-2">
                                    <span class="">
                                       Switching Costs
                                    </span>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <select @change="updateTotal"
                                            class="border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm w-26"
                                            name="switching_costs" v-model="form.switching_costs"
                                            id="switching_costs"
                                            :class="{'bg-green-600':form.switching_costs>0},{'bg-yellow-400':form.switching_costs==0},{'bg-red-600':form.switching_costs<0}">
                                        <option value="1">Low</option>
                                        <option value="0">Medium</option>
                                        <option value="-1">High</option>
                                    </select>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <jet-input v-model="form.switching_costs" id="switching_costs"
                                               type="text" class="block w-16" disabled="disabled"/>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <jet-input id="switching_costs_comment"
                                               type="text" class="block w-full"
                                               v-model="form.switching_costs_comment"/>
                                </td>
                            </tr>
                            <tr
                                class="hover:bg-gray-100 focus-within:bg-gray-100">
                                <td class="border-t px-2 py-2">
                                    <span class="">
                                       Customer Loyalty
                                    </span>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <select @change="updateTotal"
                                            class="border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm w-26"
                                            name="customer_loyalty" v-model="form.customer_loyalty"
                                            id="customer_loyalty"
                                            :class="{'bg-green-600':form.customer_loyalty>0},{'bg-yellow-400':form.customer_loyalty==0},{'bg-red-600':form.customer_loyalty<0}">
                                        <option value="1">Low</option>
                                        <option value="0">Medium</option>
                                        <option value="-1">High</option>
                                    </select>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <jet-input v-model="form.customer_loyalty" id="customer_loyalty"
                                               type="text" class="block w-16" disabled="disabled"/>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <jet-input id="customer_loyalty_comment"
                                               type="text" class="block w-full"
                                               v-model="form.customer_loyalty_comment"/>
                                </td>
                            </tr>
                            <tr
                                class="hover:bg-gray-100 focus-within:bg-gray-100">
                                <td class="border-t px-2 py-2">
                                    <span class="font-bold">
                                       Supplier Power
                                    </span>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <div class="border border-gray-300 p-2 rounded-md shadow-sm w-26"
                                         :class="{'bg-green-600':form.supplier_power>0},{'bg-yellow-400':form.supplier_power==0},{'bg-red-600':form.supplier_power<0}">
                                        <span v-if="form.supplier_power>0">Low</span>
                                        <span v-if="form.supplier_power==0">Medium</span>
                                        <span v-if="form.supplier_power<0">High</span>
                                    </div>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <jet-input disabled="disabled"
                                               id="supplier_power" v-model="form.supplier_power"
                                               type="text" class="block w-16"/>
                                </td>
                                <td class="border-t px-2 py-2">

                                </td>
                            </tr>
                            <tr
                                class="hover:bg-gray-100 focus-within:bg-gray-100">
                                <td class="border-t px-2 py-2">
                                    <span class="">
                                       Size Of Suppliers
                                    </span>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <select @change="updateTotal"
                                            class="border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm w-26"
                                            name="size_of_suppliers" v-model="form.size_of_suppliers"
                                            id="size_of_suppliers"
                                            :class="{'bg-green-600':form.size_of_suppliers>0},{'bg-yellow-400':form.size_of_suppliers==0},{'bg-red-600':form.size_of_suppliers<0}">
                                        <option value="1">Low</option>
                                        <option value="0">Medium</option>
                                        <option value="-1">High</option>
                                    </select>
                                </td>

                                <td class="border-t px-2 py-2">
                                    <jet-input v-model="form.size_of_suppliers" id="size_of_suppliers"
                                               type="text" class="block w-16" disabled="disabled"/>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <jet-input id="size_of_suppliers_comment"
                                               type="text" class="block w-full"
                                               v-model="form.size_of_suppliers_comment"/>
                                </td>
                            </tr>
                            <tr
                                class="hover:bg-gray-100 focus-within:bg-gray-100">
                                <td class="border-t px-2 py-2">
                                    <span class="">
                                       Uniqueness Of Service
                                    </span>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <select @change="updateTotal"
                                            class="border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm w-26"
                                            name="uniqueness_of_service" v-model="form.uniqueness_of_service"
                                            id="uniqueness_of_service"
                                            :class="{'bg-green-600':form.uniqueness_of_service>0},{'bg-yellow-400':form.uniqueness_of_service==0},{'bg-red-600':form.uniqueness_of_service<0}">
                                        <option value="1">Low</option>
                                        <option value="0">Medium</option>
                                        <option value="-1">High</option>
                                    </select>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <jet-input v-model="form.uniqueness_of_service" id="uniqueness_of_service"
                                               type="text" class="block w-16" disabled="disabled"/>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <jet-input id="uniqueness_of_service_comment"
                                               type="text" class="block w-full"
                                               v-model="form.uniqueness_of_service_comment"/>
                                </td>
                            </tr>
                            <tr
                                class="hover:bg-gray-100 focus-within:bg-gray-100">
                                <td class="border-t px-2 py-2">
                                    <span class="">
                                       Costs Of Supplier Change
                                    </span>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <select @change="updateTotal"
                                            class="border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm w-26"
                                            name="costs_of_supplier_change" v-model="form.costs_of_supplier_change"
                                            id="costs_of_supplier_change"
                                            :class="{'bg-green-600':form.costs_of_supplier_change>0},{'bg-yellow-400':form.costs_of_supplier_change==0},{'bg-red-600':form.costs_of_supplier_change<0}">
                                        <option value="1">Low</option>
                                        <option value="0">Medium</option>
                                        <option value="-1">High</option>
                                    </select>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <jet-input v-model="form.costs_of_supplier_change" id="costs_of_supplier_change"
                                               type="text" class="block w-16" disabled="disabled"/>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <jet-input id="costs_of_supplier_change_comment"
                                               type="text" class="block w-full"
                                               v-model="form.costs_of_supplier_change_comment"/>
                                </td>
                            </tr>
                            <tr
                                class="hover:bg-gray-100 focus-within:bg-gray-100">
                                <td class="border-t px-2 py-2">
                                    <span class="">
                                       Supplier Switching Costs
                                    </span>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <select @change="updateTotal"
                                            class="border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm w-26"
                                            name="supplier_switching_costs" v-model="form.supplier_switching_costs"
                                            id="supplier_switching_costs"
                                            :class="{'bg-green-600':form.supplier_switching_costs>0},{'bg-yellow-400':form.supplier_switching_costs==0},{'bg-red-600':form.supplier_switching_costs<0}">
                                        <option value="1">Low</option>
                                        <option value="0">Medium</option>
                                        <option value="-1">High</option>
                                    </select>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <jet-input v-model="form.supplier_switching_costs" id="supplier_switching_costs"
                                               type="text" class="block w-16" disabled="disabled"/>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <jet-input id="supplier_switching_costs_comment"
                                               type="text" class="block w-full"
                                               v-model="form.supplier_switching_costs_comment"/>
                                </td>
                            </tr>
                            <tr
                                class="hover:bg-gray-100 focus-within:bg-gray-100">
                                <td class="border-t px-2 py-2">
                                    <span class="font-bold">
                                       Threats Of Substitution
                                    </span>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <div class="border border-gray-300 p-2 rounded-md shadow-sm w-26"
                                         :class="{'bg-green-600':form.threats_of_substitution>0},{'bg-yellow-400':form.threats_of_substitution==0},{'bg-red-600':form.threats_of_substitution<0}">
                                        <span v-if="form.threats_of_substitution>0">Low</span>
                                        <span v-if="form.threats_of_substitution==0">Medium</span>
                                        <span v-if="form.threats_of_substitution<0">High</span>
                                    </div>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <jet-input disabled="disabled"
                                               id="threats_of_substitution" v-model="form.threats_of_substitution"
                                               type="text" class="block w-16"/>
                                </td>
                                <td class="border-t px-2 py-2">
                                </td>
                            </tr>
                            <tr
                                class="hover:bg-gray-100 focus-within:bg-gray-100">
                                <td class="border-t px-2 py-2">
                                    <span class="">
                                       Substitute Performance
                                    </span>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <select @change="updateTotal"
                                            class="border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm w-26"
                                            name="substitute_performance" v-model="form.substitute_performance"
                                            id="substitute_performance"
                                            :class="{'bg-green-600':form.substitute_performance>0},{'bg-yellow-400':form.substitute_performance==0},{'bg-red-600':form.substitute_performance<0}">
                                        <option value="1">Low</option>
                                        <option value="0">Medium</option>
                                        <option value="-1">High</option>
                                    </select>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <jet-input v-model="form.substitute_performance" id="substitute_performance"
                                               type="text" class="block w-16" disabled="disabled"/>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <jet-input id="substitute_performance_comment"
                                               type="text" class="block w-full"
                                               v-model="form.substitute_performance_comment"/>
                                </td>
                            </tr>
                            <tr
                                class="hover:bg-gray-100 focus-within:bg-gray-100">
                                <td class="border-t px-2 py-2">
                                    <span class="">
                                       Costs of Substitution
                                    </span>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <select @change="updateTotal"
                                            class="border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm w-26"
                                            name="costs_of_substitution" v-model="form.costs_of_substitution"
                                            id="costs_of_substitution"
                                            :class="{'bg-green-600':form.costs_of_substitution>0},{'bg-yellow-400':form.costs_of_substitution==0},{'bg-red-600':form.costs_of_substitution<0}">
                                        <option value="1">Low</option>
                                        <option value="0">Medium</option>
                                        <option value="-1">High</option>
                                    </select>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <jet-input v-model="form.costs_of_substitution" id="costs_of_substitution"
                                               type="text" class="block w-16" disabled="disabled"/>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <jet-input id="costs_of_substitution_comment"
                                               type="text" class="block w-full"
                                               v-model="form.costs_of_substitution_comment"/>
                                </td>
                            </tr>
                            <tr
                                class="hover:bg-gray-100 focus-within:bg-gray-100">
                                <td class="border-t px-2 py-2">
                                    <span class="font-bold">
                                       Buyer Power
                                    </span>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <div class="border border-gray-300 p-2 rounded-md shadow-sm w-26"
                                         :class="{'bg-green-600':form.buyer_power>0},{'bg-yellow-400':form.buyer_power==0},{'bg-red-600':form.buyer_power<0}">
                                        <span v-if="form.buyer_power>0">Low</span>
                                        <span v-if="form.buyer_power==0">Medium</span>
                                        <span v-if="form.buyer_power<0">High</span>
                                    </div>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <jet-input disabled="disabled"
                                               id="buyer_power" v-model="form.buyer_power"
                                               type="text" class="block w-16"/>
                                </td>
                                <td class="border-t px-2 py-2">
                                </td>
                            </tr>
                            <tr
                                class="hover:bg-gray-100 focus-within:bg-gray-100">
                                <td class="border-t px-2 py-2">
                                    <span class="">
                                       Number Of Customers
                                    </span>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <select @change="updateTotal"
                                            class="border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm w-26"
                                            name="number_of_customers" v-model="form.number_of_customers"
                                            id="number_of_customers"
                                            :class="{'bg-green-600':form.number_of_customers>0},{'bg-yellow-400':form.number_of_customers==0},{'bg-red-600':form.number_of_customers<0}">
                                        <option value="1">Low</option>
                                        <option value="0">Medium</option>
                                        <option value="-1">High</option>
                                    </select>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <jet-input v-model="form.number_of_customers" id="number_of_customers"
                                               type="text" class="block w-16" disabled="disabled"/>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <jet-input id="number_of_customers_comment"
                                               type="text" class="block w-full"
                                               v-model="form.number_of_customers_comment"/>
                                </td>
                            </tr>
                            <tr
                                class="hover:bg-gray-100 focus-within:bg-gray-100">
                                <td class="border-t px-2 py-2">
                                    <span class="">
                                       Single Order Size
                                    </span>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <select @change="updateTotal"
                                            class="border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm w-26"
                                            name="single_order_size" v-model="form.single_order_size"
                                            id="single_order_size"
                                            :class="{'bg-green-600':form.single_order_size>0},{'bg-yellow-400':form.single_order_size==0},{'bg-red-600':form.single_order_size<0}">
                                        <option value="1">Low</option>
                                        <option value="0">Medium</option>
                                        <option value="-1">High</option>
                                    </select>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <jet-input v-model="form.single_order_size" id="single_order_size"
                                               type="text" class="block w-16" disabled="disabled"/>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <jet-input id="single_order_size_comment"
                                               type="text" class="block w-full"
                                               v-model="form.single_order_size_comment"/>
                                </td>
                            </tr>
                            <tr
                                class="hover:bg-gray-100 focus-within:bg-gray-100">
                                <td class="border-t px-2 py-2">
                                    <span class="">
                                       Competitor Differences
                                    </span>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <select @change="updateTotal"
                                            class="border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm w-26"
                                            name="competitor_differences" v-model="form.competitor_differences"
                                            id="competitor_differences"
                                            :class="{'bg-green-600':form.competitor_differences>0},{'bg-yellow-400':form.competitor_differences==0},{'bg-red-600':form.competitor_differences<0}">
                                        <option value="1">Low</option>
                                        <option value="0">Medium</option>
                                        <option value="-1">High</option>
                                    </select>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <jet-input v-model="form.competitor_differences" id="competitor_differences"
                                               type="text" class="block w-16" disabled="disabled"/>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <jet-input id="competitor_differences_comment"
                                               type="text" class="block w-full"
                                               v-model="form.competitor_differences_comment"/>
                                </td>
                            </tr>
                            <tr
                                class="hover:bg-gray-100 focus-within:bg-gray-100">
                                <td class="border-t px-2 py-2">
                                    <span class="">
                                      Price Sensitivity
                                    </span>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <select @change="updateTotal"
                                            class="border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm w-26"
                                            name="price_sensitivity" v-model="form.price_sensitivity"
                                            id="price_sensitivity"
                                            :class="{'bg-green-600':form.price_sensitivity>0},{'bg-yellow-400':form.price_sensitivity==0},{'bg-red-600':form.price_sensitivity<0}">
                                        <option value="1">Low</option>
                                        <option value="0">Medium</option>
                                        <option value="-1">High</option>
                                    </select>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <jet-input v-model="form.price_sensitivity" id="price_sensitivity"
                                               type="text" class="block w-16" disabled="disabled"/>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <jet-input id="price_sensitivity_comment"
                                               type="text" class="block w-full"
                                               v-model="form.price_sensitivity_comment"/>
                                </td>
                            </tr>
                            <tr
                                class="hover:bg-gray-100 focus-within:bg-gray-100">
                                <td class="border-t px-2 py-2">
                                    <span class="">
                                       Ability To Substitute
                                    </span>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <select @change="updateTotal"
                                            class="border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm w-26"
                                            name="ability_to_substitute" v-model="form.ability_to_substitute"
                                            id="ability_to_substitute"
                                            :class="{'bg-green-600':form.ability_to_substitute>0},{'bg-yellow-400':form.ability_to_substitute==0},{'bg-red-600':form.ability_to_substitute<0}">
                                        <option value="1">Low</option>
                                        <option value="0">Medium</option>
                                        <option value="-1">High</option>
                                    </select>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <jet-input v-model="form.ability_to_substitute" id="ability_to_substitute"
                                               type="text" class="block w-16" disabled="disabled"/>

                                </td>
                                <td class="border-t px-2 py-2">
                                    <jet-input id="ability_to_substitute_comment"
                                               type="text" class="block w-full"
                                               v-model="form.ability_to_substitute_comment"/>
                                </td>
                            </tr>
                            <tr
                                class="hover:bg-gray-100 focus-within:bg-gray-100">
                                <td class="border-t px-2 py-2">
                                    <span class="">
                                       Customers Switching Costs
                                    </span>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <select @change="updateTotal"
                                            class="border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm w-26"
                                            name="customers_switching_costs" v-model="form.customers_switching_costs"
                                            id="customers_switching_costs"
                                            :class="{'bg-green-600':form.customers_switching_costs>0},{'bg-yellow-400':form.customers_switching_costs==0},{'bg-red-600':form.customers_switching_costs<0}">
                                        <option value="1">Low</option>
                                        <option value="0">Medium</option>
                                        <option value="-1">High</option>
                                    </select>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <jet-input v-model="form.customers_switching_costs" id="customers_switching_costs"
                                               type="text" class="block w-16" disabled="disabled"/>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <jet-input id="customers_switching_costs_comment"
                                               type="text" class="block w-full"
                                               v-model="form.customers_switching_costs_comment"/>
                                </td>
                            </tr>
                            <tr
                                class="hover:bg-gray-100 focus-within:bg-gray-100">
                                <td class="border-t px-2 py-2">
                                    <span class="font-bold">
                                       Grand Total
                                    </span>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <div class="border border-gray-300 p-2 rounded-md shadow-sm w-26"
                                         :class="{'bg-green-600':form.grand_total>0},{'bg-yellow-400':form.grand_total==0},{'bg-red-600':form.grand_total<0}">
                                        <span v-if="form.grand_total>0">Low</span>
                                        <span v-if="form.grand_total==0">Medium</span>
                                        <span v-if="form.grand_total<0">High</span>
                                    </div>
                                </td>
                                <td class="border-t px-2 py-2">
                                    <jet-input disabled="disabled"
                                               id="grand_total" v-model="form.grand_total"
                                               type="text" class="block w-16"/>
                                </td>
                                <td class="border-t px-2 py-2">

                                </td>
                            </tr>
                            </tbody>
                        </table>
                        <div class="mt-4 flex justify-end">
                            <button @click="submit" class="btn btn-blue"
                                    v-if="can('clients.poters_five_forces_analysis.create')">
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
import JetInput from "@/Jetstream/Input.vue";
import SelectInput from '@/Jetstream/SelectInput.vue'
import JetConfirmationModal from '@/Jetstream/ConfirmationModal.vue'
import JetDangerButton from '@/Jetstream/DangerButton.vue'
import JetSecondaryButton from '@/Jetstream/SecondaryButton.vue'
import ClientMenu from '@/Pages/Clients/ClientMenu.vue'
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
        JetInput
    },
    props: {
        client: Object,
        poter: Object,

    },
    data() {
        return {
            form: this.$inertia.form({
                industry_cyclicality: '',
                industry_performance: '',
                id: '',
                time_and_cost_of_entry: '',
                time_and_cost_of_entry_comment: '',
                specialist_knowledge: '',
                specialist_knowledge_comment: '',
                economies_of_scale: '',
                economies_of_scale_comment: '',
                cost_advantages: '',
                cost_advantages_comment: '',
                technology_protection: '',
                technology_protection_comment: '',
                barriers_to_entry: '',
                barriers_to_entry_comment: '',
                threats_of_new_entry: '',
                threats_of_new_entry_score: '',
                number_of_competitors: '',
                number_of_competitors_comment: '',
                quality_differences: '',
                quality_differences_comment: '',
                other_differences: '',
                other_differences_comment: '',
                switching_costs: '',
                switching_costs_comment: '',
                customer_loyalty: '',
                customer_loyalty_comment: '',
                competitive_rivalry: '',
                number_of_suppliers: '',
                number_of_suppliers_comment: '',
                size_of_suppliers: '',
                size_of_suppliers_comment: '',
                uniqueness_of_service: '',
                uniqueness_of_service_comment: '',
                costs_of_supplier_change: '',
                costs_of_supplier_change_comment: '',
                supplier_switching_costs: '',
                supplier_switching_costs_comment: '',
                supplier_power: '',
                substitute_performance: '',
                substitute_performance_comment: '',
                costs_of_substitution: '',
                costs_of_substitution_comment: '',
                threats_of_substitution: '',
                number_of_customers: '',
                number_of_customers_comment: '',
                single_order_size: '',
                single_order_size_comment: '',
                competitor_differences: '',
                competitor_differences_comment: '',
                price_sensitivity: '',
                price_sensitivity_comment: '',
                ability_to_substitute: '',
                ability_to_substitute_comment: '',
                customers_switching_costs: '',
                customers_switching_costs_comment: '',
                buyer_power: '',
                grand_total: '',
            }),
            confirmingDeletion: false,
            selectedRecord: null,
            pageTitle: "Client Poter's Five Forces Analysis",
            pageDescription: "Client Poter's Five Forces Analysis",

        }
    },
    mounted() {
        if (this.poter) {
            this.form.id = this.poter.id
            this.form.time_and_cost_of_entry = this.poter.time_and_cost_of_entry
            this.form.time_and_cost_of_entry_comment = this.poter.time_and_cost_of_entry_comment
            this.form.specialist_knowledge = this.poter.specialist_knowledge
            this.form.specialist_knowledge_comment = this.poter.specialist_knowledge_comment
            this.form.economies_of_scale = this.poter.economies_of_scale
            this.form.economies_of_scale_comment = this.poter.economies_of_scale_comment
            this.form.cost_advantages = this.poter.cost_advantages
            this.form.cost_advantages_comment = this.poter.cost_advantages_comment
            this.form.technology_protection = this.poter.technology_protection
            this.form.technology_protection_comment = this.poter.technology_protection_comment
            this.form.barriers_to_entry = this.poter.barriers_to_entry
            this.form.barriers_to_entry_comment = this.poter.barriers_to_entry_comment
            this.form.threats_of_new_entry = this.poter.threats_of_new_entry
            this.form.threats_of_new_entry_score = this.poter.threats_of_new_entry_score
            this.form.number_of_competitors = this.poter.number_of_competitors
            this.form.number_of_competitors_comment = this.poter.number_of_competitors_comment
            this.form.quality_differences = this.poter.quality_differences
            this.form.quality_differences_comment = this.poter.quality_differences_comment
            this.form.other_differences = this.poter.other_differences
            this.form.other_differences_comment = this.poter.other_differences_comment
            this.form.switching_costs = this.poter.switching_costs
            this.form.switching_costs_comment = this.poter.switching_costs_comment
            this.form.customer_loyalty = this.poter.customer_loyalty
            this.form.customer_loyalty_comment = this.poter.customer_loyalty_comment
            this.form.competitive_rivalry = this.poter.competitive_rivalry
            this.form.number_of_suppliers = this.poter.number_of_suppliers
            this.form.number_of_suppliers_comment = this.poter.number_of_suppliers_comment
            this.form.size_of_suppliers = this.poter.size_of_suppliers
            this.form.size_of_suppliers_comment = this.poter.size_of_suppliers_comment
            this.form.uniqueness_of_service = this.poter.uniqueness_of_service
            this.form.uniqueness_of_service_comment = this.poter.uniqueness_of_service_comment
            this.form.costs_of_supplier_change = this.poter.costs_of_supplier_change
            this.form.costs_of_supplier_change_comment = this.poter.costs_of_supplier_change_comment
            this.form.supplier_switching_costs = this.poter.supplier_switching_costs
            this.form.supplier_switching_costs_comment = this.poter.supplier_switching_costs_comment
            this.form.supplier_power = this.poter.supplier_power
            this.form.substitute_performance = this.poter.substitute_performance
            this.form.substitute_performance_comment = this.poter.substitute_performance_comment
            this.form.costs_of_substitution = this.poter.costs_of_substitution
            this.form.costs_of_substitution_comment = this.poter.costs_of_substitution_comment
            this.form.threats_of_substitution = this.poter.threats_of_substitution
            this.form.number_of_customers = this.poter.number_of_customers
            this.form.number_of_customers_comment = this.poter.number_of_customers_comment
            this.form.single_order_size = this.poter.single_order_size
            this.form.single_order_size_comment = this.poter.single_order_size_comment
            this.form.competitor_differences = this.poter.competitor_differences
            this.form.competitor_differences_comment = this.poter.competitor_differences_comment
            this.form.price_sensitivity = this.poter.price_sensitivity
            this.form.price_sensitivity_comment = this.poter.price_sensitivity_comment
            this.form.ability_to_substitute = this.poter.ability_to_substitute
            this.form.ability_to_substitute_comment = this.poter.ability_to_substitute_comment
            this.form.customers_switching_costs = this.poter.customers_switching_costs
            this.form.customers_switching_costs_comment = this.poter.customers_switching_costs_comment
            this.form.buyer_power = this.poter.buyer_power
            this.form.grand_total = this.poter.grand_total
        }
        this.updateTotal()
    },
    methods: {
        deleteAction(id) {
            this.confirmingDeletion = true
            this.selectedRecord = id
        },
        destroy() {
            this.$inertia.delete(this.route('clients.poter.destroy', this.selectedRecord))
            this.confirmingDeletion = false
        },
        updateTotal() {
            this.form.threats_of_new_entry = parseInt(this.form.time_and_cost_of_entry || '0') + parseInt(this.form.specialist_knowledge || '0') + parseInt(this.form.economies_of_scale || '0') + parseInt(this.form.cost_advantages || '0') + parseInt(this.form.technology_protection || '0') + parseInt(this.form.barriers_to_entry || '0');
            this.form.competitive_rivalry = parseInt(this.form.number_of_competitors || '0') + parseInt(this.form.quality_differences || '0') + parseInt(this.form.other_differences || '0') + parseInt(this.form.switching_costs || '0') + parseInt(this.form.customer_loyalty || '0');
            this.form.supplier_power = parseInt(this.form.number_of_suppliers || '0') + parseInt(this.form.size_of_suppliers || '0') + parseInt(this.form.uniqueness_of_service || '0') + parseInt(this.form.costs_of_supplier_change || '0') + parseInt(this.form.supplier_switching_costs || '0');
            this.form.threats_of_substitution = parseInt(this.form.substitute_performance || '0') + parseInt(this.form.costs_of_substitution || '0');
            this.form.buyer_power = parseInt(this.form.number_of_customers || '0') + parseInt(this.form.single_order_size || '0') + parseInt(this.form.competitor_differences || '0') + parseInt(this.form.price_sensitivity || '0') + parseInt(this.form.ability_to_substitute || '0') + parseInt(this.form.customers_switching_costs || '0');
            this.form.grand_total = this.form.threats_of_new_entry + this.form.competitive_rivalry + this.form.supplier_power + this.form.threats_of_substitution + this.form.buyer_power;
        },
        submit() {
            this.form.post(this.route('clients.poter.store', this.client.id), {
                preserveState: false
            })
        },
    },
}
</script>

<style scoped>

</style>
