<?php

namespace OTGH\AccessControl\Core\DataTables\Admin;

use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Illuminate\Http\Request;
use OTGH\AccessControl\Core\Helpers\DataTableHelpers;
use OTGH\AccessControl\Core\Models\Access\AreaPermission;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class AreaPermissionsDataTable extends DataTable
{
    public function __construct(private readonly Request $request) {}

    /** @param  QueryBuilder<AreaPermission>  $query */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('user_name', fn (AreaPermission $permission): string => e($permission->accessUser?->name ?? '-'))
            ->addColumn('area_name', fn (AreaPermission $permission): string => e($permission->area?->name ?? '-'))
            ->editColumn('permission', fn (AreaPermission $permission): string => '<span class="badge text-bg-'.($permission->permission === 'allow' ? 'success' : 'danger').'">'.e(strtoupper($permission->permission)).'</span>')
            ->addColumn('actions', fn (AreaPermission $permission): string => DataTableHelpers::actionsDropdown([
                ['type' => 'edit', 'href' => route('admin.access-area-permissions.edit', $permission)],
                ['type' => 'delete', 'href' => route('admin.access-area-permissions.destroy', $permission)],
            ]))
            ->rawColumns(['permission', 'actions'])
            ->setRowId('id');
    }

    /** @return QueryBuilder<AreaPermission> */
    public function query(AreaPermission $model): QueryBuilder
    {
        return $model->newQuery()->with(['accessUser', 'area'])
            ->when($this->request->filled('individual_id'), fn (QueryBuilder $query) => $query->where('individual_id', (int) $this->request->input('individual_id')))
            ->when($this->request->filled('area_id'), fn (QueryBuilder $query) => $query->where('area_id', (int) $this->request->input('area_id')))
            ->when($this->request->filled('permission'), fn (QueryBuilder $query) => $query->where('permission', $this->request->string('permission')->toString()))
            ->latest('id');
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()->setTableId('area-permissions-table')->columns($this->getColumns())->minifiedAjax()->orderBy(0, 'asc')->responsive(true)->serverSide(true);
    }

    /** @return array<int, Column> */
    public function getColumns(): array
    {
        return [
            Column::make('user_name')->title('User')->orderable(false),
            Column::make('area_name')->title('Area')->orderable(false),
            Column::make('permission')->title('Permission')->orderable(false),
            Column::computed('actions')->title('Actions')->orderable(false)->searchable(false),
        ];
    }
}
