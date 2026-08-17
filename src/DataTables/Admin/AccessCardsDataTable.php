<?php

namespace OTGH\AccessControl\Core\DataTables\Admin;

use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use OTGH\AccessControl\Core\Helpers\DataTableHelpers;
use OTGH\AccessControl\Core\Models\Access\Card;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class AccessCardsDataTable extends DataTable
{
    /**
     * @param  QueryBuilder<Card>  $query
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->editColumn('active', fn (Card $card): string => sprintf(
                '<span class="badge text-bg-%s">%s</span>',
                $card->active ? 'success' : 'secondary',
                $card->active ? 'Active' : 'Inactive',
            ))
            ->editColumn('description', fn (Card $card): string => e($card->description ?: '-'))
            ->addColumn('user_name', fn (Card $card): string => e($card->user?->name ?? '-'))
            ->addColumn('actions', fn (Card $card): string => DataTableHelpers::actionsDropdown([
                ['type' => 'edit', 'href' => route('admin.access-cards.edit', $card)],
                ['type' => 'delete', 'href' => route('admin.access-cards.destroy', $card)],
            ]))
            ->rawColumns(['active', 'actions'])
            ->setRowId('id');
    }

    /** @return QueryBuilder<Card> */
    public function query(Card $model): QueryBuilder
    {
        return $model->newQuery()->with('user')->latest('id');
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()->setTableId('access-cards-table')->columns($this->getColumns())->minifiedAjax()->orderBy(0, 'desc')->responsive(true)->serverSide(true);
    }

    /** @return array<int, Column> */
    public function getColumns(): array
    {
        return [
            Column::make('card_number')->title('Card Number'),
            Column::make('user_name')->title('User'),
            Column::make('active')->title('Active')->orderable(false),
            Column::make('description')->title('Description'),
            Column::computed('actions')->title('Actions')->orderable(false)->searchable(false),
        ];
    }
}
