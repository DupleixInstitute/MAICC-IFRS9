<html>
<head>
    <title>Invoice #{{ $invoice->reference }}</title>
    <style>
        body {
            font-family: "Helvetica Neue", Helvetica, sans-serif;
            font-size: 12px;
        }

        table {
            border-collapse: collapse;
        }

        .invoice-preview {
            max-width: 900px;
            min-width: 400px;
            margin: auto;
        }

        .invoice-preview table {
            width: 100%;
            margin-top: 40px;
        }

        .invoice-preview td,
        .invoice-preview th {
            padding: 10px;
        }

        .invoice-preview * {
            line-height: 20px !important;
        }


        .ribbon {
            position: absolute;
            left: -35px;
            top: -1px;
            z-index: 1;
            overflow: hidden;
            width: 110px;
            height: 110px;
            text-align: right;
        }

        .ribbon span {
            font-size: 12px;
            font-weight: bold;
            color: #FFF;
            text-align: center;
            line-height: 20px;
            transform: rotate(-45deg);
            -webkit-transform: rotate(-45deg);
            width: 147px;
            display: block;
            position: absolute;
            top: 35px;
            left: -28px;
            padding: 0 !important;
        }

        .ribbon span::before {
            content: "";
            position: absolute;
            left: 0px;
            top: 100%;
            z-index: -1;
            border-left: 3px solid #aaa;
            border-right: 3px solid transparent;
            border-bottom: 3px solid transparent;
            border-top: 3px solid #aaa;
        }

        .ribbon span::after {
            content: "";
            position: absolute;
            right: 0px;
            top: 100%;
            z-index: -1;
            border-left: 3px solid transparent;
            border-right: 3px solid #aaa;
            border-bottom: 3px solid transparent;
            border-top: 3px solid #aaa;
        }

        .invoice-meta {
            font-size: 100% !important;
        }

        .print-invoice .invoice-preview-container {
            border: 1px solid #dadada;
        }

        .print-invoice .ribbon span::before, .print-invoice .ribbon span::after {
            border: none !important;
        }

        .print-invoice .ribbon {
            width: 105px;
            height: 105px;
            left: -30px;
            top: -30px;
        }

        .print-invoice .table-responsive, .print-invoice .table-responsive tr th, .print-invoice .table-responsive tr td:not([colspan='3']) {
            border: 1px solid #eee !important;
        }

        .print-invoice .invoice-pdf-hidden-table {
            border: none !important;
        }

        .print-invoice td.invoice-header-style-one .invoice-info-title {
            margin-right: -5px !important;
        }

        .print-invoice td.invoice-header-style-two .invoice-info-title {
            margin-left: -5px !important;
        }
    </style>
</head>
<body>
<div class="invoice-preview">
    <div class="invoice-preview-container bg-white mt15">

        <table style="color: #444; width: 100%;">
            <tbody>
            <tr class="invoice-preview-header-row">
                <td style="width: 45%; vertical-align: top;">
                    @if(!empty($logo=\App\Models\Setting::where('setting_key','company_logo')->first()->setting_value))
                        <img src="{{asset('storage/'.$logo)}}" alt="logo" style="max-width: 100%">
                    @else

                    @endif
                </td>
                <td class="hidden-invoice-preview-row" style="width: 20%;"></td>
                <td class="invoice-info-container invoice-header-style-one"
                    style="width: 35%; vertical-align: top; text-align: right">
                    <div style="line-height: 10px;"></div>
                    <span>Reference: {{$invoice->reference}}</span><br>
                    <span>Bill Date: {{$invoice->date}}</span><br>
                    <span>Due Date: {{$invoice->due_date}}</span><br>
                    @if($invoice->status==='paid')
                        <span>Status:</span><span style="color: green">Paid</span>
                    @endif
                    @if($invoice->status==='partially_paid')
                        <span>Status:</span><span style="color: orange">Partially Paid</span>
                        -<a href="{{url('portal/invoice/'.$invoice->id)}}"><span
                                style="color: green">Pay</span></a>
                    @endif
                    @if($invoice->status==='unpaid')
                        <span>Status:</span> <span style="color: red">Unpaid</span>
                        -<a href="{{url('portal/invoice/'.$invoice->id)}}"><span
                                style="color: green">Pay</span></a>
                    @endif
                </td>
            </tr>
            <tr>
                <td style="padding: 5px;"></td>
                <td></td>
                <td></td>
            </tr>
            <tr>
                <td>
                    <div><b>{{\App\Models\Setting::where('setting_key','company_name')->first()->setting_value}}</b></div>
                    <div style="line-height: 3px;"></div>
                    <span class="invoice-meta" style="font-size: 90%; color: #666;">
                                        {{\App\Models\Setting::where('setting_key','company_address')->first()->setting_value}}
                                        <br>
                                        Phone: {{\App\Models\Setting::where('setting_key','company_tel')->first()->setting_value}}
                                        <br>
                                        Website: <a
                            style="color:#666; text-decoration: none;"
                            href="{{\App\Models\Setting::where('setting_key','company_website')->first()->setting_value}}">{{\App\Models\Setting::where('setting_key','company_website')->first()->setting_value}}</a>
                                    </span>
                </td>
                <td></td>
                <td>
                    <div>
                        <b>Bill To</b>
                    </div>
                    <div style="line-height: 2px; border-bottom: 1px solid #f2f2f2;"></div>
                    <div style="line-height: 3px;"></div>
                    @if(!empty($invoice->patient))
                        <strong>{{$invoice->patient->name}} </strong>
                    @endif

                    <div style="line-height: 3px;"></div>
                    <span class="invoice-meta" style="font-size: 90%; color: #666;">
                                        <div>
                                            {{$invoice->patient->address}}
                                            <br>
                                            @if(!empty($invoice->patient->country))
                                                {{$invoice->patient->country->name}}
                                            @endif
                                        </div>
                                    </span>
                </td>
            </tr>
            </tbody>
        </table>

        <div style="clear: both"></div>
        <br>
        <table class="table-responsive" style="width: 100%; color: #444;">
            <tbody>
            <tr style="font-weight: bold; background-color: #2AA384; color: #fff;  ">
                <th style="width: 45%; border-right: 1px solid #eee;"> Item</th>
                <th style="text-align: center;   border-right: 1px solid #eee;"> Quantity</th>
                <th style="text-align: right;  border-right: 1px solid #eee;"> Unit Cost</th>
                <th style="text-align: right;  border-right: 1px solid #eee;"> Tax</th>
                <th style="text-align: right;"> Total</th>
            </tr>
            @foreach($invoice->invoiceItems as $key)
                <tr style="background-color: #f4f4f4; ">
                    <td style="width: 45%; border: 1px solid #fff; padding: 10px;">{{$key->name}}
                        <br>
                        <span style="color: #888; font-size: 90%;">{{$key->description}}</span>
                    </td>
                    <td style="text-align: center; width: 15%; border: 1px solid #fff;"> {{number_format($key->qty)}}</td>
                    <td style="text-align: right; width: 20%; border: 1px solid #fff;"> {{number_format($key->unit_cost,2)}}</td>
                    <td style="text-align: right; width: 20%; border: 1px solid #fff;">
                        {{number_format($key->tax_total,2)}}
                        @if(!empty($key->taxRate))
                            ({{$key->taxRate->name}})
                        @endif
                    </td>
                    <td style="text-align: right; width: 20%; border: 1px solid #fff;"> {{number_format($key->total,2)}}</td>
                </tr>
            @endforeach
            <tr>
                <td colspan="4"
                    style="text-align: right;">Sub Total</td>
                <td style="text-align: right; width: 20%; border: 1px solid #fff; background-color: #f4f4f4;">
                    {{number_format($invoice->invoiceItems->sum('total'),2)}}
                </td>
            </tr>

            @if($invoice->discount_amount>0)
                <tr>
                    <td colspan="4"
                        style="text-align: right;">Discount</td>
                    <td style="text-align: right; width: 20%; border: 1px solid #fff; background-color: #f4f4f4;">
                        {{number_format($invoice->discount_amount,2)}}
                        @if($invoice->discount_type=='percentage')
                            ({{$invoice->discount}}%)
                        @endif
                    </td>
                </tr>
            @endif
            @if(!empty($invoice->taxRate))
                <tr>
                    <td colspan="4"
                        style="text-align: right;">Tax</td>
                    <td style="text-align: right; width: 20%; border: 1px solid #fff; background-color: #f4f4f4;">
                        {{number_format($invoice->tax_total,2)}}
                        ({{$invoice->taxRate->name}})
                    </td>
                </tr>
            @endif
            <tr>
                <td colspan="4" style="text-align: right;">Total</td>
                <td style="text-align: right; width: 20%; border: 1px solid #fff; background-color: #f4f4f4;">
                    {{number_format($invoice->amount,2)}}
                </td>
            </tr>
            <tr>
                <td colspan="4" style="text-align: right;">Paid</td>
                <td style="text-align: right; width: 20%; border: 1px solid #fff; background-color: #f4f4f4;">
                    {{number_format($invoice->invoicePayments->sum('amount'),2)}}
                </td>
            </tr>
            <tr>
                <td colspan="4"
                    style="text-align: right;">Balance</td>
                <td style="text-align: right; width: 20%; background-color: #2AA384; color: #fff;">
                    {{number_format($invoice->balance,2)}}
                </td>
            </tr>
            </tbody>
        </table>
        <!-- use table to avoid extra spaces -->
        <br>
        <h4>Payments</h4>
        <table class="table-responsive" style="width: 100%; color: #444;">
            <tbody>
            <tr style="font-weight: bold; background-color: #2AA384; color: #fff;  ">
                <th style=" border-right: 1px solid #eee;"> #</th>
                <th style="text-align: center;   border-right: 1px solid #eee;"> Payment Mode</th>
                <th style="text-align: center;  border-right: 1px solid #eee;"> Date</th>
                <th style="text-align: right;"> Amount</th>
            </tr>
            @if(count($invoice->invoicePayments)==0)
                <tr style="background-color: #f4f4f4; ">
                    <td style=" border: 1px solid #fff; padding: 10px; text-align: center;" colspan="4">
                        No Records
                    </td>
                </tr>
            @endif
            @foreach($invoice->invoicePayments as $key)
                <tr style="background-color: #f4f4f4; ">
                    <td style=" border: 1px solid #fff; padding: 10px;">
                        {{$key->id}}
                    </td>
                    <td style="text-align: center; width: 15%; border: 1px solid #fff;">
                        @if(!empty($key->paymentType))
                            {{$key->paymentType->name}}
                        @endif
                    </td>
                    <td style="text-align: right; width: 20%; border: 1px solid #fff;"> {{$key->date}}</td>
                    <td style="text-align: center; width: 15%; border: 1px solid #fff;"> {{number_format($key->amount)}}</td>

                </tr>
            @endforeach
            </tbody>
        </table>
        <br>
        @if(!empty($invoice->terms))
            <div style=" margin: auto;">
                <hr>
                <h4>Terms & Conditions</h4>
                {!! $invoice->terms !!}
            </div>
        @endif
        <br>
        <table class="invoice-pdf-hidden-table"
               style="border-top: 2px solid #f2f2f2; margin: 0; padding: 0; display: block; width: 100%; height: 10px;"></table>
        <span style="color:#444; line-height: 14px;"><p><br></p></span>
    </div>
</div>
</body>
</html>
