<table class="table table-bordered table-striped table-hover">
    <thead>
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
            <td></td>
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
