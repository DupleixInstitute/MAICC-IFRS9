<?php

namespace App\DataTables;

use App\Models\TransitionProfileDefinition;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Html\Editor\Editor;
use Yajra\DataTables\Html\Editor\Fields;
use Yajra\DataTables\Services\DataTable;

class TransitionProfileDefinitionDataTable extends DataTable
{
    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder $query Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('action', 'transitionprofiledefinition.action')
            ->setRowId('id');
    }

    /**
     * Get the query source of dataTable.
     */
    public function query(TransitionProfileDefinition $model): QueryBuilder
    {
        return $model->newQuery();
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
                    ->setTableId('transition-profile-table')
                    ->columns($this->getColumns())
                    ->minifiedAjax()
                    ->dom('Bfrtip')
                    ->orderBy(1)
                    ->selectStyleSingle()
                    ->buttons([
                        Button::make('excel'),
                        Button::make('csv'),
                        Button::make('pdf'),
                        Button::make('print'),
                        Button::make('reset'),
                        Button::make('reload')
                    ]);
    }

    /**
     * Get the dataTable columns definition.
     */
    public function getColumns(): array
    {
        return [
            Column::make('profile_code'),
            Column::make('short_name')->title('Name')->addClass('editable'),
            Column::make('start_table')->title('Start Table')->addClass('editable'),
            Column::make('start_grading_col')->title('Start Column')->addClass('editable'),
            Column::make('start_value_type')->title('Value Type')->addClass('editable'),
            Column::make('end_table')->title('End Table')->addClass('editable'),
            Column::make('end_grading_col')->title('End Column')->addClass('editable'),
            Column::make('end_value_type')->title('Value Type')->addClass('editable'),
            Column::make('created_at')->title('Date Created')->addClass('editable'),
            Column::computed('action')->exportable(false)->printable(false)->orderable(false)->searchable(false),
        ];
    }

    /**
     * Get the filename for export.
     */
    protected function filename(): string
    {
        return 'TransitionProfileDefinition_' . date('YYYY/MM/DD');
    }
}
