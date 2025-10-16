<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\User;
use Inertia\Inertia;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Tariff;
use App\Models\Invoice;
use App\Models\Setting;
use App\Models\Currency;
use App\Events\LoanCreated;
use App\Models\InvoiceItem;
use App\Models\LoanProduct;
use Illuminate\Http\Request;
use App\Models\RatioAnalysis;
use App\Models\LoanApplication;
use App\Events\LoanStatusChanged;
use App\Models\LoanApprovalStage;
use Webit\Util\EvalMath\EvalMath;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use App\Models\LoanProductCategory;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use App\Models\LoanApplicationScore;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Models\ScoringAttributeGroup;
use App\Events\LoanApplicationCreated;
use App\Mail\LoanApplicationFinalised;
use App\Models\LoanApplicationComment;
use Illuminate\Support\Facades\Storage;
use function React\Promise\Stream\first;
use Illuminate\Database\Eloquent\Builder;
use App\Models\LoanProductScoringAttribute;
use App\Events\LoanApplicationStatusChanged;
use App\Models\LoanApplicationReviewerComment;
use App\Mail\LoanApplicationApprovalStageReturned;
use App\Models\LoanApplicationLinkedApprovalStage;
use App\Models\LoanApplicationApprovalStageHistory;
use App\Models\LoanProductScoringAttributeOptionValue;
use App\Notifications\LoanApplicationApprovalStageAssigned;
use App\Notifications\LoanApplicationApprovalStageIsCurrent;

class LoanApplicationsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(['permission:loans.applications.index'])->only(['index', 'show']);
        $this->middleware(['permission:loans.applications.create'])->only(['create', 'store']);
        $this->middleware(['permission:loans.applications.update'])->only(['edit', 'update']);
        $this->middleware(['permission:loans.applications.destroy'])->only(['destroy']);
        $this->middleware(['permission:loans.applications.recommend|loans.applications.approve'])->only(['changeStatus']);


    }

    /**
     * Display a listing of the resource.
     *
     * @return \Inertia\Response
     */
    public function index()
    {
        $staffID = null;
        $nurseID = null;
        $receptionistID = null;
        $applications = LoanApplication::with(['staff', 'client', 'product', 'currentLinkedStage', 'currentLinkedStage.stage', 'currentLinkedStage.approver', 'currentLinkedStage.assignedBy', 'branch'])
            ->filter(\request()->only('search', 'client_id', 'loan_product_id', 'province_id', 'branch_id', 'district_id', 'ward_id', 'date_range', 'village_id', 'staff_id', 'status'))
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        return Inertia::render('LoanApplications/Index', [
            'filters' => \request()->all('search', 'client_id', 'loan_product_id', 'province_id', 'branch_id', 'district_id', 'ward_id', 'date_range', 'village_id', 'staff_id', 'status'),
            'applications' => $applications,
            'products' => LoanProduct::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'branches' => Branch::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
        ]);
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Inertia\Response
     */
    public function create()
    {
        $products = LoanProduct::with(['scoringAttributes', 'scoringAttributes.attribute', 'scoringAttributes.options'])->get();
        $allAttributes = LoanProductScoringAttribute::get();
        $products->each(function (&$product) use ($allAttributes) {
            $groups = LoanProductScoringAttribute::where('is_group', 1)->where('loan_product_id', $product->id)->get();
            $groups->transform(function ($group) use ($product, $allAttributes) {
                $attributes = LoanProductScoringAttribute::with(['attribute'])->where('is_group', 0)->where('scoring_attribute_group_id', $group->scoring_attribute_group_id)->where('loan_product_id', $product->id)->orderBy('order_position')->get();
                $attributes->transform(function ($item) {
                    if (!empty($item->attribute)) {
                        $item->attribute->options = $item->options ?: [];
                        if ($item->attribute->field_type === 'checkbox') {
                            $item->value = [];
                        } else {
                            $item->value = '';
                        }
                    }

                    return $item;
                });
                $group->attributes = $attributes;
                return $group;
            });
            $product->form_attributes = $groups;
        });
        // dd($products);
        return Inertia::render('LoanApplications/Create', [
            'products' => $products,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {

        $request->validate([
            'client_id' => ['required'],
            'amount' => ['required'],
            'date' => ['required'],
        ]);
        // dd($request->all());

        $attributes = $request->json('attributes');
        $client = Client::find($request->client_id);
        // dd($client);
        $ratioAnalysis = RatioAnalysis::where('client_id', $client->id)->first();
        // dd($ratioAnalysis);



        if ($client->type === 'corporate' && empty($client->ratio)) {
            return redirect()->back()->with('error', 'No financial data entered for the chosen client. Ratio analysis not found!');
        }


        //check if client branch is set
        if ($client->branch_id == null) {
            return redirect()->back()->with('error', 'Client branch is not set. Please set the client branch before proceeding. Go To Clients > Edit Client');
        }
        $product = LoanProduct::find($request->loan_product_id);
       if($product->loan_product_category_id == 2 and $client->type == 'individual'){
           return redirect()->back()->with('error', 'Individual clients cannot apply for corporate loans');
         }
        $client->load(['shareholders', 'industryType', 'ratio']);
        $application = new LoanApplication();
        $application->created_by_id = Auth::id();
        $application->loan_product_id = $request->loan_product_id;
        $application->currency_id = Setting::where('setting_key', 'currency')->first()->setting_value;
        $application->client_id = $client->id;
        $application->branch_id = $client->branch_id;
        $application->staff_id = $request->staff_id;
        $application->date = $request->date;
        $application->amount = $request->amount;
        $application->applied_amount = $request->amount;
        $application->description = $request->description;
        $application->product_description = $request->product_description;
        $application->save();
        //save scores
        foreach ($attributes as $group) {
            foreach ($group['attributes'] as $field) {
                $score = new LoanApplicationScore();
                $score->created_by_id = Auth::id();
                $score->loan_application_id = $application->id;
                $score->scoring_attribute_id = $field['scoring_attribute_id'];
                $score->loan_product_scoring_attribute_id = $field['id'];
                $score->weight = $field['weight'];
                $score->effective_weight = $field['effective_weight'];
                $score->weighted_score = $field['weighted_score'];
                if ($field['attribute']['field_type'] === 'checkbox') {
                    $score->value = json_encode($field['value']);
                } else {
                    $score->value = $field['value'];
                }

                //determine the score
                if ($field['attribute']['field_type'] === 'dropdown' || $field['attribute']['field_type'] === 'radio') {
                    foreach ($field['attribute']['options'] as $key) {
                        if ($key['name'] == $field['value']) {
                            $score->score = $key['score'];
                            //check if score is accepted
                            if (in_array($key['name'], $field['accept_value'])) {
                                $score->accepted = 1;
                            }
                        }
                    }
                } elseif ($field['attribute']['field_type'] === 'checkbox') {
                    $tempScore = 0;
                    $tempAccepted = 0;
                    foreach ($field['value'] as $item) {
                        foreach ($field['attribute']['options'] as $key) {
                            if ($key['name'] == $item) {
                                $tempScore += $key['score'];
                                //check if score is accepted
                                if (in_array($key['name'], $field['accept_value'])) {
                                    $tempAccepted = 1;
                                }
                            }
                        }
                    }
                    $score->score = $tempScore;
                    $score->accepted = $tempAccepted;
                } elseif ($field['attribute']['field_type'] === 'text' || $field['attribute']['field_type'] === 'number') {
                    if ($field['option_type'] === 'range') {
                        foreach ($field['attribute']['options'] as $key) {
                            if ($field['value'] >= $key['lower_value'] && $field['value'] <= $key['upper_value']) {
                                $score->score = $key['score'];
                                //check if score is accepted
                                if (in_array($key['name'], $field['accept_value'])) {
                                    $score->accepted = 1;
                                }
                            }
                        }
                    }
                    if ($field['option_type'] === 'greater_than_or_less_than') {
                        foreach ($field['attribute']['options'] as $key) {
                            if ($key['name'] === 'Greater Than or Equal To' && $field['value'] >= $field['median_value']) {
                                $score->score = $key['score'];
                                //check if score is accepted
                                if (in_array($key['name'], $field['accept_value'])) {
                                    $score->accepted = 1;
                                }
                            }
                            if ($key['name'] === 'Less Than' && $field['value'] < $field['median_value']) {
                                $score->score = $key['score'];
                                //check if score is accepted
                                if (in_array($key['name'], $field['accept_value'])) {
                                    $score->accepted = 1;
                                }
                            }
                        }
                    }
                } else {
                    $score->score = $field['score'];
                }
                $score->save();
            }
        }
        //let's update fields of type formula
        $formulaAttributes = LoanApplicationScore::with(['productAttribute'])
            ->where('loan_application_id', $application->id)
            ->whereHas('scoringAttribute', function (Builder $query) {
                $query->where('field_type', 'formula');
            })->get();
        foreach ($formulaAttributes as $attribute) {
            $formula = $attribute->productAttribute->data;
            preg_match_all(
                '/\{\{field_(\d)+}}/',
                $formula,
                $matches,
                PREG_PATTERN_ORDER
            );
            if (!empty($matches[0])) {
                foreach ($matches[0] as $match) {
                    //find the value of that field
                    $score = LoanApplicationScore::where('loan_product_scoring_attribute_id', explode('_', $match)[1])->where('loan_application_id', $application->id)->first();
                    if (empty($score)) {
                        //throw error
                    }
                    $formula = str_replace($match, $score->value, $formula);
                }
                //at this point our formula is ready
                $math = new EvalMath;
                $value = $math->evaluate($formula);
                $attribute->value = round($value, 2);
            }
            //update the score now that we have a value
            $options = LoanProductScoringAttributeOptionValue::where('loan_product_id', $product->id)->where('loan_product_scoring_attribute_id', $attribute->productAttribute->id)->get();
            if ($attribute->productAttribute->option_type === 'range') {
                foreach ($options as $key) {
                    if ($attribute->value >= $key->lower_value && $attribute->value <= $key->upper_value) {
                        $attribute->score = $key->score;
                        //check if score is accepted
                        if (in_array($key->name, $attribute->productAttribute->accept_value)) {
                            $attribute->accepted = 1;
                        }
                    }
                }
            }
            if ($attribute->productAttribute->option_type === 'greater_than_or_less_than') {
                foreach ($options as $key) {
                    if ($key->name === 'Greater Than or Equal To' && $attribute->value >= $attribute->productAttribute->median_value) {
                        $score->score = $key->score;
                        //check if score is accepted
                        if (in_array($key->name, $attribute->productAttribute->accept_value)) {
                            $attribute->accepted = 1;
                        }
                    }
                    if ($key->name === 'Less Than' && $attribute->value < $attribute->productAttribute->median_value) {
                        $attribute->score = $key->score;
                        //check if score is accepted
                        if (in_array($key->name, $attribute->productAttribute->accept_value)) {
                            $attribute->accepted = 1;
                        }
                    }
                }
            }
            $attribute->save();
        }
        //let's update fields of type calculated
        $formulaAttributes = LoanApplicationScore::with(['productAttribute'])
            ->where('loan_application_id', $application->id)
            ->whereHas('scoringAttribute', function (Builder $query) {
                $query->where('field_type', 'calculated');
            })->get();
        $industryType = $client->industryType;
        $ratio = $client->ratio;
        $shareholders = $client->shareholders;
        $porter = $client->porter;
        foreach ($formulaAttributes as $attribute) {

            $ratioScore = 0.00;
            $accepted = 0;
            $maxScore = $attribute->productAttribute->score;
            $minScore = $attribute->productAttribute->min_score;
            //update is ratio
            if ($attribute->productAttribute->is_ratio) {
                $ratioValue = $ratio->{$attribute->productAttribute->system_name};
                $ratioBenchmarkValue = $industryType->{$attribute->productAttribute->system_name};
                $ratioScore = $ratioBenchmarkValue ? ($ratioValue * $maxScore / $ratioBenchmarkValue) : 0;
                if ($ratioScore > $maxScore) {
                    $ratioScore = $maxScore;
                }
                if ($ratioScore < 0) {
                    $ratioScore = 0.00;
                }
            }
            if ($attribute->productAttribute->is_shareholder_analysis) {
                if ($attribute->productAttribute->system_name !== 'blacklisted' && $attribute->productAttribute->system_name !== 'fraud_alert') {
                    $average = round($client->shareholders->average($attribute->productAttribute->system_name));
                    switch ($average) {
                        case 0:
                            $ratioScore = $maxScore;
                            $accepted = 1;
                            break;
                        case 1:
                            $ratioScore = $maxScore * 40 / 100;
                            $accepted = 1;
                            break;
                        case 2:
                            $ratioScore = $maxScore * 20 / 100;
                            $accepted = 1;
                            break;
                        default:
                            $ratioScore = 0;
                            $accepted = 0;
                    }
                } else {
                    if ($shareholders->where($attribute->productAttribute->system_name, 1)->count() > 0) {
                        $attribute->value = 'yes';
                        $ratioScore = 0;
                        $accepted = 0;
                    } else {
                        $attribute->value = 'no';
                        $ratioScore = $maxScore;
                        $accepted = 1;
                    }
                }
            }
            if ($attribute->productAttribute->is_industry_analysis) {
                if ($attribute->productAttribute->system_name === 'porters_five_forces_analysis') {
                    $porter = $porter->grand_total;
                    if ($porter->grand_total < 0) {
                        $ratioScore = 0;
                        $accepted = 0;
                    }
                    if ($porter->grand_total == 0) {
                        $ratioScore = $maxScore * 50 / 100;
                        $accepted = 1;
                    }
                    if ($porter->grand_total > 0) {
                        $ratioScore = $maxScore;
                        $accepted = 1;
                    }
                }
            }
            $attribute->score = $ratioScore;
            $attribute->accepted = $accepted;
            $attribute->save();
        }
        $application->score = LoanApplicationScore::where('loan_application_id', $application->id)->sum('score');
        $application->score_percentage = $application->score * 100 / $product->score;
        $application->save();
        //save linked approval stages
        $stages = LoanApprovalStage::orderBy('stage_position')->orderBy('id')->get();
        foreach ($stages as $stage) {
            $linkedStage = new LoanApplicationLinkedApprovalStage();
            $linkedStage->loan_application_id = $application->id;
            $linkedStage->loan_approval_stage_id = $stage->id;
            $linkedStage->save();
        }
        event(new LoanApplicationCreated($application));
        activity()
            ->performedOn($application)
            ->log('Create Loan Application');
        return redirect()->route('loan_applications.show', $application->id)->with('success', 'Loan application created successfully.');
    }

    /**
     * Display the specified resource.
     *
     * @param LoanApplication $application
     * @return \Inertia\Response
     */
    public function show(LoanApplication $application)
    {
        $application->load(['staff', 'client', 'client.shareholders', 'client.industryType', 'client.ratio', 'product', 'linkedStages', 'linkedStages.stage', 'linkedStages.approver', 'linkedStages.assignedBy', 'currentLinkedStage', 'currentLinkedStage.stage', 'currentLinkedStage.approver', 'currentLinkedStage.assignedBy', 'createdBy']);
        $error = '';
        $message = '';
        if ($application->client->type === 'corporate') {
            if ($application->client->shareholders->where('blacklisted', 1)->count() > 0) {
                $error = 'One of the shareholders is blacklisted, this application should not be approved.<br>';
            }
            if ($application->client->shareholders->where('fraud_alert', 1)->count() > 0) {
                $error .= 'One of the shareholders has a fraud alert, this application should not be approved.';
            }
        }
        $groups = LoanProductScoringAttribute::where('is_group', 1)->where('loan_product_id', $application->product->id)->get();
        // dd($groups);
        $groups->transform(function ($group) use ($application) {
            $attributes = LoanProductScoringAttribute::with(['attribute'])->where('is_group', 0)->where('scoring_attribute_group_id', $group->scoring_attribute_group_id)->where('loan_product_id', $application->product->id)->orderBy('order_position')->get();

            $group->max_total_score = (float)$attributes->sum('score');
            $groupTotalScore = 0;
            $attributes->transform(function ($item) use ($application, &$groupTotalScore) {
                if (!empty($item->attribute)) {
                    $item->attribute->options = $item->options ?: [];
                } else {
                    abort(422, 'A linked scoring attribute with id ' . $item->scoring_attribute_id . ' was deleted');
                }
                //get value
                $score = LoanApplicationScore::where('loan_application_id', $application->id)->where('loan_product_scoring_attribute_id', $item->id)->first();
                if (!empty($score)) {

                    if ($item->attribute->field_type === 'checkbox') {
                        $item->value = json_decode($score->value);
                    } else {
                        $item->value = $score->value;
                    }

                    $item->accepted = $score->accepted;
                    $item->actual_score = $score->score;
                    $item->percentage_score = $item->score > 0 ? round($score->score * 100 / $item->score, 2) : 0;
                    $groupTotalScore = $groupTotalScore + $score->score;
                } else {
                    if ($item->attribute->field_type === 'checkbox') {
                        $item->value = [];
                    } else {
                        $item->value = '';
                    }
                    $item->accepted = false;
                    $item->percentage_score = 0;
                    $item->actual_score = 0;
                }
                return $item;
            });
            $group->total_score = $groupTotalScore;
            $group->total_percentage = $group->max_total_score > 0 ? round($group->total_score * 100 / $group->max_total_score, 2) : 0;
            $group->attributes = $attributes;
            return $group;
        });
        if ($error) {
            session()->flash('error', $error);
        }
        if ($message) {
            session()->flash('success', $message);
        }
        $application->product->scoring_attributes = $groups;
        //check if the authenticated user is a recommender or approver or both

       $auth_roles = Auth::user()->roles->pluck('id');
       $recommendPermission = DB::table('permissions')->where('name', 'loans.applications.recommend')->first();
       $approvePermission = DB::table('permissions')->where('name', 'loans.applications.approve')->first();
       $recommenderAccessRight = DB::table('role_has_permissions')
       ->whereIn('role_id', $auth_roles)
       ->where('permission_id', $recommendPermission->id)
       ->first() ? true : false;
       $approverAccessRight = DB::table('role_has_permissions')
       ->whereIn('role_id', $auth_roles)
       ->where('permission_id', $approvePermission->id)
       ->first() ? true : false;

       $currentUserRoles =  $auth_roles = Auth::user()->roles->pluck('can_reassign')->toArray();
       $canReassignViaRole = in_array(1, $currentUserRoles);
       $reviewerCommentsCount = LoanApplicationComment::where('loan_application_id', $application->id)->count();
    //    dd($reviewerCommentsCount);


        return Inertia::render('LoanApplications/Show', [
            'application' => $application,
            'recommenderAccessRight' => $recommenderAccessRight,
            'approverAccessRight' => $approverAccessRight,
            'canReassignViaRole' =>$canReassignViaRole,
            'reviewerCommentsCount' => $reviewerCommentsCount

        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param LoanApplication $application
     * @return \Inertia\Response
     */
    public function edit(LoanApplication $application)
    {
        $application->load(['staff', 'client', 'product']);
        $groups = LoanProductScoringAttribute::where('is_group', 1)->where('loan_product_id', $application->product->id)->get();
        $groups->transform(function ($group) use ($application) {
            $attributes = LoanProductScoringAttribute::with(['attribute'])->where('is_group', 0)->where('scoring_attribute_group_id', $group->scoring_attribute_group_id)->where('loan_product_id', $application->product->id)->orderBy('order_position')->get();
            $attributes->transform(function ($item) use ($application) {
                if (!empty($item->attribute)) {
                    $item->attribute->options = $item->options ?: [];
                } else {
                    abort(422, 'A linked scoring attribute with id ' . $item->scoring_attribute_id . ' was deleted');
                }
                //get value
                $score = LoanApplicationScore::where('loan_application_id', $application->id)->where('loan_product_scoring_attribute_id', $item->id)->first();
                if (!empty($score)) {
                    if ($item->attribute->field_type === 'checkbox') {
                        $item->value = $score->value ? json_decode($score->value) : [];
                    } else {
                        $item->value = $score->value;
                    }
                } else {
                    if ($item->attribute->field_type === 'checkbox') {
                        $item->value = [];
                    } else {
                        $item->value = '';
                    }
                }
                return $item;
            });
            $group->attributes = $attributes;
            return $group;
        });
        $application->product->form_attributes = $groups;
        return Inertia::render('LoanApplications/Edit', [
            'application' => $application,
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param LoanApplication $application
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, LoanApplication $application)
    {
        $request->validate([
            'amount' => ['required'],
            'date' => ['required'],
        ]);
        $attributes = $request->json('attributes');
        $client = $application->client;
        $client->load(['shareholders', 'industryType', 'ratio']);
        $product = $application->product;
        $application->staff_id = $request->staff_id;
        $application->date = $request->date;
        $application->amount = $request->amount;
        $application->description = $request->description;
        $application->product_description = $request->product_description;
        $application->save();
        //save scores
        LoanApplicationScore::where('loan_application_id', $application->id)->delete();
        foreach ($attributes as $group) {
            foreach ($group['attributes'] as $field) {
                $score = new LoanApplicationScore();
                $score->created_by_id = Auth::id();
                $score->loan_application_id = $application->id;
                $score->scoring_attribute_id = $field['scoring_attribute_id'];
                $score->loan_product_scoring_attribute_id = $field['id'];
                $score->weight = $field['weight'];
                $score->effective_weight = $field['effective_weight'];
                $score->weighted_score = $field['weighted_score'];
                if ($field['attribute']['field_type'] === 'checkbox') {
                    $score->value = json_encode($field['value']);
                } else {
                    $score->value = $field['value'];
                }
                //determine the score
                if ($field['attribute']['field_type'] === 'dropdown' || $field['attribute']['field_type'] === 'radio') {
                    foreach ($field['attribute']['options'] as $key) {
                        if ($key['name'] == $field['value']) {
                            $score->score = $key['score'];
                            //check if score is accepted
                            if (in_array($key['name'], $field['accept_value'])) {
                                $score->accepted = 1;
                            }
                        }
                    }
                } elseif ($field['attribute']['field_type'] === 'checkbox') {
                    $tempScore = 0;
                    $tempAccepted = 0;
                    foreach ($field['value'] as $item) {
                        foreach ($field['attribute']['options'] as $key) {
                            if ($key['name'] == $item) {
                                $tempScore += $key['score'];
                                //check if score is accepted
                                if (in_array($key['name'], $field['accept_value'])) {
                                    $tempAccepted = 1;
                                }
                            }
                        }
                    }
                    $score->score = $tempScore;
                    $score->accepted = $tempAccepted;
                } elseif ($field['attribute']['field_type'] === 'text' || $field['attribute']['field_type'] === 'number') {
                    if ($field['option_type'] === 'range') {
                        foreach ($field['attribute']['options'] as $key) {
                            if ($field['value'] >= $key['lower_value'] && $field['value'] <= $key['upper_value']) {
                                $score->score = $key['score'];
                                //check if score is accepted
                                if (in_array($key['name'], $field['accept_value'])) {
                                    $score->accepted = 1;
                                }
                            }
                        }
                    }
                    if ($field['option_type'] === 'greater_than_or_less_than') {
                        foreach ($field['attribute']['options'] as $key) {
                            if ($key['name'] === 'Greater Than or Equal To' && $field['value'] >= $field['median_value']) {
                                $score->score = $key['score'];
                                //check if score is accepted
                                if (in_array($key['name'], $field['accept_value'])) {
                                    $score->accepted = 1;
                                }
                            }
                            if ($key['name'] === 'Less Than' && $field['value'] < $field['median_value']) {
                                $score->score = $key['score'];
                                //check if score is accepted
                                if (in_array($key['name'], $field['accept_value'])) {
                                    $score->accepted = 1;
                                }
                            }
                        }
                    }
                } else {
                    $score->score = $field['score'];
                }
                $score->save();
            }
        }
        //let's update fields of type formula
        $formulaAttributes = LoanApplicationScore::with(['productAttribute'])
            ->where('loan_application_id', $application->id)
            ->whereHas('scoringAttribute', function (Builder $query) {
                $query->where('field_type', 'formula');
            })->get();
        foreach ($formulaAttributes as $attribute) {
            $formula = $attribute->productAttribute->data;
            preg_match_all(
                '/\{\{field_(\d)+}}/',
                $formula,
                $matches,
                PREG_PATTERN_ORDER
            );
            if (!empty($matches[0])) {
                foreach ($matches[0] as $match) {
                    //find the value of that field
                    $score = LoanApplicationScore::where('loan_product_scoring_attribute_id', explode('_', $match)[1])->where('loan_application_id', $application->id)->first();
                    if (empty($score)) {
                        //throw error
                    }
                    $formula = str_replace($match, $score->value, $formula);
                }
                //at this point our formula is ready
                $math = new EvalMath;
                $value = $math->evaluate($formula);
                $attribute->value = round($value, 2);
            }
            //update the score now that we have a value
            $options = LoanProductScoringAttributeOptionValue::where('loan_product_id', $product->id)->where('loan_product_scoring_attribute_id', $attribute->productAttribute->id)->get();
            if ($attribute->productAttribute->option_type === 'range') {
                foreach ($options as $key) {
                    if ($attribute->value >= $key->lower_value && $attribute->value <= $key->upper_value) {
                        $attribute->score = $key->score;
                        //check if score is accepted
                        if (in_array($key->name, $attribute->productAttribute->accept_value)) {
                            $attribute->accepted = 1;
                        }
                    }
                }
            }
            if ($attribute->productAttribute->option_type === 'greater_than_or_less_than') {
                foreach ($options as $key) {
                    if ($key->name === 'Greater Than or Equal To' && $attribute->value >= $attribute->productAttribute->median_value) {
                        $score->score = $key->score;
                        //check if score is accepted
                        if (in_array($key->name, $attribute->productAttribute->accept_value)) {
                            $attribute->accepted = 1;
                        }
                    }
                    if ($key->name === 'Less Than' && $attribute->value < $attribute->productAttribute->median_value) {
                        $attribute->score = $key->score;
                        //check if score is accepted
                        if (in_array($key->name, $attribute->productAttribute->accept_value)) {
                            $attribute->accepted = 1;
                        }
                    }
                }
            }
            $attribute->save();
        }
        //let's update fields of type calculated
        $formulaAttributes = LoanApplicationScore::with(['productAttribute'])
            ->where('loan_application_id', $application->id)
            ->whereHas('scoringAttribute', function (Builder $query) {
                $query->where('field_type', 'calculated');
            })->get();
        $industryType = $client->industryType;
        $ratio = $client->ratio;
        $shareholders = $client->shareholders;
        $porter = $client->porter;
        foreach ($formulaAttributes as $attribute) {

            $ratioScore = 0.00;
            $accepted = 0;
            $maxScore = $attribute->productAttribute->score;
            $minScore = $attribute->productAttribute->min_score;
            //update is ratio
            if ($attribute->productAttribute->is_ratio) {
                $ratioValue = $ratio->{$attribute->productAttribute->system_name};
                $ratioBenchmarkValue = $industryType->{$attribute->productAttribute->system_name};
                $ratioScore = $ratioBenchmarkValue ? ($ratioValue * $maxScore / $ratioBenchmarkValue) : 0;
                if ($ratioScore > $maxScore) {
                    $ratioScore = $maxScore;
                }
                if ($ratioScore < 0) {
                    $ratioScore = 0.00;
                }
            }
            if ($attribute->productAttribute->is_shareholder_analysis) {
                if ($attribute->productAttribute->system_name !== 'blacklisted' && $attribute->productAttribute->system_name !== 'fraud_alert') {
                    $average = round($client->shareholders->average($attribute->productAttribute->system_name));
                    switch ($average) {
                        case 0:
                            $ratioScore = $maxScore;
                            $accepted = 1;
                            break;
                        case 1:
                            $ratioScore = $maxScore * 40 / 100;
                            $accepted = 1;
                            break;
                        case 2:
                            $ratioScore = $maxScore * 20 / 100;
                            $accepted = 1;
                            break;
                        default:
                            $ratioScore = 0;
                            $accepted = 0;
                    }
                } else {
                    if ($shareholders->where($attribute->productAttribute->system_name, 1)->count() > 0) {
                        $attribute->value = 'yes';
                        $ratioScore = 0;
                        $accepted = 0;
                    } else {
                        $attribute->value = 'no';
                        $ratioScore = $maxScore;
                        $accepted = 1;
                    }
                }
            }
            if ($attribute->productAttribute->is_industry_analysis) {
                if ($attribute->productAttribute->system_name === 'porters_five_forces_analysis') {
                    $porter = $porter->grand_total;
                    if ($porter->grand_total < 0) {
                        $ratioScore = 0;
                        $accepted = 0;
                    }
                    if ($porter->grand_total == 0) {
                        $ratioScore = $maxScore * 50 / 100;
                        $accepted = 1;
                    }
                    if ($porter->grand_total > 0) {
                        $ratioScore = $maxScore;
                        $accepted = 1;
                    }
                }
            }
            $attribute->score = $ratioScore;
            $attribute->accepted = $accepted;
            $attribute->save();
        }
        $application->score = LoanApplicationScore::where('loan_application_id', $application->id)->sum('score');
        $application->score_percentage = $application->score * 100 / $product->score;
        $application->save();
        activity()
            ->performedOn($application)
            ->log('Update Loan Application');
        return redirect()->route('loan_applications.show', $application->id)->with('success', 'Loan application updated successfully.');
    }

    public function changeStatus(Request $request, LoanApplication $application)
    {
        // Load necessary relationships
        $application->load(['linkedStages']);

        // Validate request data
        $request->validate([
            'status' => ['required'],
            'stage_id' => ['required'],
            'description' => ['required'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png', 'max:2048'],
        ]);

        DB::transaction(function () use ($request, $application) {
            $linkedStage = LoanApplicationLinkedApprovalStage::find($request->stage_id);
            $linkedStage->status = $request->status;

            // Update descriptions
            $linkedStage->previous_description = $linkedStage->description ?: $request->description;
            $linkedStage->description = $request->description ?: $linkedStage->description;

            // Handle attachment
            try {
                if ($request->hasFile("attachment")) {
                    $attachment = $request->file("attachment");
                    $filename = $linkedStage->id . '_' . $attachment->getClientOriginalName();
                    $attachmentPath = $attachment->storeAs('LoanAttachments', $filename, 'public');
                    if (!$attachmentPath) {
                        throw new \Exception("File could not be stored.");
                    }
                    $linkedStage->attachment_path = $attachmentPath;
                }
            } catch (\Exception $e) {
                // Log the error for further inspection
                Log::error("File upload error: " . $e->getMessage());
                return back()->withErrors('There was an issue uploading the file.');
            }

            // Handle status changes
            if (in_array($linkedStage->status, ['approved', 'rejected', 'recommend'])) {
                $linkedStage->completed = $linkedStage->status !== 'recommend';
                $linkedStage->stage_finished_at = Carbon::now();

                if (in_array($linkedStage->status, ['approved', 'rejected'])) {
                    $previousStages = LoanApplicationLinkedApprovalStage::where('loan_application_id', $application->id)->get();
                    $approverIds = $previousStages->pluck('approver_id')->push($application->created_by_id);
                    $previousApprovers = User::whereIn('id', $approverIds)->get();

                    foreach ($previousApprovers as $approver) {
                        // Send notification email (assuming sendApprovalNotificationEmail is defined elsewhere)
                        $this->sendApprovalNotificationEmail($application, $approver->email, $linkedStage->status, $approver->name);
                    }
                }
            }

            if ($linkedStage->status === 'in_progress') {
                $linkedStage->stage_started_at = Carbon::now();
            }

            $linkedStage->save();

            // Handle "sent back" status
            if ($linkedStage->status === 'sent_back') {
                $nextStage = $application->linkedStages->where('id', '<', $linkedStage->id)->last();

                if (!empty($nextStage)) {
                    $nextStage->update(['is_current' => 1, 'completed' => 0, 'status' => 'returned']);
                    $application->update(['current_loan_application_approval_stage_id' => $nextStage->id]);
                    $linkedStage->update(['is_current' => 0, 'completed' => 0]);

                    $previousStages = LoanApplicationLinkedApprovalStage::where('loan_application_id', $application->id)->where('id', '<', $linkedStage->id)->get();
                    $array = $previousStages->pluck('approver_id')->push($application->created_by_id);
                    $previousApprovers = User::whereIn('id', $array)->get();

                    foreach ($previousApprovers as $user) {
                        $mailData = [
                            'application' => $application,
                            'to' => $user->email,
                            'message' => 'A loan application has been returned for further review. Please log in to the system to review and take action.'
                        ];
                        Mail::to($user->email)->send(new LoanApplicationApprovalStageReturned($mailData));
                    }
                }
            }

            // Handle "approved" or "recommend" status
            if (in_array($linkedStage->status, ['approved', 'recommend'])) {
                $nextStage = $application->linkedStages->where('id', '>', $linkedStage->id)->first();

                if ($linkedStage->status === 'approved') {
                    $linkedStage->completed = 1;
                    $application->update(['current_loan_application_approval_stage_id' => $linkedStage->id]);
                } elseif (!empty($nextStage) && $linkedStage->status === 'recommend') {
                    $nextStage->update(['is_current' => 1]);
                    $application->update(['current_loan_application_approval_stage_id' => $nextStage->id]);
                    $linkedStage->update(['is_current' => 0]);
                } else {
                    if ($linkedStage->status === 'recommend') {
                        $linkedStage->status = 'approved';
                    }
                    $linkedStage->completed = 1;
                }
            }

            event(new LoanApplicationStatusChanged($linkedStage));
        });

        // Redirect to loan application page with success message
        return redirect()->route('loan_applications.show', $application->id)->with('success', 'Loan updated successfully.');
    }

    public function assignApprover(Request $request, LoanApplication $application)
    {
        $validated = $request->validate([
            'approver_id' => ['required'],
            'stage_id' => ['required'],
            'action' => ['required'],
            'additional_notes' => ['nullable'],
        ]);
        //check if the the previous stage is completed first

        try {
            DB::beginTransaction();

            $linkedStage = LoanApplicationLinkedApprovalStage::find($request->stage_id);
            $linkedStage->approver_id = $request->approver_id;
            $linkedStage->assigned_by_id = Auth::id();
            $linkedStage->stage_received_at = Carbon::now();
            $linkedStage->stage_started_at = Carbon::now();

            //check if the previous stage is completed first
            $previousStage = LoanApplicationLinkedApprovalStage::where('loan_application_id', $application->id)->where('id', '<', $linkedStage->id)->orderBy('id', 'desc')->first();
            if (!empty($previousStage) && !$previousStage->stage_finished_at) {
                abort(422, ' Loan cannot be sent to the next stage until the previous stage is actioned');

            }

            //check if we have current stage
            if (empty($application->currentLinkedStage)) {
                if (LoanApplicationLinkedApprovalStage::where('loan_application_id', $application->id)->orderBy('id')->first()->id === $linkedStage->id && !$linkedStage->is_current) {
                    $linkedStage->is_current = 1;
                    $application->current_loan_application_approval_stage_id = $linkedStage->id;
                    $application->current_stage_status = 'pending';
                }
            }
            $linkedStage->save();
            $application->save();
            if ($linkedStage->wasChanged('approver_id')) {
                // dd('approver changed');
                $linkedStage->approver->notify(new LoanApplicationApprovalStageAssigned($linkedStage));

            }
            if ($linkedStage->wasChanged('is_current') && $linkedStage->is_current) {
                $linkedStage->approver->notify(new LoanApplicationApprovalStageIsCurrent($linkedStage));
            }

            LoanApplicationApprovalStageHistory::create([
                'approver_id' => $validated['approver_id'],
                'stage_id' => $validated['stage_id'],
                'actioned_by' => Auth::id(),
                'action' => $validated['action'],
                'additional_notes' => $validated['additional_notes']
            ]);
            DB::commit();
            return redirect()->route('loan_applications.show', $application->id)->with('success', 'Loan updated successfully.');
        } catch (\Exception $exception) {
            DB::rollback();
            return redirect()->route('loan_applications.show', $application->id)->with('error', 'Something went wrong'. $exception->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param LoanApplication $application
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(LoanApplication $application)
    {
        $application->scores->each->delete();
        $application->delete();
        activity()
            ->performedOn($application)
            ->log('Delete Loan Application');
        return redirect()->route('loan_applications.index')->with('success', 'Loan deleted successfully.');
    }

    public function showComments($id)
    {

        $comments = LoanApplicationComment::with(['loanApplication', 'user', 'commentSection', 'replies', 'replies.user', 'attachments'])
            ->where('loan_application_id', $id)
            ->filter(\request()->only('comment_date', 'comment_type', 'comment_section', 'comment'))
            ->orderBy('created_at', 'desc')
            ->paginate(20);
         $attributeGroups = ScoringAttributeGroup::get(['id', 'name']);
        //  dd($attributeGroups);



        return Inertia::render('LoanApplications/ReviewerComments', [
            'filters' => \request()->all('search', 'client_id', 'loan_product_id', 'province_id', 'branch_id', 'district_id', 'ward_id', 'date_range', 'village_id', 'staff_id', 'status'),
            'comments' => $comments,
            'loanApplicationId' =>$id,
            'attributeGroups' => $attributeGroups
        ]);
    }

    public function fixing()
    {
        $applications = LoanApplication::get();
        foreach ($applications as $application) {

            //get latest linked transaction
            $currentLinkedStages = LoanApplicationLinkedApprovalStage::where('loan_application_id', $application->id)
                ->get();
            foreach ($currentLinkedStages as $stage) {
                if ($stage->status == 'approved') {
                    // Log::info($application->id. 'is approved');

                    if ($application->current_loan_application_approval_stage_id != $stage->id) {

                        $application->update([
                            'current_loan_application_approval_stage_id' => $stage->id
                        ]);
                    }
                }
            }
        }

        return back()->with('success', 'Successfully done');
    }
    public function resendEmail($id)
    {
       $application = LoanApplication::findOrfail($id);



        $application->load(['linkedStages']);
        $linkedStages = $application->linkedStages->pluck('approver_id');

        $previousApprovers = User::whereIn('id', $linkedStages)->get();
            foreach ($previousApprovers as $user) {
                $mailData = [
                    'application' => $application,
                    'to' => $user->email,
                    'message' => 'Loan Application # ' . $application->id . ' has been updated  for further review. Please login to the system to review the application and take the necessary action.',
                    'subject' => 'Updates on Loan Application # ' . $application->id,
                ];
                //send the email
                Mail::to($user->email)->send(new LoanApplicationApprovalStageReturned($mailData));
            }
            //

            $application->update([
                'was_resend' => true
            ]);

        return redirect()->route('loan_applications.show', $application->id)->with('success', 'Email sent successfully.');
    }

    protected function sendApprovalNotificationEmail($application, $recipientEmail, $status, $recipientName)
{
    $mailData = [
        'application' => $application,
        'to' => $recipientEmail,
        'status' => $status,
        'recipientName' => $recipientName,
    ];

    // Send the email
    Mail::to($recipientEmail)->send(new LoanApplicationFinalised($mailData));
}

public function viewLogHistory($id)
{
    $application = LoanApplication::findOrfail($id);
    $loanHistories = $application->linkedStages;
    return Inertia::render('LoanApplications/LoanHistory', [
        'application' => $application,
        'loanHistories' => $loanHistories
    ]);
}
}
