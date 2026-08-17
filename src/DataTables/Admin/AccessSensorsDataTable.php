<?php

namespace OTGH\AccessControl\Core\DataTables\Admin;

use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use OTGH\AccessControl\Core\Helpers\DataTableHelpers;
use OTGH\AccessControl\Core\Models\Hardware\Sensor;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class AccessSensorsDataTable extends DataTable
{
    /** @param  QueryBuilder<Sensor>  $query */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('area_name', fn (Sensor $sensor): string => e($sensor->area?->name ?? '-'))
            ->editColumn('identifier', fn (Sensor $sensor): string => '<code>'.e($sensor->identifier).'</code>')
            ->editColumn('state', fn (Sensor $sensor): string => sprintf(
                '<span class="badge text-bg-%s">%s</span>',
                $sensor->state ? 'success' : 'secondary',
                $sensor->state ? 'Open' : 'Closed',
            ))
            ->addColumn('actions', fn (Sensor $sensor): string => DataTableHelpers::actionsDropdown([
                ['type' => 'view', 'href' => route('admin.access-sensors.show', $sensor)],
                ['type' => 'edit', 'href' => route('admin.access-sensors.edit', $sensor)],
                ['type' => 'delete', 'href' => route('admin.access-sensors.destroy', $sensor)],
            ]))
            ->rawColumns(['identifier', 'state', 'actions'])
            ->filterColumn('area_name', fn (QueryBuilder $query, string $keyword) => $query->whereHas(
                'area',
                fn (QueryBuilder $areaQuery) => $areaQuery->whereRaw('LOWER(name) LIKE ?', ['%'.strtolower($keyword).'%'])
            ))
            ->orderColumn('area_name', fn (QueryBuilder $query, string $direction) => $query->orderByRaw(
                '(select name from areas where areas.id = '.$query->getModel()->getTable().'.area_id) '.(strtolower($direction) === 'desc' ? 'desc' : 'asc')
            ))
            ->setRowId('id');
    }

    /** @return QueryBuilder<Sensor> */
    public function query(Sensor $model): QueryBuilder
    {
        return $model->newQuery()->with('area');
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()->setTableId('access-sensors-table')->columns($this->getColumns())->minifiedAjax()->orderBy(0, 'asc')->responsive(true)->serverSide(true);
    }

    /** @return array<int, Column> */
    public function getColumns(): array
    {
        return [
            Column::make('name')->title('Name'),
            Column::make('identifier')->title('Identifier'),
            Column::computed('area_name')->title('Area')->orderable(true)->searchable(true),
            Column::make('state')->title('State')->searchable(false),
            Column::computed('actions')->title('Actions'),
        ];
    }
}
