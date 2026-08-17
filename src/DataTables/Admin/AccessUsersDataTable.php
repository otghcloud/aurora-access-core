<?php

namespace OTGH\AccessControl\Core\DataTables\Admin;

use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use OTGH\AccessControl\Core\Helpers\DataTableHelpers;
use OTGH\AccessControl\Core\Models\Access\Individual;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class AccessUsersDataTable extends DataTable
{
    /**
     * @param  QueryBuilder<Individual>  $query
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('actions', function (Individual $accessUser): string {
                return DataTableHelpers::actionsDropdown([
                    [
                        'type' => 'edit',
                        'href' => route('admin.access-users.edit', $accessUser),
                    ],
                    [
                        'type' => 'delete',
                        'href' => route('admin.access-users.destroy', $accessUser),
                    ],
                ]);
            })
            ->rawColumns(['actions'])
            ->setRowId('id');
    }

    /**
     * @return QueryBuilder<Individual>
     */
    public function query(Individual $model): QueryBuilder
    {
        return $model->newQuery()->withCount('cards')->orderBy('name');
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('access-users-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->orderBy(0, 'asc')
            ->responsive(true)
            ->serverSide(true);
    }

    /**
     * @return array<int, Column>
     */
    public function getColumns(): array
    {
        return [
            Column::make('name')->title('Name'),
            Column::make('cards_count')->title('Cards'),
            Column::computed('actions')->title('Actions')->orderable(false)->searchable(false),
        ];
    }
}
