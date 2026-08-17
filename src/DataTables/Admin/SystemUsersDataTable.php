<?php

namespace OTGH\AccessControl\Core\DataTables\Admin;

use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use OTGH\AccessControl\Core\Helpers\DataTableHelpers;
use OTGH\AccessControl\Core\Models\User;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class SystemUsersDataTable extends DataTable
{
    /** @param  QueryBuilder<User>  $query */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('actions', function (User $user): string {
                $actions = [
                    ['type' => 'edit', 'href' => route('admin.system-users.edit', $user)],
                ];

                if ((int) Auth::id() !== (int) $user->id) {
                    $actions[] = ['type' => 'delete', 'href' => route('admin.system-users.destroy', $user)];
                }

                return DataTableHelpers::actionsDropdown($actions);
            })
            ->rawColumns(['actions'])
            ->setRowId('id');
    }

    /** @return QueryBuilder<User> */
    public function query(User $model): QueryBuilder
    {
        $query = $model->newQuery();

        return Schema::hasTable('personal_access_tokens')
            ? $query->withCount('tokens')
            : $query->select(['users.*'])->selectRaw('0 as tokens_count');
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()->setTableId('system-users-table')->columns($this->getColumns())->minifiedAjax()->orderBy(0, 'asc')->responsive(true)->serverSide(true);
    }

    /** @return array<int, Column> */
    public function getColumns(): array
    {
        return [
            Column::make('name')->title('Name'),
            Column::make('email')->title('Email'),
            Column::computed('tokens_count')->title('API Tokens'),
            Column::computed('actions')->title('Actions'),
        ];
    }
}
