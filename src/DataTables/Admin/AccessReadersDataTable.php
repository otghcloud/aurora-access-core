<?php

namespace OTGH\AccessControl\Core\DataTables\Admin;

use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use OTGH\AccessControl\Core\Helpers\DataTableHelpers;
use OTGH\AccessControl\Core\Models\Hardware\Reader;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class AccessReadersDataTable extends DataTable
{
    /** @param  QueryBuilder<Reader>  $query */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('area_name', fn (Reader $reader): string => e($reader->area?->name ?? 'Unassigned'))
            ->addColumn('metadata_display', function (Reader $reader): string {
                $readerModel = e((string) data_get($reader->metadata, 'reader.model', '-'));
                $readerType = e((string) data_get($reader->metadata, 'reader.type', '-'));
                $lockModel = e((string) data_get($reader->metadata, 'lock.model', '-'));
                $lockType = e((string) data_get($reader->metadata, 'lock.type', '-'));

                return '<div><strong>Reader:</strong> '.$readerModel.' / '.$readerType.'</div><div><strong>Lock:</strong> '.$lockModel.' / '.$lockType.'</div>';
            })
            ->addColumn('actions', fn (Reader $reader): string => DataTableHelpers::actionsDropdown([
                ['type' => 'view', 'href' => route('admin.access-readers.show', $reader)],
                ['type' => 'edit', 'href' => route('admin.access-readers.edit', $reader)],
                ['type' => 'delete', 'href' => route('admin.access-readers.destroy', $reader)],
            ]))
            ->rawColumns(['metadata_display', 'actions'])
            ->filterColumn('area_name', fn (QueryBuilder $query, string $keyword) => $query->whereHas(
                'area',
                fn (QueryBuilder $areaQuery) => $areaQuery->whereRaw('LOWER(name) LIKE ?', ['%'.strtolower($keyword).'%'])
            ))
            ->orderColumn('area_name', fn (QueryBuilder $query, string $direction) => $query->orderByRaw(
                '(select name from areas where areas.id = '.$query->getModel()->getTable().'.area_id) '.(strtolower($direction) === 'desc' ? 'desc' : 'asc')
            ))
            ->setRowId('id');
    }

    /** @return QueryBuilder<Reader> */
    public function query(Reader $model): QueryBuilder
    {
        return $model->newQuery()->with('area');
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()->setTableId('access-readers-table')->columns($this->getColumns())->minifiedAjax()->orderBy(0, 'asc')->responsive(true)->serverSide(true);
    }

    /** @return array<int, Column> */
    public function getColumns(): array
    {
        return [
            Column::make('name')->title('Name'),
            Column::make('identifier')->title('Identifier'),
            Column::computed('area_name')->title('Area')->orderable(true)->searchable(true),
            Column::computed('metadata_display')->title('Metadata'),
            Column::computed('actions')->title('Actions'),
        ];
    }
}
