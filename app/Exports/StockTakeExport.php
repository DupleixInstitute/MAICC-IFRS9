<?php

namespace App\Exports;

use App\Models\InventoryStockTake;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class StockTakeExport implements FromCollection, WithHeadings, WithMapping
{
    protected InventoryStockTake $stockTake;

    /**
     * StockTakeExport constructor.
     * @param $items
     */
    public function __construct(InventoryStockTake $stockTake)
    {
        $this->stockTake = $stockTake;
    }

    public function collection()
    {
        return $this->stockTake->items;
    }

    public function headings(): array
    {
        return [
            'SKU',
            'Product Name',
            'Variant',
            'Expected',
            'Counted'
        ];
    }

    public function map($item): array
    {
        return [
            $item->variation_sku,
            $item->product_name,
            $item->variation_name,
            $item->quantity_expected,
            $item->quantity_counted,
        ];
    }
}
