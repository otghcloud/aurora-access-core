<?php

namespace OTGH\AccessControl\Core\DataTables\Admin;

use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use OTGH\AccessControl\Core\Helpers\DataTableHelpers;
use OTGH\AccessControl\Core\Models\Access\Event;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class AccessEventsDataTable extends DataTable
{
    /** @param  QueryBuilder<Event>  $query */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->editColumn('created_at', fn (Event $event): string => $event->created_at?->format('d/m/Y H:i:s') ?? '')
            ->editColumn('status_label', fn (Event $event): string => sprintf('<span class="badge text-bg-%s">%s</span>', $event->granted ? 'success' : 'secondary', e($event->status_label)))
            ->addColumn('granted_label', fn (Event $event): string => $event->granted ? 'Yes' : 'No')
            ->addColumn('user_name', fn (Event $event): string => e($event->accessUser?->name ?? '-'))
            ->addColumn('card_display', fn (Event $event): string => e($event->card_number ?? $event->accessCard?->card_number ?? '-'))
            ->addColumn('originator', function (Event $event): string {
                $label = $event->origin_label ?? '-';
                $route = null;

                if ($event->origin_type === 'lock' && $event->accessLock) {
                    $label = $event->accessLock->name ?: $label;
                    $route = route('admin.access-locks.show', $event->accessLock);
                } elseif ($event->origin_type === 'reader' && $event->originReader) {
                    $label = $event->originReader->name ?: $label;
                    $route = route('admin.access-readers.show', $event->originReader);
                } elseif ($event->origin_type === 'area' && $event->accessArea) {
                    $label = $event->accessArea->name ?: $label;
                    $route = route('admin.access-areas.edit', $event->accessArea);
                }

                return $route ? '<a href="'.e($route).'">'.e($label).'</a>' : e($label);
            })
            ->addColumn('area_name', fn (Event $event): string => e($event->accessArea?->name ?? '-'))
            ->addColumn('actions', function (Event $event): string {
                $actions = [];
                if (! $event->access_card_id && $event->card_number) {
                    $actions[] = ['type' => 'custom', 'label' => 'Create Card', 'href' => route('admin.access-cards.create', ['card_number' => $event->card_number, 'source_event_id' => $event->id])];
                }
                $actions[] = ['type' => 'view', 'label' => 'Details', 'href' => route('admin.access-events.show', $event)];

                return DataTableHelpers::actionsDropdown($actions);
            })
            ->rawColumns(['status_label', 'originator', 'actions'])
            ->setRowId('id');
    }

    /** @return QueryBuilder<Event> */
    public function query(Event $model): QueryBuilder
    {
        return $model->newQuery()->with(['accessUser', 'accessCard', 'originReader', 'accessArea', 'accessLock'])->latest('id');
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()->setTableId('access-events-table')->columns($this->getColumns())->minifiedAjax()->orderBy(0, 'desc')->responsive(true)->serverSide(true);
    }

    /** @return array<int, Column> */
    public function getColumns(): array
    {
        return [
            Column::make('created_at')->title('Time'),
            Column::make('status_label')->title('Status')->orderable(false),
            Column::make('granted_label')->title('Granted')->orderable(false),
            Column::make('user_name')->title('User')->orderable(false),
            Column::make('card_display')->title('Card')->orderable(false),
            Column::make('originator')->title('Originator')->orderable(false),
            Column::make('area_name')->title('Area')->orderable(false),
            Column::computed('actions')->title('Actions')->orderable(false)->searchable(false),
        ];
    }
}
