<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessCampaign;
use App\Models\Branch;
use App\Models\CommunicationCampaign;
use App\Models\CommunicationCampaignAttachmentType;
use App\Models\CommunicationCampaignBusinessRule;
use App\Models\CommunicationTemplate;
use App\Models\CourseCategory;
use App\Models\CourseMaterial;
use App\Models\District;
use App\Models\LoanProduct;
use App\Models\LoanProductCategory;
use App\Models\Province;
use App\Models\SmsGateway;
use App\Models\User;
use App\Models\Village;
use App\Models\Ward;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;


class CommunicationCampaignController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth']);
        $this->middleware(['permission:communication.campaigns.index'])->only(['index', 'show']);
        $this->middleware(['permission:communication.campaigns.create'])->only(['create', 'store']);
        $this->middleware(['permission:communication.campaigns.update'])->only(['edit', 'update']);
        $this->middleware(['permission:communication.campaigns.destroy'])->only(['destroy']);

    }

    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index(Request $request)
    {
        $communicationCampaigns = CommunicationCampaign::filter(\request()->only('search', 'communication_campaign_business_rule_id', 'campaign_type', 'trigger_type', 'status'))
            ->with('branch')
            ->with('createdBy')
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        return Inertia::render('CommunicationCampaigns/Index', [
            'filters' => \request()->all('search', 'communication_campaign_business_rule_id', 'campaign_type', 'trigger_type', 'status'),
            'communicationCampaigns' => $communicationCampaigns,
        ]);

    }


    /**
     * Show the form for creating a new resource.
     * @return \Inertia\Response
     */
    public function create()
    {
        return Inertia::render('CommunicationCampaigns/Create', [
            'communicationCampaignBusinessRules' => CommunicationCampaignBusinessRule::where('active', 1)->get(),
            'communicationCampaignAttachmentTypes' => CommunicationCampaignAttachmentType::where('active', 1)->get(),
            'smsGateways' => SmsGateway::where('active', 1)->get()->map(function ($item) {
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
            'products' => LoanProduct::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'provinces' => Province::get()->transform(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
            'districts' => District::get()->transform(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                    'province_id' => $item->province_id,
                ];
            }),
            'wards' => Ward::get()->transform(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                    'district_id' => $item->district_id,
                ];
            }),
            'villages' => Village::get()->transform(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                    'ward_id' => $item->ward_id,
                ];
            }),
            'templates' => CommunicationTemplate::where('is_system', 0)->get(),
            'client_id' => \request('client_id'),
            'campaign_type' => \request('campaign_type'),
            'user_id' => \request('user_id'),
            'course_id' => \request('course_id'),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required'],
            'sms_gateway_id' => ['required_if:campaign_type,sms'],
            'scheduled_date' => ['required_if:trigger_type,schedule'],
            'scheduled_time' => ['required_if:trigger_type,schedule'],
            'client_id' => ['required_if:communication_campaign_business_rule_id,9'],
            'user_id' => ['required_if:communication_campaign_business_rule_id,10'],
            'description' => ['required'],
            'status' => ['required_if:trigger_type,schedule,triggered'],
        ]);
        $communicationCampaign = new CommunicationCampaign();
        $communicationCampaign->created_by_id = Auth::id();
        $communicationCampaign->subject = $request->subject;
        $communicationCampaign->name = $request->name;
        $communicationCampaign->sms_gateway_id = $request->sms_gateway_id;
        $communicationCampaign->communication_campaign_business_rule_id = $request->communication_campaign_business_rule_id;
        $communicationCampaign->communication_campaign_attachment_type_id = $request->communication_campaign_attachment_type_id;
        $communicationCampaign->branch_id = $request->branch_id;
        $communicationCampaign->client_id = $request->client_id;
        $communicationCampaign->loan_product_id = $request->loan_product_id;
        $communicationCampaign->province_id = $request->province_id;
        $communicationCampaign->district_id = $request->district_id;
        $communicationCampaign->ward_id = $request->ward_id;
        $communicationCampaign->village_id = $request->village_id;
        $communicationCampaign->user_id = $request->user_id;
        $communicationCampaign->currency_id = $request->currency_id;
        $communicationCampaign->campaign_type = $request->campaign_type;
        $communicationCampaign->trigger_type = $request->trigger_type;
        if ($communicationCampaign->trigger_type === 'schedule') {
            $communicationCampaign->scheduled_date = $request->scheduled_date;
            $communicationCampaign->scheduled_time = $request->scheduled_time;
        }
        $communicationCampaign->recurring = $request->recurring ? 1 : 0;
        if ($request->recurring) {
            $communicationCampaign->recur_frequency = $request->recur_frequency;
            $communicationCampaign->recur_start_date = $request->recur_start_date;
            $communicationCampaign->recur_end_date = $request->recur_end_date;
            $communicationCampaign->recur_type = $request->recur_type;
            $communicationCampaign->selected_days = $request->selected_days;
        }
        $communicationCampaign->from_x = $request->from_x;
        $communicationCampaign->to_y = $request->to_y;
        $communicationCampaign->status = $request->trigger_type === 'direct' ? 'done' : $request->status;
        $communicationCampaign->description = $request->description;
        $communicationCampaign->save();
        if ($request->trigger_type === 'direct') {
            ProcessCampaign::dispatch($communicationCampaign);
        }
        activity()->on($communicationCampaign)
            ->withProperties(['id' => $communicationCampaign->id])
            ->log('Create Communication Campaign');
        return redirect()->route('communication.campaigns.index')->with('success', 'Campaign saved successfully.');
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return \Inertia\Response
     */
    public function show(CommunicationCampaign $communicationCampaign)
    {
        $communicationCampaign->load(['communicationCampaignBusinessRule']);
        return Inertia::render('CommunicationCampaigns/Show', [
            'communicationCampaign' => $communicationCampaign,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     * @param CommunicationCampaign $communicationCampaign
     * @return \Inertia\Response
     */
    public function edit(CommunicationCampaign $communicationCampaign)
    {
        return Inertia::render('CommunicationCampaigns/Edit', [
            'communicationCampaign' => $communicationCampaign,
            'communicationCampaignBusinessRules' => CommunicationCampaignBusinessRule::where('active', 1)->get(),
            'communicationCampaignAttachmentTypes' => CommunicationCampaignAttachmentType::where('active', 1)->get(),
            'smsGateways' => SmsGateway::where('active', 1)->get()->map(function ($item) {
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
            'products' => LoanProduct::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
            'provinces' => Province::get()->transform(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                ];
            }),
            'districts' => District::get()->transform(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                    'province_id' => $item->province_id,
                ];
            }),
            'wards' => Ward::get()->transform(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                    'district_id' => $item->district_id,
                ];
            }),
            'villages' => Village::get()->transform(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name,
                    'ward_id' => $item->ward_id,
                ];
            }),
            'templates' => CommunicationTemplate::where('is_system', 0)->get(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param CommunicationCampaign $communicationCampaign
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, CommunicationCampaign $communicationCampaign)
    {
        $request->validate([
            'name' => ['required'],
            'sms_gateway_id' => ['required_if:campaign_type,sms'],
            'scheduled_date' => ['required_if:trigger_type,schedule'],
            'scheduled_time' => ['required_if:trigger_type,schedule'],
            'client_id' => ['required_if:communication_campaign_business_rule_id,9'],
            'user_id' => ['required_if:communication_campaign_business_rule_id,10'],
            'description' => ['required'],
            'status' => ['required_if:trigger_type,schedule,triggered'],
        ]);
        $communicationCampaign->subject = $request->subject;
        $communicationCampaign->name = $request->name;
        $communicationCampaign->sms_gateway_id = $request->sms_gateway_id;
        $communicationCampaign->communication_campaign_business_rule_id = $request->communication_campaign_business_rule_id;
        $communicationCampaign->communication_campaign_attachment_type_id = $request->communication_campaign_attachment_type_id;
        $communicationCampaign->branch_id = $request->branch_id;
        $communicationCampaign->client_id = $request->client_id;
        $communicationCampaign->loan_product_id = $request->loan_product_id;
        $communicationCampaign->province_id = $request->province_id;
        $communicationCampaign->district_id = $request->district_id;
        $communicationCampaign->ward_id = $request->ward_id;
        $communicationCampaign->village_id = $request->village_id;
        $communicationCampaign->user_id = $request->user_id;
        $communicationCampaign->currency_id = $request->currency_id;
        $communicationCampaign->campaign_type = $request->campaign_type;
        $communicationCampaign->trigger_type = $request->trigger_type;
        if ($communicationCampaign->trigger_type === 'schedule') {
            $communicationCampaign->scheduled_date = $request->scheduled_date;
            $communicationCampaign->scheduled_time = $request->scheduled_time;
        }
        $communicationCampaign->recurring = $request->recurring ? 1 : 0;
        if ($request->recurring) {
            $communicationCampaign->recur_frequency = $request->recur_frequency;
            $communicationCampaign->recur_start_date = $request->recur_start_date;
            $communicationCampaign->recur_end_date = $request->recur_end_date;
            $communicationCampaign->recur_type = $request->recur_type;
            $communicationCampaign->selected_days = $request->selected_days;
        }
        $communicationCampaign->from_x = $request->from_x;
        $communicationCampaign->to_y = $request->to_y;
        $communicationCampaign->status = $request->trigger_type === 'direct' ? 'done' : $request->status;
        $communicationCampaign->description = $request->description;
        $communicationCampaign->save();
        activity()->on($communicationCampaign)
            ->withProperties(['id' => $communicationCampaign->id])
            ->log('Update Communication Campaign');
        return redirect()->route('communication.campaigns.index')->with('success', 'Campaign updated successfully.');

    }

    /**
     * Remove the specified resource from storage.
     * @param CommunicationCampaign $communicationCampaign
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(CommunicationCampaign $communicationCampaign)
    {
        $communicationCampaign->delete();
        activity()->on($communicationCampaign)
            ->withProperties(['id' => $communicationCampaign->id])
            ->log('Delete Communication Campaign');
        return redirect()->route('communication.campaigns.index')->with('success', 'Campaign deleted successfully.');

    }
}
