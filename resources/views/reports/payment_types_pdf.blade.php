<style>
    body {
        font-size: 9px;
    }

    .table {
        width: 100%;
        border: 1px solid #ccc;
        border-collapse: collapse;
    }

    .table th, td {
        padding: 5px;
        text-align: left;
        border: 1px solid #ccc;
    }

    .light-heading th {
        background-color: #eeeeee
    }

    .green-heading th {
        background-color: #4CAF50;
        color: white;
    }

    .text-center {
        text-align: center;
    }

    .table-striped tr:nth-child(even) {
        background-color: #f2f2f2;
    }

    .text-danger {
        color: #a94442;
    }

    .text-success {
        color: #3c763d;
    }

</style>
<h3 class="text-center">{{\App\Models\Setting::where('setting_key','company_name')->first()->setting_value}}</h3>
<h3 class="text-center"> Payment Types Report</h3>
<table class="table table-bordered table-striped table-hover">
    <thead>
    <tr>
        <th colspan="2">
            Currency:{{$currency->name??''}}
            @if($start_date)
                <br>
                Start Date:{{$start_date}}<br>
                End Date:{{$end_date}}
            @endif
        </th>
    </tr>
    <tr class="text-left font-bold">
        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Name</th>
        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Invoices Payments</th>
    </tr>
    </thead>
    <tbody>
    <?php
    $totalPayments = 0;


    ?>
    @foreach($results as $key)
        <?php
        $totalPayments += $key->total_payments;
        ?>
        <tr>
            <td>{{ $key->name }}</td>
            <td>{{ number_format( $key->total_payments,2) }}</td>
        </tr>
    @endforeach
    </tbody>
    <tfoot>
    <tr>
        <td><b>Total</b></td>
        <td>{{number_format($totalPayments,2)}}</td>
    </tr>
    </tfoot>
</table>
