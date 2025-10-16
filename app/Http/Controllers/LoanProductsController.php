<?php

namespace App\Http\Controllers;

use App\Events\LoanProductCreated;
use App\Events\LoanProductStatusChanged;
use App\Models\LoanProductCategory;
use App\Models\LoanProduct;
use App\Models\Comment;
use App\Models\LoanProductScoringAttribute;
use App\Models\LoanProductScoringAttributeOptionValue;
use App\Models\ScoringAttributeGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class LoanProductsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(['permission:loans.products.index'])->only(['index', 'show']);
        $this->middleware(['permission:loans.products.create'])->only(['create', 'store']);
        $this->middleware(['permission:loans.products.update'])->only(['edit', 'update', 'syncAttributes']);
        $this->middleware(['permission:loans.products.destroy'])->only(['destroy']);
    }

    public function index()
    {
        $products = LoanProduct::filter(\request()->only('search', 'aggregation_criteria'))
            ->with(['createdBy', 'endTransitionProfile'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return Inertia::render('LoanProducts/Index', [
            'filters' => \request()->all('search', 'aggregation_criteria'),
            'products' => $products,
        ]);
    }


    public function create()
    {
        return Inertia::render('LoanProducts/Create', [
            'transitionProfiles' => LoanProduct::where('id', '!=', 0)->get()->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                ];
            }),
            'categories' => LoanProductCategory::all()->map(function ($item) {
                return [
                    'label' => $item->name,
                    'value' => $item->id,
                ];
            }),
        ]);
    }

    public function store(Request $request)
    {
        
        $request->validate([
            'name' => ['required', 'string'],
            'aggregation_criteria' => ['required', 'in:count,balance'],
            'end_transition_profile_id' => ['nullable', 'exists:loan_products,id'],
            'is_default' => ['boolean'],
            'is_per_portfolio' => ['boolean'],
            'is_paid' => ['boolean'],
            'is_lgd' => ['boolean'],
        ]);

        $product = new LoanProduct();
        $product->created_by_id = Auth::id();
        $product->name = $request->name;
        $product->aggregation_criteria = $request->aggregation_criteria;
        $product->end_transition_profile_id = $request->end_transition_profile_id ?: null;
        $product->is_default = $request->is_default;
        $product->is_per_portfolio = $request->is_per_portfolio;
        $product->is_paid = $request->is_paid;
        $product->is_lgd = $request->is_lgd;
        $product->save();

        activity()
            ->performedOn($product)
            ->log('Create Transition Profile');

        return redirect()->route('loan_products.index')
            ->with('success', 'Transition Profile created successfully.');
    }


    public function show(LoanProduct $product)
    {
        $product->load(['category', 'createdBy', 'scoringAttributes', 'scoringAttributes.attribute']);
        $ratios = [
            'Liquidity Ratios' => [
                [
                    'name' => 'Current Ratio',
                    'system_name' => 'current_ratio'
                ],
                [
                    'name' => 'Debtor Days',
                    'system_name' => 'debtor_days'
                ],
                [
                    'name' => 'Creditor Days',
                    'system_name' => 'creditor_days'
                ],
                [
                    'name' => 'Turnover/Working Capital',
                    'system_name' => 'working_capital'
                ],
            ],
            'Profitability Ratios' => [
                [
                    'name' => 'Turnover Growth',
                    'system_name' => 'turnover_growth'
                ],
                [
                    'name' => 'Gross Profit',
                    'system_name' => 'gross_profit'
                ],
                [
                    'name' => 'Operating Profit Margin',
                    'system_name' => 'operating_profit_margin'
                ],
                [
                    'name' => 'Net Profit Margin',
                    'system_name' => 'net_profit_margin'
                ],
                [
                    'name' => 'Return On Equity(ROE)',
                    'system_name' => 'return_on_equity'
                ],
                [
                    'name' => 'Return On Assets(ROA)',
                    'system_name' => 'return_on_assets'
                ],
                [
                    'name' => 'Return On Investments(ROI)',
                    'system_name' => 'return_on_investment'
                ],
            ],
            'Capital Structure Ratios' => [
                [
                    'name' => 'Gearing Ratio',
                    'system_name' => 'gearing_ratio'
                ],
                [
                    'name' => 'Long-term Debt/Equity',
                    'system_name' => 'long_term_debt'
                ],
                [
                    'name' => 'Debt/Tangible Net Worth',
                    'system_name' => 'tangible_net_worth'
                ],
                [
                    'name' => 'Equity/Total Assets',
                    'system_name' => 'total_assets'
                ],
                [
                    'name' => 'Solvency',
                    'system_name' => 'solvency'
                ],
            ],
            'Debt Service Ratios' => [
                [
                    'name' => 'Interest Cover',
                    'system_name' => 'interest_cover'
                ],
                [
                    'name' => 'EBITDA/Gross Interest Debts',
                    'system_name' => 'gross_interest_debts'
                ],
            ],
            'Activity Ratios' => [
                [
                    'name' => 'Total Assets/Turnover',
                    'system_name' => 'total_assets_turnover'
                ],
                [
                    'name' => 'Fixed Assets Turnover',
                    'system_name' => 'fixed_assets_turn_over'
                ],
            ],
        ];
        $groups = ScoringAttributeGroup::with(['scoringAttributes'])->get();

        $groups->transform(function ($group) use ($product) {
            if ($product->scoringAttributes->where('scoring_attribute_group_id', $group->id)->count()) {
                $group->used = true;
            } else {
                $group->used = false;
            }

            $group->scoringAttributes->transform(function ($item) use ($product) {
                if ($product->scoringAttributes->where('scoring_attribute_id', $item->id)->count()) {
                    $item->used = true;
                } else {
                    $item->used = false;
                }
                $optionsArray = [];
                if ($item->field_type === 'dropdown' || $item->field_type === 'radio' || $item->field_type === 'checkbox') {

                    $options = json_decode($item->options);
                    foreach ($options as $option) {
                        $optionsArray[] = [
                            'id' => '',
                            'loan_product_scoring_attribute_id' => '',
                            'scoring_attribute_id' => $item->id,
                            'name' => $option,
                            'weight' => '',
                            'score' => '',
                            'effective_weight' => '',
                            'weighted_score' => '',
                            'description' => '',
                            'active' => true,
                        ];
                    }
                    $item->options = $optionsArray;
                } elseif ($item->field_type === 'number' || $item->field_type === 'text') {
                    $item->options = $optionsArray;
                } else {
                    $item->options = [];
                }
                return $item;
            });

            return $group;
        });
        $brokenAttributes = [];
        $product->scoringAttributes->transform(function ($item) use (&$brokenAttributes) {
            if (!empty($item->attribute)) {
                $item->attribute->options = $item->options ?: [];


            }
            if (!empty($item->scoring_attribute_id) && empty($item->attribute)) {
                $brokenAttributes[] = [
                    'id' => $item->id,
                    'name' => $item->name
                ];
            }
            return $item;
        });
        if (count($brokenAttributes)) {
            $error = "Your loan product is broken. Some scoring attributes where deleted for the following attributes:";
            foreach ($brokenAttributes as $key) {
                $error .= "{$key['name']},";
                LoanProductScoringAttribute::where('id', $key['id'])->delete();
            }
            $error .= ".We have deleted the orphaned attributes, please re-add them";
            $product->refresh();
            $product->load(['category', 'createdBy', 'scoringAttributes', 'scoringAttributes.attribute']);
            $product->scoringAttributes->transform(function ($item) {
                if (!empty($item->attribute)) {
                    $item->attribute->options = $item->options ?: [];


                }
                return $item;
            });
            session()->flash('error', $error);
        }
        $product->form_attributes = $product->scoringAttributes->where('is_group', 1);

        $product->form_attributes->transform(function ($score) use ($product) {
            $score->attributes = $product->scoringAttributes->where('scoring_attribute_group_id', $score->scoring_attribute_group_id)->where('is_group', 0)->values();

            return $score;
        });
        $product->form_attributes = $product->form_attributes->values();
        return Inertia::render('LoanProducts/Show', [
            'product' => $product->toArray(),
            'groups' => $groups,
            'ratios' => $ratios,
        ]);
    }

    public function edit(LoanProduct $product)
    {

        return Inertia::render('LoanProducts/Edit', [
            'product' => $product,
            'categories' => LoanProductCategory::all()->map(function ($item) {
                return [
                    'label' => $item->name,
                    'value' => $item->id,
                ];
            }),
        ]);
    }

    public function update(Request $request, LoanProduct $product)
    {

        $request->validate([
            'name' => ['required', 'string'],
            'active' => ['required'],
        ]);
        $product->loan_product_category_id = $request->loan_product_category_id;
        $product->name = $request->name;
        $product->score = $request->score;
        $product->active = $request->active ? 1 : 0;
        $product->description = $request->description;
        $product->save();
        activity()
            ->performedOn($product)
            ->log('Update Loan Product');
        return redirect()->route('loan_products.show', $product->id)->with('success', 'LoanProduct updated successfully.');
    }

    public function destroy(LoanProduct $product)
    {
        $product->scoringAttributes->each->delete();
        $product->delete();
        activity()
            ->performedOn($product)
            ->log('Delete LoanProduct');
        return redirect()->route('loan_products.index')->with('success', 'LoanProduct deleted successfully.');
    }

    public function search(Request $request)
    {
        $search = $request->s;
        $id = $request->id;
        $data = LoanProduct::with(['coPayers', 'coPayers.coPayer'])->where(function ($query) use ($search) {
            $query->where('first_name', 'like', "%$search%");
            $query->orWhere('last_name', 'like', "%$search%");
            $query->orWhere('middle_name', 'like', "%$search%");
            $query->orWhere('id', 'like', "%$search%");
            $query->orWhere('id_number', 'like', "%$search%");
            $query->orWhere('external_id', 'like', "%$search%");
        })->when($id, function ($query) use ($id) {
            return $query->where('id', $id);
        })->get();
        return response()->json($data);
    }

    public function syncAttributes(Request $request, LoanProduct $product)
    {
        $attributes = $request->json('attributes');
        $existingAttributes = [];
        $existingOptions = [];
        $allAttributes = LoanProductScoringAttribute::where('loan_product_id', $product->id)->get();
        $allOptions = LoanProductScoringAttributeOptionValue::where('loan_product_id', $product->id)->pluck('id');
        foreach ($attributes as $key) {
            $attribute = null;
            if (!empty($key['id'])) {
                $existingAttributes[] = $key['id'];
                $attribute = LoanProductScoringAttribute::find($key['id']);
            }
            if (empty($attribute)) {
                $attribute = new LoanProductScoringAttribute();
                $attribute->created_by_id = Auth::id();
                $attribute->loan_product_id = $product->id;
                $attribute->scoring_attribute_group_id = $key['scoring_attribute_group_id'];
            }
            $attribute->name = $key['name'];
            $attribute->system_name = $key['system_name'];
            $attribute->weight = $key['weight'] ?? 0.0;
            $attribute->effective_weight = $key['effective_weight'] ?? 0.0;
            $attribute->score = $key['score'] ?? 0.0;
            $attribute->weighted_score = $key['weighted_score'] ?? 0.0;
            $attribute->min_score = $key['min_score'] ?? 0.0;
            $attribute->max_score = $key['max_score'] ?? 0.0;
            $attribute->order_position = $key['order_position'];
            $attribute->active = $key['active'] ? 1 : 0;
            $attribute->is_group = $key['is_group'] ? 1 : 0;
            $attribute->is_ratio = $key['is_ratio'] ? 1 : 0;
            $attribute->is_industry_analysis = $key['is_industry_analysis'] ? 1 : 0;
            $attribute->is_shareholder_analysis = $key['is_shareholder_analysis'] ? 1 : 0;
            $attribute->is_management_analysis = $key['is_management_analysis'] ? 1 : 0;
            $attribute->save();
            $existingAttributes[] = $attribute->id;
            foreach ($key['attributes'] as $item) {
                $childAttribute = null;
                if (!empty($item['id'])) {
                    $existingAttributes[] = $item['id'];
                    $childAttribute = LoanProductScoringAttribute::find($item['id']);
                }
                if (empty($childAttribute)) {
                    $childAttribute = new LoanProductScoringAttribute();
                    $childAttribute->created_by_id = Auth::id();
                    $childAttribute->loan_product_id = $product->id;
                    $childAttribute->scoring_attribute_id = $item['scoring_attribute_id'];
                    $childAttribute->scoring_attribute_group_id = $item['scoring_attribute_group_id'];
                }
                $childAttribute->name = $item['name'];
                $childAttribute->system_name = $item['system_name'];
                $childAttribute->weight = $item['weight'] ?? 0.0;
                $childAttribute->effective_weight = $item['effective_weight'] ?? 0.0;
                $childAttribute->score = $item['score'] ?? 0.0;
                $childAttribute->weighted_score = $item['weighted_score'] ?? 0.0;
                $childAttribute->min_score = $item['min_score'] ?? 0.0;
                $childAttribute->max_score = $item['max_score'] ?? 0.0;
                $childAttribute->reject_value = $item['reject_value'];
                $childAttribute->accept_value = $item['accept_value'];
                $childAttribute->accept_condition = $key['accept_condition'];
                $childAttribute->option_type = $item['option_type'];
                $childAttribute->median_value = $item['median_value'];
                $childAttribute->data = $item['data'];
                $childAttribute->order_position = $item['order_position'];
                $childAttribute->active = $item['active'] ? 1 : 0;
                $childAttribute->is_group = $item['is_group'] ? 1 : 0;
                $childAttribute->is_ratio = $item['is_ratio'] ? 1 : 0;
                $childAttribute->is_industry_analysis = $item['is_industry_analysis'] ? 1 : 0;
                $childAttribute->is_shareholder_analysis = $item['is_shareholder_analysis'] ? 1 : 0;
                $childAttribute->is_management_analysis = $item['is_management_analysis'] ? 1 : 0;
                $childAttribute->save();
                $existingAttributes[] = $attribute->id;
                //save options
                if (!empty($item['attribute']['options']) && is_array($item['attribute']['options'])) {
                    foreach ($item['attribute']['options'] as $option) {
                        if (!empty($option['id'])) {
                            $attributeOption = LoanProductScoringAttributeOptionValue::find($option['id']);
                            $existingOptions[] = $option['id'];
                        } else {
                            $attributeOption = new LoanProductScoringAttributeOptionValue();
                            $attributeOption->loan_product_scoring_attribute_id = $childAttribute->id;
                            $attributeOption->scoring_attribute_id = $option['scoring_attribute_id'];
                            $attributeOption->loan_product_id = $product->id;
                        }
                        $attributeOption->weight = $option['weight'] ?? 0.0;
                        $attributeOption->effective_weight = $option['effective_weight'] ?? 0.0;
                        $attributeOption->score = $option['score'] ?? 0.0;
                        $attributeOption->weighted_score = $option['weighted_score'] ?? 0.0;
                        $attributeOption->name = $option['name'];
                        $attributeOption->lower_value = $option['lower_value'] ?? null;
                        $attributeOption->upper_value = $option['upper_value'] ?? null;
                        $attributeOption->median_value = $option['median_value'] ?? null;
                        $attributeOption->active = $option['active'] ? 1 : 0;
                        $attributeOption->save();
                    }
                }
            }
        }
        //delete attributes that have been removed
        $newAttributes = LoanProductScoringAttribute::where('loan_product_id', $product->id)->pluck('id');
        $allAttributes->each(function ($item) use ($existingAttributes, $newAttributes) {
            if (!in_array($item->id, $existingAttributes)) {
                LoanProductScoringAttribute::where('id', $item->id)->delete();
                if ($item->is_group) {
                    LoanProductScoringAttribute::where('scoring_attribute_group_id', $item->id)->delete();
                }
            }
        });
        $newOptions = LoanProductScoringAttributeOptionValue::where('loan_product_id', $product->id)->pluck('id');
        $allOptions->each(function ($item) use ($newOptions) {
            if (!$newOptions->contains($item)) {
                LoanProductScoringAttributeOptionValue::where('id', $item)->delete();


            }
        });
        activity()
            ->performedOn($product)
            ->log('Update Loan Product');
        return redirect()->route('loan_products.show', $product->id)->with('success', 'Product attributes updated successfully.');
    }

    public function storeComment(Request $request, LoanProduct $product)
    {
        $request->validate([
            'description' => ['required'],
        ]);
        $comment = new Comment();
        $comment->created_by_id = Auth::id();
        $comment->record_id = $product->id;
        $comment->category = 'product';
        $comment->description = $request->description;
        $comment->save();

        activity()
            ->performedOn($product)
            ->log('Create LoanProduct Comment');
        return redirect()->route('products.show', $product->id)->with('success', 'comment created successfully.');
    }

    public function destroyComment(Comment $comment)
    {

        $comment->delete();
        activity()
            ->performedOn($comment)
            ->log('Delete LoanProduct comment');
        return redirect()->route('products.show', $comment->record_id)->with('success', 'comment deleted successfully.');
    }
}
