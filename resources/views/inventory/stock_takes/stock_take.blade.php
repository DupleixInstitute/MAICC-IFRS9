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
        font-size: 9px;
    }

    .bg-gray {
        background-color: grey;
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

    .header {
        text-align: center;
    }

    .title {
        font-weight: bold;
    }

    .title-content {
        margin-left: 10px;
    }

    .clearfix {
        clear: both;
    }

    * {
        overflow: auto
    }
</style>
<table class="table">
    <thead>
    <tr>
        <th>Product SKU</th>
        <th>Product Name</th>
        <th>Variant</th>
        <th>Expected</th>
        <th>Counted</th>
    </tr>
    </thead>
    <tbody>
    @foreach($stockTake->items as $item)
        <tr>
            <td>{{$item->product_sku}}</td>
            <td>{{$item->product_name}}</td>
            <td>
                {{$item->product_type==='variable'?'variation_name':''}}
            </td>
            <td>{{$item->quantity_expected}}</td>
            <td>{{$item->quantity_counted}}</td>
        </tr>
    @endforeach
    </tbody>
</table>
