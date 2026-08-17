<?php

namespace OTGH\AccessControl\Core\DataTables\Admin;

use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use OTGH\AccessControl\Core\Helpers\DataTableHelpers;
use OTGH\AccessControl\Core\Models\Access\Area;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class AccessAreasDataTable extends DataTable
{
    /** @param  QueryBuilder<Area>  $query */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->editColumn('identifier', fn (Area $area): string => '<code>'.e($area->identifier).'</code>')
            ->addColumn('readers_total', fn (Area $area): int => (int) $area->readers_count)
            ->addColumn('locks_total', fn (Area $area): int => (int) $area->locks_count)
            ->addColumn('switches_total', fn (Area $area): int => (int) $area->switches_count)
            ->addColumn('permissions_total', fn (Area $area): int => (int) $area->permissions_count)
            ->addColumn('actions', fn (Area $area): string => DataTableHelpers::actionsDropdown([
                ['type' => 'view', 'label' => 'Bindings', 'href' => route('admin.access-areas.bindings', $area)],
                ['type' => 'edit', 'label' => 'Edit Area', 'href' => route('admin.access-areas.edit', $area)],
                ['type' => 'delete', 'href' => route('admin.access-areas.destroy', $area)],
            ]))
            ->rawColumns(['identifier', 'actions'])
            ->setRowId('id');
    }

    /** @return QueryBuilder<Area> */
    public function query(Area $model): QueryBuilder
    {
        return $model->newQuery()->withCount(['readers', 'locks', 'switches', 'permissions'])->latest('id');
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()->setTableId('access-areas-table')->columns($this->getColumns())->minifiedAjax()->orderBy(0, 'desc')->responsive(true)->serverSide(true);
    }

    /** @return array<int, Column> */
    public function getColumns(): array
    {
        return [
            Column::make('name')->title('Name'),
            Column::make('identifier')->title('Identifier'),
            Column::make('readers_total')->title('Readers')->orderable(false),
            Column::make('locks_total')->title('Locks')->orderable(false),
            Column::make('switches_total')->title('Switches')->orderable(false),
            Column::make('permissions_total')->title('Permissions')->orderable(false),
            Column::computed('actions')->title('Actions')->orderable(false)->searchable(false),
        ];
    }
}
