<html>
<head>
    <title>Prescription</title>
    <style>
        .container {
            display: flex;
            flex-direction: column;
            margin: 50px;
        }

        .prescription_form {
            width: 100%;
            height: 100vh;

            background: white;
        }

        .prescription {
            width: 720px;
            height: 960px;
            margin: 0 auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        td {
            /*padding: 5 px 0 5 px 15 px;
            / / border: 1 px solid #EEE*/
        }

        .table-bordered td {
            border: solid thin #ccc;
            text-align: center;
            padding: 10px;
        }
        .table-bordered th {
            border: solid thin #ccc;
            padding: 10px;
        }

        .tabletitle {
            /* padding: 5 px;*/
            font-size: .5em;
            background: #EEE;
        }

        .service {
            border-bottom: 1px solid #EEE;
        }

        .item {
            width: 24mm;
        }

        .itemtext {
            font-size: .5em;
        }

        #legalcopy {
            margin-top: 5mm;
        }

        .text-center {
            text-align: center;
        }

        .patient-info {
            margin-top: 10px;
            padding: 10px;
            border-top: 1px solid #ccc;
            border-bottom: 1px solid #ccc;
            clear: both;
        }

        .prescription-info {
            margin-top: 20px;
        }
        .signature{
            position: absolute;
            bottom: 0px;
            border-bottom: 1px solid #ccc;
            height: 100px;
        }
    </style>
</head>

<body>
<div class="container">
    <div class="prescription">
        <div id="top">
            @if($logo=\App\Models\Setting::where('setting_key','company_letterhead')->first()->setting_value)
                <div class="logo" style="margin-left: auto; margin-right: auto;">
                    <img alt="logo" src="{{asset('storage/'.$logo)}}" style="width: 100%"/>
                </div>
            @elseif($logo=\App\Models\Setting::where('setting_key','company_logo')->first()->setting_value)
                <div style="margin-bottom: 10px;position: relative; clear: both;">
                    <div class="practice-details" style="float: left; width: 40%;">
                        <h4>{{\App\Models\Setting::where('setting_key','company_name')->first()->setting_value}}</h4>
                        <span>{!!\App\Models\Setting::where('setting_key','company_address')->first()->setting_value !!}</span><br>
                        <b>Tel:</b><span>{!!\App\Models\Setting::where('setting_key','company_tel')->first()->setting_value !!}</span><br>
                        <b>Email:</b><span>{!!\App\Models\Setting::where('setting_key','company_email')->first()->setting_value !!}</span><br>
                        <b>Website:</b><span>{!!\App\Models\Setting::where('setting_key','company_website')->first()->setting_value !!}</span><br>
                    </div>
                    <div class="practice-logo" style="float: left; width: 20%;">
                        <img alt="logo" src="{{asset('storage/'.$logo)}}" style="width:100%;"/>
                    </div>
                    <div class="doctor-details" style="float: left; width: 40%; text-align: right">
                        <span>Dr {{$consultation->doctor->name}}</span><br>
                        <span>{!! $consultation->doctor->qualifications !!}</span>
                    </div>
                </div>

            @else
                <div class="practice-details" style="float: left; width: 50%;">
                    <h4>{{\App\Models\Setting::where('setting_key','company_name')->first()->setting_value}}</h4>
                    <span>{!!\App\Models\Setting::where('setting_key','company_address')->first()->setting_value !!}</span><br>
                    <b>Tel:</b><span>{!!\App\Models\Setting::where('setting_key','company_tel')->first()->setting_value !!}</span><br>
                    <b>Email:</b><span>{!!\App\Models\Setting::where('setting_key','company_email')->first()->setting_value !!}</span><br>
                    <b>Website:</b><span>{!!\App\Models\Setting::where('setting_key','company_website')->first()->setting_value !!}</span><br>

                </div>

                <div class="doctor-details" style="float: left; width: 50%;text-align: right">
                    <span>Dr {{$consultation->doctor->name}}</span><br>
                    <span>{!! $consultation->doctor->qualifications !!}</span>
                </div>

            @endif
        </div>
        <div class="patient-info">
            <h4 class="text-center">Patient Details</h4>
            <table class="table">
                <tbody>
                <tr>
                    <td><b>Name</b></td>
                    <td>{{$consultation->patient->name}}</td>
                    <td><b>Age</b></td>
                    <td>{{$consultation->patient->age}}</td>
                </tr>
                <tr>
                    <td><b>Sex</b></td>
                    <td>
                        @if($consultation->patient->gender==='male')
                            Male
                        @endif
                        @if($consultation->patient->gender==='female')
                            Female
                        @endif
                    </td>
                    <td><b>Mobile</b></td>
                    <td>{{$consultation->patient->mobile}}</td>
                </tr>
                <tr>
                    <td><b>Address</b></td>
                    <td>{{$consultation->patient->address}}</td>
                    <td><b>Date</b></td>
                    <td>{{$consultation->date}}</td>
                </tr>
                </tbody>
            </table>
        </div>
        <div class="prescription-info">
            <table class="table table-bordered">
                <thead class="bg-gray-50">
                <tr class="text-left font-bold">
                    <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Drug</th>
                    <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Dosage</th>
                    <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Frequency</th>
                    <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Duration</th>
                    <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Qty</th>
                </tr>
                </thead>
                <tbody>
                @foreach($consultation->prescriptions as $prescription)
                    <tr>
                        <td>{{$prescription->name}}</td>
                        <td>{{$prescription->dosage}}</td>
                        <td>{{$prescription->frequency}}</td>
                        <td>{{$prescription->days}}</td>
                        <td>{{$prescription->qty}}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="signature">

        </div>
    </div><!--End InvoiceTop-->


</div>
</body>
</html>
