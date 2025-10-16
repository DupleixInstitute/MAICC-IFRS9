<!DOCTYPE html>
<html>
<head>
    <title>Claim Form</title>
    <style>
        body {
            height: 835px;
            width: 605px;
            /* to centre page on screen*/
            margin-left: auto;
            margin-right: auto;
            font-size: 9px;
        }

        table {
            border-collapse: collapse;
        }

        .text-center {
            text-align: center;
        }

        .table-bordered td {
            border: solid thin #ccc;
        }

        .procedure-table td {
            height: 10px;
        }
    </style>
</head>
<body>
<table style="width: 595px">
    <tr>
        <td style="width:70%;padding: 10px;text-align: center;font-weight: bold">MEDICAL AID CLAIM FORM</td>
        <td style="width:30%; border: solid thin #ccc;padding: 10px;text-align: center; font-size: 9px">Claim
            Number:{{$invoice->claim_id}}
        </td>
    </tr>
</table>
<div style="border: solid thin #000; height: 350px;padding: 3px">
    <table style="width: 595px">
        <tr>
            <td style="width:70%">
                <table>
                    <tr>
                        <td>MEDICAL AID SOCIETY:</td>
                        <td><span>{{$invoice->coPayer->name}}</span></td>
                        <td></td>
                        <td></td>
                    </tr>
                    <tr>
                        <td>MEMBER's NAME:</td>
                        <td><span>{{$invoice->co_payer_member_name}}</span></td>
                        <td></td>
                        <td></td>
                    </tr>
                    <tr>
                        <td>CONTACT TEL. NO:</td>
                        <td><span>{{$invoice->patient->tel}}</span></td>
                        <td>ID NO:</td>
                        <td><span>{{$invoice->patient->id_number}}</span></td>
                    </tr>
                    <tr>
                        <td>CELLPHONE NO:</td>
                        <td><span>{{$invoice->patient->mobile}}</span></td>
                        <td>E-MAIL:</td>
                        <td><span>{{$invoice->patient->email}}</span></td>
                    </tr>
                    <tr>
                        <td>POSTAL ADDRESS:</td>
                        <td colspan="3">
                            <span>
                            {!! $invoice->patient->address !!}
                                @if(!empty($invoice->patient->city))
                                    {{$invoice->patient->city}}
                                @endif
                                @if(!empty($invoice->patient->state))
                                    {{$invoice->patient->state}}
                                @endif
                                @if(!empty($invoice->patient->country))
                                    {{$invoice->patient->country->name}}
                                @endif
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td>NAME OF EMPLOYER/GOVT DEPT:</td>
                        <td><span>{{$invoice->patient->employer_name}}</span></td>
                        <td></td>
                        <td></td>
                    </tr>
                </table>

            </td>
            <td style="width:30%;">
                <div style="border: solid thin #000; height: 50px;margin-top: -3px;">

                </div>
                <div style="border: solid thin #000; height: 50px;clear: both">

                </div>
            </td>
        </tr>
    </table>
    <table style="margin-top:10px;width: 595px">
        <tr>
            <td>PATIENT's NAME</td>
            <td>RELATIONSHIP<br> TO MEMBER</td>
            <td>MEMBER's <br>NUMBER</td>
            <td>PATIENT's <br>SUFFIX No.</td>
            <td>PATIENT's <br>DATE OF BIRTH</td>
            <td>B/P<br>O/R</td>
            <td>STAFF</td>
        </tr>
        <tr>
            <td style="border: solid thin #000">
                <span>{{$invoice->patient->name}}</span></td>
            <td style="border: solid thin #000">
                <span>
                @if(!empty($invoice->patientRelationship->name))
                        {{$invoice->co_payer_member_name}}
                    @endif
                </span>
            </td>
            <td style="border: solid thin #000"><span>{{$invoice->co_payer_membership_number}}</span></td>
            <td style="border: solid thin #000"><span>{{$invoice->co_payer_suffix}}</span></td>
            <td style="border: solid thin #000"><span>{{$invoice->patient->dob}}</span></td>
            <td style="border: solid thin #000"></td>
            <td style="border: solid thin #000"></td>
        </tr>
    </table>
    <table style="margin-top:10px;width: 595px">
        <tr>
            <td style="width: 40%;padding-right: 10px; font-size: 7px">
                <p><i>SIGNATURE - BEFORE SIGNING, PLEASE NOTE</i></p>

                <p>1. IF YOU SIGN THIS CLAIM FORM FOR ANY TREATMENT WHICH HAS NOT BEEN PROVIDED YOU MAY WELL BE
                    COMMITTING AN OFFENCE. IF YOU BECOME AWARE THAT THE CLAIM IS SUBMITTED FOR SERVICES WHICH HAVE NOT
                    BEEN PROVIDED YOU MUST CONTACT YOUR MEDICAL AID SOCIETY FORTHWITH.</p>

                <p>2. IF THIS TREATMENT HAS NOT BEEN PAID FOR THEN YOU MUST EITHER SIGN EACH DAY THE TREATMENT IS
                    RECEIVED OR ONCE ONLY AFTER THE PROVIDER OF SERVICES HAS INSERTED ALL HIS CHARGES.</p>

                <p><i>NB: - CLAIM FORMS WHICH ARE SIGNED BEFORE THE DAY ON WHICH THE TREATMENT IS TO BE RECEIVED WILL BE
                        REJECTED.</i></p>

                <p>3. IF THIS TREATMENT HAS BEEN PAID FOR, YOU SHOULD SIGN THE FORM ONCE ONLY BEFORE SENDING IT TO YOUR
                    MEDICAL AID Society. ATTACH YOUR RECEIPT AND INSERT THE AMOUNT YOU ARE CLAIMING IN THE APPROPRIATE
                    BOX ALONGSIDE YOUR SIGNATURE.</p>
            </td>
            <td style="width: 60%">
                <table width="100%">
                    <tr>
                        <td style="text-align: center">SIGNATURE</td>
                        <td style="text-align: center">DATE</td>
                        <td style="text-align: center">RELATIONSHIP<br> TO MEMBER</td>
                        <td style="text-align: center">FEE CHARGED<br> (IF KNOWN)</td>
                    </tr>
                    <tr>
                        <td style="border: solid thin #ccc;height: 20px;width: 25%"></td>
                        <td style="border: solid thin #ccc;height: 20px;width: 25%"></td>
                        <td style="border: solid thin #ccc;height: 20px;width: 25%"></td>
                        <td style="border: solid thin #ccc;height: 20px;width: 25%"></td>
                    </tr>
                    <tr>
                        <td style="border: solid thin #ccc;height: 20px;width: 25%"></td>
                        <td style="border: solid thin #ccc;height: 20px;width: 25%"></td>
                        <td style="border: solid thin #ccc;height: 20px;width: 25%"></td>
                        <td style="border: solid thin #ccc;height: 20px;width: 25%"></td>
                    </tr>
                    <tr>
                        <td style="border: solid thin #ccc;height: 20px;width: 25%"></td>
                        <td style="border: solid thin #ccc;height: 20px;width: 25%"></td>
                        <td style="border: solid thin #ccc;height: 20px;width: 25%"></td>
                        <td style="border: solid thin #ccc;height: 20px;width: 25%"></td>
                    </tr>
                </table>
                <p style="font-size: 7px">I CONFIRM THAT THE DETAILS GIVEN ABOVE ARE CORRECT, THAT THE AMOUNT CLAIMED
                    HEREIN IS NOT CLAIMABLE
                    FROM ANOTHER SOURCE, AND THAT THE PATIENT IS A MEMBER OR DEPENDENT OF THE MEDICAL AID SOCIETY SHOWN
                    ABOVE. I AUTHORISE THE PROVIDER OF SERVICES TO DISCLOSE THE NATURE OF ILLNESS TO THE MEDICAL AID
                    SOCIETY FOR ITS CONFIDENTIAL USE, AND I AGREE THAT NO AWARDS WILL BE MADE FOR THIS TREATMENT UNLESS
                    CONTRIBUTIONS ARE RECEIVED IN RESPECT OF THE PERIOD OF TREATMENT.</p>
            </td>
        </tr>
    </table>
</div>
<div style="border: solid thin #000; height: 380px;margin-top: 2px;padding: 3px">
    <table style="width: 595px">

        <tr>
            <td style="text-align: center;font-weight: bold" colspan="3">FOR COMPLETION BY PROVIDER OF
                SERVICES
            </td>
            <td colspan="2"></td>
        </tr>
        <tr style="margin-top:5px;">
            <td>AHFoZ PAYEE No</td>
            <td>DATE CLAIM CLOSED</td>
            <td>ACCOUNT REF No.</td>
            <td colspan="2"></td>
        </tr>
        <tr>
            <td style="width: 25%"><span></span></td>
            <td style="width: 20%"><span>{{date("Y-m-d")}}</span></td>
            <td style="width: 20%"><span>{{$invoice->id}}</span></td>
            <td style="width: 35%" colspan="2">
                <span>FOR USE BY MEDICAL AID SOCIETIES</span><br>
                <small>RELEVANT AHFoZ NOs:</small>
            </td>
        </tr>
        <tr>
            <td colspan="2">NAME OF REFERRING PRACTITIONER (IF ANY):</td>
            <td style="border-bottom: solid thin #ccc"></td>
            <td style="border: solid thin #ccc"></td>
            <td style="width: 30%" rowspan="3">
                <table>
                    <tr>
                        <td>B/P<br>O/R</td>
                        <td>STAFF</td>
                    </tr>
                    <tr>
                        <td style="border: solid thin #ccc"></td>
                        <td style="border: solid thin #ccc"></td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td colspan="2">NAME OF REFERRING PRACTITIONER (IF ANY):</td>
            <td style="border-bottom: solid thin #ccc"></td>
            <td style="border: solid thin #ccc"></td>
        </tr>
        <tr>
            <td colspan="2">NAME OF SURGICAL ASSISTANT (IF ANY):</td>
            <td style="border-bottom: solid thin #ccc"></td>
            <td style="border: solid thin #ccc"></td>
        </tr>
    </table>
    <table class="table-bordered procedure-table" style="width:595px">
        <thead>
        <tr>
            <th>LINE</th>
            <th></th>
            <th>TARIFF No.</th>
            <th>Mod 1</th>
            <th>Mod 2</th>
            <th>QTY</th>
            <th>YR</th>
            <th>MTH</th>
            <th>DAY 1</th>
            <th>DAY 2</th>
            <th>DAY 3</th>
            <th>DAY 4</th>
            <th>DAY 5</th>
            <th>FEE CHARGED</th>
            <th>AWARD</th>
            <th>PERSONAL A/C SHORTFALL</th>
            <th>REASON</th>
            <th>B/P<br>O/R</th>
            <th>STAFF</th>
        </tr>
        </thead>
        <tbody>
        <?php
        $count = 1;
        foreach ($invoice->invoiceItems as $key) {
        ?>
        @if($key->co_payer_amount>0)
            <tr>
                <td>{{$count}}</td>
                <td>M</td>
                <td>{{$key->tariff->code??''}}</td>
                <td></td>
                <td></td>
                <td>{{$key->qty}}</td>
                <td>{{\Illuminate\Support\Carbon::parse($invoice->date)->format('Y')}}</td>
                <td>{{\Illuminate\Support\Carbon::parse($invoice->date)->format('m')}}</td>
                <td>{{\Illuminate\Support\Carbon::parse($invoice->date)->format('d')}}</td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td>{{$key->co_payer_amount}}</td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
        @endif
        <?php
        $count++;

        }
        while ($count < 10) {

            echo '<tr>';
            echo '<td></td>';
            echo '<td></td>';
            echo '<td></td>';
            echo '<td></td>';
            echo '<td></td>';
            echo '<td></td>';
            echo '<td></td>';
            echo '<td></td>';
            echo '<td></td>';
            echo '<td></td>';
            echo '<td></td>';
            echo '<td></td>';
            echo '<td></td>';
            echo '<td></td>';
            echo '<td></td>';
            echo '<td></td>';
            echo '<td></td>';
            echo '<td></td>';
            echo '<td></td>';
            echo '</tr>';
            $count++;
        }
        ?>
        </tbody>
    </table>
    <p class="text-center">GROSS AMOUNT
        CLAIMED:{{number_format($invoice->co_payer_amount,2)}}</p>

    <p>I hereby certify that, I, or members of my staff, have rendered the above services to or on behalf of
        the
        person. I confirm that to the best of my knowledge the patient treated is the patient named on this
        form. I agree that any claim for services not provided would be regarded as fraudulent and render
        the
        person concerned liable for prosecution.</p>

    <p>DIAGNOSIS:</p>

    <p class="text-right">
        _________________________________________________<br><br>
        SIGNATURE & OFFICIAL STAMP OF PROVIDER OF SERVICES<br><br>
        DATE:{{date("Y-m-d")}}
    </p>
</div>
</body>
</html>
