<?php

namespace OTGH\AccessControl\Core\DataTables\Admin;

use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use OTGH\AccessControl\Core\Helpers\DataTableHelpers;
use OTGH\AccessControl\Core\Models\Hardware\Lock;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class AccessLocksDataTable extends DataTable
{
    /** @param  QueryBuilder<Lock>  $query */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('area_name', fn (Lock $lock): string => e($lock->area?->name ?? 'Unassigned'))
            ->editColumn('identifier', fn (Lock $lock): string => '<code>'.e($lock->identifier).'</code>')
            ->editColumn('is_primary', fn (Lock $lock): string => sprintf('<span class="badge text-bg-%s">%s</span>', $lock->is_primary ? 'success' : 'secondary', $lock->is_primary ? 'Primary' : 'Secondary'))
            ->addColumn('autolock', fn (Lock $lock): string => $lock->usesAutolockOverride() ? '<span class="badge text-bg-warning">Override</span>' : '<span class="badge text-bg-secondary">Inherit Area</span>')
            ->addColumn('actions', fn (Lock $lock): string => DataTableHelpers::actionsDropdown([
                ['type' => 'view', 'href' => route('admin.access-locks.show', $lock)],
                ['type' => 'edit', 'href' => route('admin.access-locks.edit', $lock)],
                ['type' => 'delete', 'href' => route('admin.access-locks.destroy', $lock)],
            ]))
            ->rawColumns(['identifier', 'is_primary', 'autolock', 'actions'])
            ->filterColumn('area_name', fn (QueryBuilder $query, string $keyword) => $query->whereHas(
                'area',
                fn (QueryBuilder $areaQuery) => $areaQuery->whereRaw('LOWER(name) LIKE ?', ['%'.strtolower($keyword).'%'])
            ))
            ->orderColumn('area_name', fn (QueryBuilder $query, string $direction) => $query->orderByRaw(
                '(select name from areas where areas.id = '.$query->getModel()->getTable().'.area_id) '.(strtolower($direction) === 'desc' ? 'desc' : 'asc')
            ))
            ->setRowId('id');
    }

    /** @return QueryBuilder<Lock> */
    public function query(Lock $model): QueryBuilder
    {
        return $model->newQuery()->with('area');
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()->setTableId('access-locks-table')->columns($this->getColumns())->minifiedAjax()->orderBy(0, 'asc')->responsive(true)->serverSide(true);
    }

    /** @return array<int, Column> */
    public function getColumns(): array
    {
        return [
            Column::make('name')->title('Name'),
            Column::make('identifier')->title('Identifier'),
            Column::computed('area_name')->title('Area')->orderable(true)->searchable(true),
            Column::make('is_primary')->title('Role')->searchable(false),
            Column::computed('autolock')->title('Auto-lock Config'),
            Column::computed('actions')->title('Actions'),
        ];
    }
}
