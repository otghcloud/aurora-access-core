<?php

namespace OTGH\AccessControl\Core\DataTables\Admin;

use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use OTGH\AccessControl\Core\Helpers\DataTableHelpers;
use OTGH\AccessControl\Core\Models\Hardware\Source;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class AccessSourcesDataTable extends DataTable
{
    /** @param  QueryBuilder<Source>  $query */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->editColumn('identifier', fn (Source $source): string => '<code>'.e($source->identifier).'</code>')
            ->editColumn('type', fn (Source $source): string => '<span class="badge text-bg-info text-uppercase">'.e($source->type).'</span>')
            ->editColumn('endpoint', fn (Source $source): string => '<code>'.e($source->endpoint ?? '-').'</code>')
            ->editColumn('enabled', fn (Source $source): string => sprintf(
                '<span class="badge text-bg-%s">%s</span>',
                $source->enabled ? 'success' : 'secondary',
                $source->enabled ? 'Enabled' : 'Disabled',
            ))
            ->addColumn('actions', function (Source $source): string {
                $testForm = '<form method="POST" action="'.e(route('admin.access-sources.test', $source)).'" class="d-inline">'
                    .'<input type="hidden" name="_token" value="'.e(csrf_token()).'">'
                    .'<button type="submit" class="dropdown-item">Test</button></form>';

                return '<div class="dropdown">'
                    .'<button class="btn btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"><i class="fa-solid fa-fw fa-gear" aria-hidden="true"></i><span class="visually-hidden">Actions</span></button>'
                    .'<div class="dropdown-menu dropdown-menu-end">'
                    .$testForm
                    .DataTableHelpers::actionsDropdown([
                        ['type' => 'edit', 'href' => route('admin.access-sources.edit', $source)],
                        ['type' => 'delete', 'href' => route('admin.access-sources.destroy', $source)],
                    ])
                    .'</div></div>';
            })
            ->rawColumns(['identifier', 'type', 'endpoint', 'enabled', 'actions'])
            ->setRowId('id');
    }

    /** @return QueryBuilder<Source> */
    public function query(Source $model): QueryBuilder
    {
        return $model->newQuery();
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()->setTableId('access-sources-table')->columns($this->getColumns())->minifiedAjax()->orderBy(0, 'asc')->responsive(true)->serverSide(true);
    }

    /** @return array<int, Column> */
    public function getColumns(): array
    {
        return [
            Column::make('name')->title('Name'),
            Column::make('identifier')->title('Identifier'),
            Column::make('type')->title('Type'),
            Column::make('endpoint')->title('Endpoint'),
            Column::make('enabled')->title('Enabled')->searchable(false),
            Column::computed('actions')->title('Actions'),
        ];
    }
}
