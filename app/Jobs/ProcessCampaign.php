<?php

namespace App\Jobs;

use App\Mail\SendBasicEmail;
use App\Models\CommunicationCampaign;
use App\Models\CommunicationCampaignLog;
use App\Models\CourseMaterial;
use App\Models\Client;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class ProcessCampaign implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $communicationCampaign;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(CommunicationCampaign $communicationCampaign)
    {
        $this->communicationCampaign = $communicationCampaign;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $systemUserID = Setting::where('setting_key', 'system_user')->first()->setting_value;
        $communicationCampaign = $this->communicationCampaign;
        $member_id = $communicationCampaign->member;
        $branch_id = $communicationCampaign->branch_id;
        $sms_gateway_id = $communicationCampaign->sms_gateway_id;
        $user_id = $communicationCampaign->user_id;
        $attachment_type = $communicationCampaign->communication_campaign_attachment_type_id;
        $from_x = $communicationCampaign->from_x;
        $to_y = $communicationCampaign->to_y;
        $province_id = $communicationCampaign->province_id;
        $currency_id = $communicationCampaign->currency_id;
        $district_id = $communicationCampaign->district_id;
        $ward_id = $communicationCampaign->ward_id;
        $village_id = $communicationCampaign->village_id;
        $tutor_id = $communicationCampaign->tutor_id;
        $loan_category_id = $communicationCampaign->loan_category_id;
        $course_category_id = $communicationCampaign->course_category_id;
        $course_id = $communicationCampaign->course_id;
        $overdue_x = $communicationCampaign->overdue_x;
        $overdue_y = $communicationCampaign->overdue_y;
        //active members
        if ($communicationCampaign->communication_campaign_business_rule_id == 1) {
            $members = Client::where('status', 'active')
                ->when($branch_id, function ($query) use ($branch_id) {
                    $query->where('branch_id', $branch_id);
                })
                ->when($province_id, function ($query) use ($province_id) {
                    $query->where('province_id', $province_id);
                })
                ->when($district_id, function ($query) use ($district_id) {
                    $query->where('district_id', $district_id);
                })
                ->when($ward_id, function ($query) use ($ward_id) {
                    $query->where('ward_id', $ward_id);
                })
                ->when($village_id, function ($query) use ($village_id) {
                    $query->where('village_id', $village_id);
                })
                ->get();
            foreach ($members as $member) {
                if ($communicationCampaign->campaign_type === 'sms') {
                    if (!empty($member->mobile)) {
                        $description = template_replace_tags(["body" => $communicationCampaign->description, "member_id" => $member->id]);
                        send_sms($member->mobile, $description, $communicationCampaign->sms_gateway_id);
                        //log sms
                        $communicationCampaignLog = new CommunicationCampaignLog();
                        $communicationCampaignLog->member_id = $member->id;
                        $communicationCampaignLog->communication_campaign_id = $communicationCampaign->id;
                        $communicationCampaignLog->campaign_type = $communicationCampaign->campaign_type;
                        $communicationCampaignLog->description = $description;
                        $communicationCampaignLog->send_to = $member->mobile;
                        $communicationCampaignLog->status = 'sent';
                        $communicationCampaignLog->campaign_name = $communicationCampaign->name;
                        $communicationCampaignLog->save();
                    }
                }
                if ($communicationCampaign->campaign_type === 'email') {
                    if (!empty($member->email)) {
                        $description = template_replace_tags(["body" => $communicationCampaign->description, "member_id" => $member->id]);
                        $subject = $communicationCampaign->subject;
                        Mail::to($member->email)->send(new SendBasicEmail($subject, $description));
                        //log email
                        $communicationCampaignLog = new CommunicationCampaignLog();
                        $communicationCampaignLog->member_id = $member->id;
                        $communicationCampaignLog->communication_campaign_id = $communicationCampaign->id;
                        $communicationCampaignLog->campaign_type = $communicationCampaign->campaign_type;
                        $communicationCampaignLog->description = $description;
                        $communicationCampaignLog->send_to = $member->email;
                        $communicationCampaignLog->status = 'sent';
                        $communicationCampaignLog->campaign_name = $communicationCampaign->name;
                        $communicationCampaignLog->save();
                    }
                }
            }
        }
        //inactive members
        if ($communicationCampaign->communication_campaign_business_rule_id == 2) {
            $members = Client::where('status', 'inactive')
                ->when($branch_id, function ($query) use ($branch_id) {
                    $query->where('branch_id', $branch_id);
                })
                ->when($branch_id, function ($query) use ($branch_id) {
                    $query->where('branch_id', $branch_id);
                })
                ->when($province_id, function ($query) use ($province_id) {
                    $query->where('province_id', $province_id);
                })
                ->when($district_id, function ($query) use ($district_id) {
                    $query->where('district_id', $district_id);
                })
                ->when($ward_id, function ($query) use ($ward_id) {
                    $query->where('ward_id', $ward_id);
                })
                ->when($village_id, function ($query) use ($village_id) {
                    $query->where('village_id', $village_id);
                })
                ->get();
            foreach ($members as $member) {
                if ($communicationCampaign->campaign_type === 'sms') {
                    if (!empty($member->mobile)) {
                        $description = template_replace_tags(["body" => $communicationCampaign->description, "member_id" => $member->id]);
                        send_sms($member->mobile, $description, $communicationCampaign->sms_gateway_id);
                        //log sms
                        $communicationCampaignLog = new CommunicationCampaignLog();
                        $communicationCampaignLog->member_id = $member->id;
                        $communicationCampaignLog->communication_campaign_id = $communicationCampaign->id;
                        $communicationCampaignLog->campaign_type = $communicationCampaign->campaign_type;
                        $communicationCampaignLog->description = $description;
                        $communicationCampaignLog->send_to = $member->mobile;
                        $communicationCampaignLog->status = 'sent';
                        $communicationCampaignLog->campaign_name = $communicationCampaign->name;
                        $communicationCampaignLog->save();
                    }
                }
                if ($communicationCampaign->campaign_type === 'email') {
                    if (!empty($member->email)) {
                        $description = template_replace_tags(["body" => $communicationCampaign->description, "member_id" => $member->id]);
                        $subject = $communicationCampaign->subject;
                        Mail::to($member->email)->send(new SendBasicEmail($subject, $description));
                        //log email
                        $communicationCampaignLog = new CommunicationCampaignLog();
                        $communicationCampaignLog->member_id = $member->id;
                        $communicationCampaignLog->communication_campaign_id = $communicationCampaign->id;
                        $communicationCampaignLog->campaign_type = $communicationCampaign->campaign_type;
                        $communicationCampaignLog->description = $description;
                        $communicationCampaignLog->send_to = $member->email;
                        $communicationCampaignLog->status = 'sent';
                        $communicationCampaignLog->campaign_name = $communicationCampaign->name;
                        $communicationCampaignLog->save();
                    }
                }
            }
        }
        //members with courses
        if ($communicationCampaign->communication_campaign_business_rule_id == 3) {
            $members = Client::where('members.status', 'active')
                ->when($branch_id, function ($query) use ($branch_id) {
                    $query->where('members.branch_id', $branch_id);
                })
                ->when($branch_id, function ($query) use ($branch_id) {
                    $query->where('branch_id', $branch_id);
                })
                ->when($province_id, function ($query) use ($province_id) {
                    $query->where('province_id', $province_id);
                })
                ->when($district_id, function ($query) use ($district_id) {
                    $query->where('district_id', $district_id);
                })
                ->when($ward_id, function ($query) use ($ward_id) {
                    $query->where('ward_id', $ward_id);
                })
                ->when($village_id, function ($query) use ($village_id) {
                    $query->where('village_id', $village_id);
                })
                ->whereHas('courses', function ($query) use ($course_category_id,$course_id,$from_x, $to_y) {
                    $query->when($course_id, function ($query) use ($course_id) {
                        $query->where('course_id', $course_id);
                    });
                    $query->when($course_category_id, function ($query) use ($course_category_id) {
                        $query->where('course_category_id', $course_category_id);
                    });
                    $query->when($from_x, function ($query) use ($from_x, $to_y) {
                        $query->whereBetween('date', [Carbon::today()->subDays($from_x)->format('Y-m-d'), Carbon::today()->subDays($to_y)->format('Y-m-d')]);
                    });
                })
                ->get();
            foreach ($members as $member) {
                if ($communicationCampaign->campaign_type === 'sms') {
                    if (!empty($member->mobile)) {
                        $description = template_replace_tags(["body" => $communicationCampaign->description, "member_id" => $member->id]);
                        send_sms($member->mobile, $description, $communicationCampaign->sms_gateway_id);
                        //log sms
                        $communicationCampaignLog = new CommunicationCampaignLog();
                        $communicationCampaignLog->member_id = $member->id;
                        $communicationCampaignLog->communication_campaign_id = $communicationCampaign->id;
                        $communicationCampaignLog->campaign_type = $communicationCampaign->campaign_type;
                        $communicationCampaignLog->description = $description;
                        $communicationCampaignLog->send_to = $member->mobile;
                        $communicationCampaignLog->status = 'sent';
                        $communicationCampaignLog->campaign_name = $communicationCampaign->name;
                        $communicationCampaignLog->save();
                    }
                }
                if ($communicationCampaign->campaign_type === 'email') {
                    if (!empty($member->email)) {
                        $description = template_replace_tags(["body" => $communicationCampaign->description, "member_id" => $member->id]);
                        $subject = $communicationCampaign->subject;
                        Mail::to($member->email)->send(new SendBasicEmail($subject, $description));
                        //log email
                        $communicationCampaignLog = new CommunicationCampaignLog();
                        $communicationCampaignLog->member_id = $member->id;
                        $communicationCampaignLog->communication_campaign_id = $communicationCampaign->id;
                        $communicationCampaignLog->campaign_type = $communicationCampaign->campaign_type;
                        $communicationCampaignLog->description = $description;
                        $communicationCampaignLog->send_to = $member->email;
                        $communicationCampaignLog->status = 'sent';
                        $communicationCampaignLog->campaign_name = $communicationCampaign->name;
                        $communicationCampaignLog->save();
                    }
                }
            }
        }
        //members without courses
        if ($communicationCampaign->communication_campaign_business_rule_id == 4) {
            $members = Client::where('members.status', 'active')
                ->when($branch_id, function ($query) use ($branch_id) {
                    $query->where('members.branch_id', $branch_id);
                })
                ->when($branch_id, function ($query) use ($branch_id) {
                    $query->where('branch_id', $branch_id);
                })
                ->when($province_id, function ($query) use ($province_id) {
                    $query->where('province_id', $province_id);
                })
                ->when($district_id, function ($query) use ($district_id) {
                    $query->where('district_id', $district_id);
                })
                ->when($ward_id, function ($query) use ($ward_id) {
                    $query->where('ward_id', $ward_id);
                })
                ->when($village_id, function ($query) use ($village_id) {
                    $query->where('village_id', $village_id);
                })
                ->whereDoesntHave('courses', function ($query) use ($course_category_id,$course_id,$from_x, $to_y) {
                    $query->when($course_id, function ($query) use ($course_id) {
                        $query->where('course_id', $course_id);
                    });
                    $query->when($course_category_id, function ($query) use ($course_category_id) {
                        $query->where('course_category_id', $course_category_id);
                    });
                    $query->when($from_x, function ($query) use ($from_x, $to_y) {
                        $query->whereBetween('date', [Carbon::today()->subDays($from_x)->format('Y-m-d'), Carbon::today()->subDays($to_y)->format('Y-m-d')]);
                    });
                })
                ->get();
            foreach ($members as $member) {
                if ($communicationCampaign->campaign_type === 'sms') {
                    if (!empty($member->mobile)) {
                        $description = template_replace_tags(["body" => $communicationCampaign->description, "member_id" => $member->id]);
                        send_sms($member->mobile, $description, $communicationCampaign->sms_gateway_id);
                        //log sms
                        $communicationCampaignLog = new CommunicationCampaignLog();
                        $communicationCampaignLog->member_id = $member->id;
                        $communicationCampaignLog->communication_campaign_id = $communicationCampaign->id;
                        $communicationCampaignLog->campaign_type = $communicationCampaign->campaign_type;
                        $communicationCampaignLog->description = $description;
                        $communicationCampaignLog->send_to = $member->mobile;
                        $communicationCampaignLog->status = 'sent';
                        $communicationCampaignLog->campaign_name = $communicationCampaign->name;
                        $communicationCampaignLog->save();
                    }
                }
                if ($communicationCampaign->campaign_type === 'email') {
                    if (!empty($member->email)) {
                        $description = template_replace_tags(["body" => $communicationCampaign->description, "member_id" => $member->id]);
                        $subject = $communicationCampaign->subject;
                        Mail::to($member->email)->send(new SendBasicEmail($subject, $description));
                        //log email
                        $communicationCampaignLog = new CommunicationCampaignLog();
                        $communicationCampaignLog->member_id = $member->id;
                        $communicationCampaignLog->communication_campaign_id = $communicationCampaign->id;
                        $communicationCampaignLog->campaign_type = $communicationCampaign->campaign_type;
                        $communicationCampaignLog->description = $description;
                        $communicationCampaignLog->send_to = $member->email;
                        $communicationCampaignLog->status = 'sent';
                        $communicationCampaignLog->campaign_name = $communicationCampaign->name;
                        $communicationCampaignLog->save();
                    }
                }
            }
        }
        //members with loans
        if ($communicationCampaign->communication_campaign_business_rule_id == 5) {
            $members = Client::where('members.status', 'active')
                ->when($branch_id, function ($query) use ($branch_id) {
                    $query->where('members.branch_id', $branch_id);
                })
                ->when($branch_id, function ($query) use ($branch_id) {
                    $query->where('branch_id', $branch_id);
                })
                ->when($province_id, function ($query) use ($province_id) {
                    $query->where('province_id', $province_id);
                })
                ->when($district_id, function ($query) use ($district_id) {
                    $query->where('district_id', $district_id);
                })
                ->when($ward_id, function ($query) use ($ward_id) {
                    $query->where('ward_id', $ward_id);
                })
                ->when($village_id, function ($query) use ($village_id) {
                    $query->where('village_id', $village_id);
                })
                ->whereHas('loans', function ($query) use ($loan_category_id,$from_x, $to_y) {
                    $query->when($loan_category_id, function ($query) use ($loan_category_id) {
                        $query->where('loan_category_id', $loan_category_id);
                    });
                    $query->when($from_x, function ($query) use ($from_x, $to_y) {
                        $query->whereBetween('date', [Carbon::today()->subDays($from_x)->format('Y-m-d'), Carbon::today()->subDays($to_y)->format('Y-m-d')]);
                    });
                })
                ->get();
            foreach ($members as $member) {
                if ($communicationCampaign->campaign_type === 'sms') {
                    if (!empty($member->mobile)) {
                        $description = template_replace_tags(["body" => $communicationCampaign->description, "member_id" => $member->id]);
                        send_sms($member->mobile, $description, $communicationCampaign->sms_gateway_id);
                        //log sms
                        $communicationCampaignLog = new CommunicationCampaignLog();
                        $communicationCampaignLog->member_id = $member->id;
                        $communicationCampaignLog->communication_campaign_id = $communicationCampaign->id;
                        $communicationCampaignLog->campaign_type = $communicationCampaign->campaign_type;
                        $communicationCampaignLog->description = $description;
                        $communicationCampaignLog->send_to = $member->mobile;
                        $communicationCampaignLog->status = 'sent';
                        $communicationCampaignLog->campaign_name = $communicationCampaign->name;
                        $communicationCampaignLog->save();
                    }
                }
                if ($communicationCampaign->campaign_type === 'email') {
                    if (!empty($member->email)) {
                        $description = template_replace_tags(["body" => $communicationCampaign->description, "member_id" => $member->id]);
                        $subject = $communicationCampaign->subject;
                        Mail::to($member->email)->send(new SendBasicEmail($subject, $description));
                        //log email
                        $communicationCampaignLog = new CommunicationCampaignLog();
                        $communicationCampaignLog->member_id = $member->id;
                        $communicationCampaignLog->communication_campaign_id = $communicationCampaign->id;
                        $communicationCampaignLog->campaign_type = $communicationCampaign->campaign_type;
                        $communicationCampaignLog->description = $description;
                        $communicationCampaignLog->send_to = $member->email;
                        $communicationCampaignLog->status = 'sent';
                        $communicationCampaignLog->campaign_name = $communicationCampaign->name;
                        $communicationCampaignLog->save();
                    }
                }
            }
        }
        //members without loans
        if ($communicationCampaign->communication_campaign_business_rule_id == 6) {
            $members = Client::where('members.status', 'active')
                ->when($branch_id, function ($query) use ($branch_id) {
                    $query->where('branch_id', $branch_id);
                })
                ->when($province_id, function ($query) use ($province_id) {
                    $query->where('province_id', $province_id);
                })
                ->when($district_id, function ($query) use ($district_id) {
                    $query->where('district_id', $district_id);
                })
                ->when($ward_id, function ($query) use ($ward_id) {
                    $query->where('ward_id', $ward_id);
                })
                ->when($village_id, function ($query) use ($village_id) {
                    $query->where('village_id', $village_id);
                })
                ->whereDoesntHave('loans', function ($query) use ($loan_category_id,$from_x, $to_y) {
                    $query->when($loan_category_id, function ($query) use ($loan_category_id) {
                        $query->where('loan_category_id', $loan_category_id);
                    });
                    $query->when($from_x, function ($query) use ($from_x, $to_y) {
                        $query->whereBetween('date', [Carbon::today()->subDays($from_x)->format('Y-m-d'), Carbon::today()->subDays($to_y)->format('Y-m-d')]);
                    });
                })
                ->get();
            foreach ($members as $member) {
                if ($communicationCampaign->campaign_type === 'sms') {
                    if (!empty($member->mobile)) {
                        $description = template_replace_tags(["body" => $communicationCampaign->description, "member_id" => $member->id]);
                        send_sms($member->mobile, $description, $communicationCampaign->sms_gateway_id);
                        //log sms
                        $communicationCampaignLog = new CommunicationCampaignLog();
                        $communicationCampaignLog->member_id = $member->id;
                        $communicationCampaignLog->communication_campaign_id = $communicationCampaign->id;
                        $communicationCampaignLog->campaign_type = $communicationCampaign->campaign_type;
                        $communicationCampaignLog->description = $description;
                        $communicationCampaignLog->send_to = $member->mobile;
                        $communicationCampaignLog->status = 'sent';
                        $communicationCampaignLog->campaign_name = $communicationCampaign->name;
                        $communicationCampaignLog->save();
                    }
                }
                if ($communicationCampaign->campaign_type === 'email') {
                    if (!empty($member->email)) {
                        $description = template_replace_tags(["body" => $communicationCampaign->description, "member_id" => $member->id]);
                        $subject = $communicationCampaign->subject;
                        Mail::to($member->email)->send(new SendBasicEmail($subject, $description));
                        //log email
                        $communicationCampaignLog = new CommunicationCampaignLog();
                        $communicationCampaignLog->member_id = $member->id;
                        $communicationCampaignLog->communication_campaign_id = $communicationCampaign->id;
                        $communicationCampaignLog->campaign_type = $communicationCampaign->campaign_type;
                        $communicationCampaignLog->description = $description;
                        $communicationCampaignLog->send_to = $member->email;
                        $communicationCampaignLog->status = 'sent';
                        $communicationCampaignLog->campaign_name = $communicationCampaign->name;
                        $communicationCampaignLog->save();
                    }
                }
            }
        }
        //active users
        if ($communicationCampaign->communication_campaign_business_rule_id == 7) {
            $users = User::where('active', 1)
                ->when($branch_id, function ($query) use ($branch_id) {
                    $query->where('branch_id', $branch_id);
                })
                ->when($province_id, function ($query) use ($province_id) {
                    $query->where('province_id', $province_id);
                })
                ->when($district_id, function ($query) use ($district_id) {
                    $query->where('district_id', $district_id);
                })
                ->when($ward_id, function ($query) use ($ward_id) {
                    $query->where('ward_id', $ward_id);
                })
                ->when($village_id, function ($query) use ($village_id) {
                    $query->where('village_id', $village_id);
                })
                ->get();
            foreach ($users as $user) {
                if ($communicationCampaign->campaign_type === 'sms') {
                    if (!empty($user->mobile)) {
                        $description = template_replace_tags(["body" => $communicationCampaign->description, "user_id" => $user->id]);
                        send_sms($user->mobile, $description, $communicationCampaign->sms_gateway_id);
                        //log sms
                        $communicationCampaignLog = new CommunicationCampaignLog();
                        $communicationCampaignLog->communication_campaign_id = $communicationCampaign->id;
                        $communicationCampaignLog->campaign_type = $communicationCampaign->campaign_type;
                        $communicationCampaignLog->description = $description;
                        $communicationCampaignLog->send_to = $user->mobile;
                        $communicationCampaignLog->status = 'sent';
                        $communicationCampaignLog->campaign_name = $communicationCampaign->name;
                        $communicationCampaignLog->save();
                    }
                }
                if ($communicationCampaign->campaign_type === 'email') {
                    if (!empty($user->email)) {
                        $description = template_replace_tags(["body" => $communicationCampaign->description, "user_id" => $user->id]);
                        $subject = $communicationCampaign->subject;
                        Mail::to($user->email)->send(new SendBasicEmail($subject, $description));
                        //log email
                        $communicationCampaignLog = new CommunicationCampaignLog();
                        $communicationCampaignLog->communication_campaign_id = $communicationCampaign->id;
                        $communicationCampaignLog->campaign_type = $communicationCampaign->campaign_type;
                        $communicationCampaignLog->description = $description;
                        $communicationCampaignLog->send_to = $user->email;
                        $communicationCampaignLog->status = 'sent';
                        $communicationCampaignLog->campaign_name = $communicationCampaign->name;
                        $communicationCampaignLog->save();
                    }
                }
            }
        }
        //members with birthdays
        if ($communicationCampaign->communication_campaign_business_rule_id == 8) {
            $members = Client::where('status', 'active')
                ->whereRaw('DATE_FORMAT(dob, "%m-%d") = ?', [Carbon::now()->format('m-d')])
                ->when($branch_id, function ($query) use ($branch_id) {
                    $query->where('branch_id', $branch_id);
                })
                ->when($province_id, function ($query) use ($province_id) {
                    $query->where('province_id', $province_id);
                })
                ->when($district_id, function ($query) use ($district_id) {
                    $query->where('district_id', $district_id);
                })
                ->when($ward_id, function ($query) use ($ward_id) {
                    $query->where('ward_id', $ward_id);
                })
                ->when($village_id, function ($query) use ($village_id) {
                    $query->where('village_id', $village_id);
                })
                ->get();
            foreach ($members as $member) {
                if ($communicationCampaign->campaign_type === 'sms') {
                    if (!empty($member->mobile)) {
                        $description = template_replace_tags(["body" => $communicationCampaign->description, "member_id" => $member->id]);
                        send_sms($member->mobile, $description, $communicationCampaign->sms_gateway_id);
                        //log sms
                        $communicationCampaignLog = new CommunicationCampaignLog();
                        $communicationCampaignLog->member_id = $member->id;
                        $communicationCampaignLog->communication_campaign_id = $communicationCampaign->id;
                        $communicationCampaignLog->campaign_type = $communicationCampaign->campaign_type;
                        $communicationCampaignLog->description = $description;
                        $communicationCampaignLog->send_to = $member->mobile;
                        $communicationCampaignLog->status = 'sent';
                        $communicationCampaignLog->campaign_name = $communicationCampaign->name;
                        $communicationCampaignLog->save();
                    }
                }
                if ($communicationCampaign->campaign_type === 'email') {
                    if (!empty($member->email)) {
                        $description = template_replace_tags(["body" => $communicationCampaign->description, "member_id" => $member->id]);
                        $subject = $communicationCampaign->subject;
                        Mail::to($member->email)->send(new SendBasicEmail($subject, $description));
                        //log email
                        $communicationCampaignLog = new CommunicationCampaignLog();
                        $communicationCampaignLog->member_id = $member->id;
                        $communicationCampaignLog->communication_campaign_id = $communicationCampaign->id;
                        $communicationCampaignLog->campaign_type = $communicationCampaign->campaign_type;
                        $communicationCampaignLog->description = $description;
                        $communicationCampaignLog->send_to = $member->email;
                        $communicationCampaignLog->status = 'sent';
                        $communicationCampaignLog->campaign_name = $communicationCampaign->name;
                        $communicationCampaignLog->save();
                    }
                }
            }
        }
        //member direct message
        if ($communicationCampaign->communication_campaign_business_rule_id == 9) {
            $member = Client::find($communicationCampaign->member_id);
            if ($member) {
                if ($communicationCampaign->campaign_type === 'sms') {
                    if (!empty($member->mobile)) {
                        $description = template_replace_tags(["body" => $communicationCampaign->description, "member_id" => $member->id]);
                        send_sms($member->mobile, $description, $communicationCampaign->sms_gateway_id);
                        //log sms
                        $communicationCampaignLog = new CommunicationCampaignLog();
                        $communicationCampaignLog->member_id = $member->id;
                        $communicationCampaignLog->communication_campaign_id = $communicationCampaign->id;
                        $communicationCampaignLog->campaign_type = $communicationCampaign->campaign_type;
                        $communicationCampaignLog->description = $description;
                        $communicationCampaignLog->send_to = $member->mobile;
                        $communicationCampaignLog->status = 'sent';
                        $communicationCampaignLog->campaign_name = $communicationCampaign->name;
                        $communicationCampaignLog->save();
                    }
                }
                if ($communicationCampaign->campaign_type === 'email') {
                    if (!empty($member->email)) {
                        $description = template_replace_tags(["body" => $communicationCampaign->description, "member_id" => $member->id]);
                        $subject = $communicationCampaign->subject;
                        Mail::to($member->email)->send(new SendBasicEmail($subject, $description));
                        //log email
                        $communicationCampaignLog = new CommunicationCampaignLog();
                        $communicationCampaignLog->member_id = $member->id;
                        $communicationCampaignLog->communication_campaign_id = $communicationCampaign->id;
                        $communicationCampaignLog->campaign_type = $communicationCampaign->campaign_type;
                        $communicationCampaignLog->description = $description;
                        $communicationCampaignLog->send_to = $member->email;
                        $communicationCampaignLog->status = 'sent';
                        $communicationCampaignLog->campaign_name = $communicationCampaign->name;
                        $communicationCampaignLog->save();
                    }
                }
            }
        }
        //user direct message
        if ($communicationCampaign->communication_campaign_business_rule_id == 10) {
            $user = User::find($communicationCampaign->user_id);
            if ($user) {
                if ($communicationCampaign->campaign_type === 'sms') {
                    if (!empty($user->mobile)) {
                        $description = template_replace_tags(["body" => $communicationCampaign->description, "user_id" => $user->id]);
                        send_sms($user->mobile, $description, $communicationCampaign->sms_gateway_id);
                        //log sms
                        $communicationCampaignLog = new CommunicationCampaignLog();
                        $communicationCampaignLog->communication_campaign_id = $communicationCampaign->id;
                        $communicationCampaignLog->campaign_type = $communicationCampaign->campaign_type;
                        $communicationCampaignLog->description = $description;
                        $communicationCampaignLog->send_to = $user->mobile;
                        $communicationCampaignLog->status = 'sent';
                        $communicationCampaignLog->campaign_name = $communicationCampaign->name;
                        $communicationCampaignLog->save();
                    }
                }
                if ($communicationCampaign->campaign_type === 'email') {
                    if (!empty($user->email)) {
                        $description = template_replace_tags(["body" => $communicationCampaign->description, "user_id" => $user->id]);
                        $subject = $communicationCampaign->subject;
                        Mail::to($user->email)->send(new SendBasicEmail($subject, $description));
                        //log email
                        $communicationCampaignLog = new CommunicationCampaignLog();
                        $communicationCampaignLog->communication_campaign_id = $communicationCampaign->id;
                        $communicationCampaignLog->campaign_type = $communicationCampaign->campaign_type;
                        $communicationCampaignLog->description = $description;
                        $communicationCampaignLog->send_to = $user->email;
                        $communicationCampaignLog->status = 'sent';
                        $communicationCampaignLog->campaign_name = $communicationCampaign->name;
                        $communicationCampaignLog->save();
                    }
                }
            }
        }
        if (!$communicationCampaign->recurring) {
            $communicationCampaign->status = 'done';
            $communicationCampaign->save();
        }

    }
}
