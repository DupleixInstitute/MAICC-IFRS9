<?php

use App\Models\Course;
use App\Models\Event;
use App\Models\Branch;
use App\Models\CourseMaterial;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\LoanApplication;
use App\Models\Client;
use App\Models\Setting;
use App\Models\SmsGateway;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tjmugova\BluedotSms\BluedotSms;

if (!function_exists('generate_reference')) {
    function generate_reference($id = '', $setting_key = 'invoice_reference_prefix')
    {
        $prefix = '';
        if ($setting_key) {
            if ($setting = Setting::where('setting_key', $setting_key)->first()) {
                $prefix = $setting->setting_value;
            }
        }
        if (strlen($id) < 2) {
            $sequence_number = '00' . $id;
        } elseif (strlen($id) < 3) {
            $sequence_number = '0' . $id;
        } else {
            $sequence_number = $id;
        }
        $random_number = uniqid('', false);
        $reference_format = Setting::where('setting_key', 'invoice_reference_format')->first()->setting_value;
        if ($reference_format == 'Sequence Number') {
            return $prefix . $sequence_number;
        } elseif ($reference_format == 'Random Number') {
            return $prefix . $random_number;
        } elseif ($reference_format == 'YEAR/Sequence Number (SL/2014/001)') {
            return $prefix . date("Y") . '/' . $sequence_number;
        } elseif ($reference_format == 'YEAR/MONTH/Sequence Number (SL/2014/08/001)') {
            return $prefix . date("Y") . '/' . date("m") . '/' . $sequence_number;
        } else {
            return $id;
        }
    }
}
if (!function_exists('send_sms')) {
    /**
     * Send sms to an HTTP API using curl
     * @param string $to The number to send the message to
     * @param string $msg The message to be sent
     * @param null $gateway_id Provide gateway id
     */
    function send_sms($to, $msg, $gateway_id = null)
    {
        if (!$gateway_id) {
            $active_sms_gateway = SmsGateway::find(Setting::where('setting_key',
                'active_sms_gateway')->first()->setting_value);
        } else {
            $active_sms_gateway = SmsGateway::find($gateway_id);
        }
        if ($active_sms_gateway) {
            if ($active_sms_gateway->is_system) {
                if ($active_sms_gateway->system_name === 'bluedot') {
                    $bluedot = app()->make(BluedotSms::class);
                    $response = $bluedot->sendMessage([
                        'to' => $to,
                        'text' => $msg,
                    ]);
                }
            } else {
                $append = "&";
                $append .= $active_sms_gateway->to_name . "=" . $to;
                $append .= "&" . $active_sms_gateway->msg_name . "=" . urlencode($msg);
                $url = $active_sms_gateway->url . $append;
                //send sms here
                $response = Http::get($url);

            }

        }

    }
}
if (!function_exists('template_replace_tags')) {

    /**
     * Replaces tags in templates
     * @param array $args An array of arguments
     * @return string $body
     */
    function template_replace_tags(array $args)
    {
        $body = $args['body'];
        //member
        if (!empty($args['member_id'])) {
            $member = Client::with(['province', 'district', 'ward', 'village', 'branch'])->find($args['member_id']);
            $body = str_replace('{{memberFirstName}}', $member->first_name ?? '', $body);
            $body = str_replace('{{memberMiddleName}}', $member->middle_name ?? '', $body);
            $body = str_replace('{{memberLastName}}', $member->last_name ?? '', $body);
            $body = str_replace('{{memberMobile}}', $member->mobile ?? '', $body);
            $body = str_replace('{{memberPhone}}', $member->phone ?? '', $body);
            $body = str_replace('{{memberExternalID}}', $member->external_id ?? '', $body);
            $body = str_replace('{{memberEmail}}', $member->email ?? '', $body);
            $body = str_replace('{{memberDOB}}', $member->dob ?? '', $body);
            $body = str_replace('{{memberID}}', $member->id ?? '', $body);
            $body = str_replace('{{memberAddress}}', $member->address ?? '', $body);
            $body = str_replace('{{memberZip}}', $member->zip ?? '', $body);
            $body = str_replace('{{memberProvince}}', $member->province->name ?? '', $body);
            $body = str_replace('{{memberDistrict}}', $member->district->name ?? '', $body);
            $body = str_replace('{{memberWard}}', $member->ward->name ?? '', $body);
            $body = str_replace('{{memberVillage}}', $member->village->name ?? '', $body);
            $body = str_replace('{{memberCountry}}', $member->country->name ?? '', $body);
            $body = str_replace('{{memberBranch}}', $member->branch->name ?? '', $body);
            $body = str_replace('{{memberDoctor}}', $member->doctor->name ?? '', $body);
            if ($member->gender == 'male') {
                $body = str_replace('{{memberGender}}', 'Male', $body);
            }
            if ($member->gender == 'female') {
                $body = str_replace('{{memberGender}}', 'Female', $body);
            }
            if ($member->gender == 'unspecified') {
                $body = str_replace('{{memberGender}}', 'Unspecified', $body);
            }
            $body = str_replace('{{memberLoanAmount}}', number_format(LoanApplication::where('member_id', $member->id)->sum('amount'), 2), $body);
        }
        //branch
        if (!empty($args['branch_id'])) {
            $branch = Branch::find($args['branch_id']);
            $body = str_replace('{{branchName}}', $branch->name ?? '', $body);
        }
        //co_payers
        if (!empty($args['course_id'])) {
            $course = Course::find($args['course_id']);
            $body = str_replace('{{courseName}}', $course->name ?? '', $body);
            $body = str_replace('{{courseCategoryName}}', $course->category->name ?? '', $body);
            $body = str_replace('{{courseTutorName}}', $course->tutor->name ?? '', $body);
            $body = str_replace('{{courseDuration}}', $course->duration ?? '', $body);
            $body = str_replace('{{courseDescription}}', $course->description ?? '', $body);
            $body = str_replace('{{courseID}}', $course->id ?? '', $body);
        }
        //invoice
        if (!empty($args['invoice_id'])) {
            $invoice = Invoice::with('currency')->with('invoiceItems')->with('branch')->find($args['invoice_id']);
            $body = str_replace('{{invoiceID}}', $invoice->id ?? '', $body);
            if ($invoice->status == 'paid') {
                $body = str_replace('{{invoiceStatus}}', 'Pending', $body);
            }
            if ($invoice->status == 'partially_paid') {
                $body = str_replace('{{invoiceStatus}}', 'Partially Paid', $body);
            }
            if ($invoice->status == 'unpaid') {
                $body = str_replace('{{invoiceStatus}}', 'Unpaid', $body);
            }
            $body = str_replace('{{invoiceCurrency}}', $invoice->currency->name ?? '', $body);

            $body = str_replace('{{invoiceAmount}}', number_format($invoice->amount ?? '', 2), $body);
            $body = str_replace('{{invoiceBalance}}', number_format($invoice->balance ?? '', 2), $body);
            $body = str_replace('{{invoiceDate}}', $invoice->date ?? '', $body);
            $body = str_replace('{{invoiceDueDate}}', $invoice->due_date ?? '', $body);
            $body = str_replace('{{invoiceReference}}', $invoice->reference ?? '', $body);
            $body = str_replace('{{invoiceClaimDate}}', $invoice->claim_date ?? '', $body);
            $body = str_replace('{{invoiceShortfall}}', number_format($invoice->shortfall ?? 0, 2), $body);
            $body = str_replace('{{invoiceCashAmount}}', number_format($invoice->cash_amount ?? 0, 2), $body);
            $body = str_replace('{{invoiceCoPayerAmount}}', number_format($invoice->co_payer_amount ?? 0, 2), $body);
            $body = str_replace('{{invoicePayments}}', number_format($invoice->amount - $invoice->balance, 2), $body);
            $body = str_replace('{{invoiceTerms}}', $invoice->terms ?? '', $body);
            $body = str_replace('{{invoiceClientNotes}}', $invoice->client_notes ?? '', $body);
            $body = str_replace('{{invoiceAdminNotes}}', $invoice->admin_notes ?? '', $body);
        }
        //loan
        if (!empty($args['loan_id'])) {
            $loan = LoanApplication::with(['staff', 'member', 'category'])->find($args['loan_id']);
            $body = str_replace('{{loanID}}', $loan->id ?? '', $body);
            if ($loan->status == 'received') {
                $body = str_replace('{{loanStatus}}', 'Received', $body);
            }
            if ($loan->status == 'approved') {
                $body = str_replace('{{loanStatus}}', 'Approved', $body);
            }
            if ($loan->status == 'rejected') {
                $body = str_replace('{{loanStatus}}', 'Rejected', $body);
            }
            //$body = str_replace('{{loanCurrency}}', $loan->currency->name ?? '', $body);

            $body = str_replace('{{loanAmount}}', number_format($loan->amount ?? '', 2), $body);
            $body = str_replace('{{loanAppliedAmount}}', number_format($loan->applied_amount ?? '', 2), $body);
            $body = str_replace('{{loanDate}}', $loan->date ?? '', $body);
            $body = str_replace('{{loanApprovedDate}}', $loan->approved_date ?? '', $body);
            $body = str_replace('{{loanReceivedDate}}', $loan->received_date ?? '', $body);
            $body = str_replace('{{loanDescription}}', $loan->description ?? '', $body);
        }
        //event
        if (!empty($args['event_id'])) {
            $event = Event::with(['category', 'createdBy'])->find($args['event_id']);
            $body = str_replace('{{eventID}}', $event->id ?? '', $body);
            if ($event->status == 'pending') {
                $body = str_replace('{{eventStatus}}', 'Pending', $body);
            }
            if ($event->status == 'active') {
                $body = str_replace('{{eventStatus}}', 'Active', $body);
            }
            if ($event->status == 'inactive') {
                $body = str_replace('{{eventStatus}}', 'In-active', $body);
            }
            if ($event->status == 'published') {
                $body = str_replace('{{eventStatus}}', 'Published', $body);
            }
            if ($event->status == 'declined') {
                $body = str_replace('{{eventStatus}}', 'Declined', $body);
            }
            if ($event->status == 'done') {
                $body = str_replace('{{eventStatus}}', 'Done', $body);
            }
            if ($event->status == 'completed') {
                $body = str_replace('{{eventStatus}}', 'Completed', $body);
            }
            if ($event->status == 'in_progress') {
                $body = str_replace('{{eventStatus}}', 'In Progress', $body);
            }

            $body = str_replace('{{eventStartDate}}', $event->start_date ?? '', $body);
            $body = str_replace('{{eventStartTime}}', $event->start_time ?? '', $body);
            $body = str_replace('{{eventEndDate}}', $event->end_date ?? '', $body);
            $body = str_replace('{{eventEndTime}}', $event->end_time ?? '', $body);
            $body = str_replace('{{eventLocation}}', $event->location ?? '', $body);
            $body = str_replace('{{eventLatitude}}', $event->latitude ?? '', $body);
            $body = str_replace('{{eventLongitude}}', $event->longitude ?? '', $body);
            $body = str_replace('{{eventDescription}}', $event->description ?? '', $body);
        }
        //invoice payment
        if (!empty($args['invoice_payment_id'])) {
            $invoicePayment = InvoicePayment::with('paymentDetail')->with('invoice')->with('createdBy')->find($args['invoice_payment_id']);
            $body = str_replace('{{invoicePaymentAmount}}', number_format($invoicePayment->amount, 2), $body);
            $body = str_replace('{{invoicePaymentDate}}', $invoicePayment->date, $body);
            $body = str_replace('{{invoicePaidBy}}', $invoicePayment->paid_by == 'member' ? 'Patient' : 'CoPayer', $body);
            $body = str_replace('{{invoicePaymentType}}', $invoicePayment->paymentDetail->paymentType->name ?? '', $body);
            $body = str_replace('{{invoicePaymentReceipt}}', $invoicePayment->paymentDetail->receipt ?? '', $body);
            $body = str_replace('{{invoicePaymentAccountNumber}}', $invoicePayment->paymentDetail->account_number ?? '', $body);
            $body = str_replace('{{invoicePaymentBankName}}', $invoicePayment->paymentDetail->bank_name ?? '', $body);
            $body = str_replace('{{invoicePaymentRoutingCode}}', $invoicePayment->paymentDetail->routing_code ?? '', $body);
            $body = str_replace('{{invoicePaymentChequeNumber}}', $invoicePayment->paymentDetail->cheque_number ?? '', $body);
            $body = str_replace('{{invoicePaymentCreatedBy}}', $invoicePayment->createdBy->name ?? '', $body);

        }
        //user
        if (!empty($args['user_id'])) {
            $user = User::find($args['user_id']);
            $body = str_replace('{{userFirstName}}', $user->first_name ?? '', $body);
            $body = str_replace('{{userMiddleName}}', $user->middle_name ?? '', $body);
            $body = str_replace('{{userLastName}}', $user->last_name ?? '', $body);
            $body = str_replace('{{userName}}', $user->name ?? '', $body);
            $body = str_replace('{{userEmail}}', $user->email ?? '', $body);
            $body = str_replace('{{userLastLogin}}', $user->last_login ?? '', $body);
            $body = str_replace('{{userMobile}}', $user->mobile ?? '', $body);
            $body = str_replace('{{userBranch}}', $user->branch->name ?? '', $body);
            $body = str_replace('{{userAddress}}', $user->address ?? '', $body);
        }
        //branch
        if (!empty($args['branch_id'])) {
            $branch = Branch::find($args['branch_id']);
            $body = str_replace('{{branchName}}', $branch->name ?? '', $body);

        }
        return $body;
    }
}

if (!function_exists('generate_report_friendly_name')) {
    function generate_report_friendly_name($title)
    {
        return Str::replace(['/', ' '], ['', '-'], $title);
    }
}
