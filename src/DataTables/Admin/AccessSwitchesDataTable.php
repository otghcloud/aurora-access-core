<?php

namespace OTGH\AccessControl\Core\DataTables\Admin;

use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use OTGH\AccessControl\Core\Helpers\DataTableHelpers;
use OTGH\AccessControl\Core\Models\Hardware\PhysicalSwitch;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class AccessSwitchesDataTable extends DataTable
{
    /** @param  QueryBuilder<PhysicalSwitch>  $query */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('area_name', fn (PhysicalSwitch $switch): string => e($switch->area?->name ?? '-'))
            ->editColumn('identifier', fn (PhysicalSwitch $switch): string => '<code>'.e($switch->identifier).'</code>')
            ->addColumn('actions', fn (PhysicalSwitch $switch): string => DataTableHelpers::actionsDropdown([
                ['type' => 'edit', 'href' => route('admin.access-switches.edit', $switch)],
                ['type' => 'delete', 'href' => route('admin.access-switches.destroy', $switch)],
            ]))
            ->rawColumns(['identifier', 'actions'])
            ->filterColumn('area_name', fn (QueryBuilder $query, string $keyword) => $query->whereHas(
                'area',
                fn (QueryBuilder $areaQuery) => $areaQuery->whereRaw('LOWER(name) LIKE ?', ['%'.strtolower($keyword).'%'])
            ))
            ->orderColumn('area_name', fn (QueryBuilder $query, string $direction) => $query->orderByRaw(
                '(select name from areas where areas.id = '.$query->getModel()->getTable().'.area_id) '.(strtolower($direction) === 'desc' ? 'desc' : 'asc')
            ))
            ->setRowId('id');
    }

    /** @return QueryBuilder<PhysicalSwitch> */
    public function query(PhysicalSwitch $model): QueryBuilder
    {
        return $model->newQuery()->with('area');
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()->setTableId('access-switches-table')->columns($this->getColumns())->minifiedAjax()->orderBy(0, 'asc')->responsive(true)->serverSide(true);
    }

    /** @return array<int, Column> */
    public function getColumns(): array
    {
        return [
            Column::make('name')->title('Name'),
            Column::make('identifier')->title('Identifier'),
            Column::computed('area_name')->title('Area')->orderable(true)->searchable(true),
            Column::computed('actions')->title('Actions'),
        ];
    }
}
