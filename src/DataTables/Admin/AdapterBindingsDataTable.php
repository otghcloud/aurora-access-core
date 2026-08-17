<?php

namespace OTGH\AccessControl\Core\DataTables\Admin;

use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Illuminate\Http\Request;
use OTGH\AccessControl\Core\Enums\AccessControl\AccessBindingActionKey;
use OTGH\AccessControl\Core\Helpers\DataTableHelpers;
use OTGH\AccessControl\Core\Models\Hardware\AdapterBinding;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class AdapterBindingsDataTable extends DataTable
{
    public function __construct(private readonly Request $httpRequest)
    {
        parent::__construct();
    }

    /** @param  QueryBuilder<AdapterBinding>  $query */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->editColumn('direction', fn (AdapterBinding $binding): string => '<span class="badge text-bg-'.($binding->direction === 'input' ? 'info' : 'primary').'">'.e(strtoupper($binding->direction)).'</span>')
            ->editColumn('adapter_type', fn (AdapterBinding $binding): string => '<code>'.e($binding->adapter_type).'</code>')
            ->editColumn('action_key', function (AdapterBinding $binding): string {
                $action = AccessBindingActionKey::fromStored($binding->action_key);
                $label = '<code>'.e($action?->key() ?? $binding->action_key).'</code>';

                return $action ? $label.'<div class="small text-muted">'.e($action->label()).'</div>' : $label;
            })
            ->addColumn('target', fn (AdapterBinding $binding): string => '<code>'.e($binding->target_type.'#'.$binding->target_id).'</code>')
            ->addColumn('source_name', fn (AdapterBinding $binding): string => e($binding->source?->name ?? '-'))
            ->editColumn('channel', fn (AdapterBinding $binding): string => '<code>'.e($binding->channel ?? '-').'</code>')
            ->addColumn('reversed', fn (AdapterBinding $binding): string => $binding->signal_reversed ? 'Yes' : 'No')
            ->addColumn('enabled_label', fn (AdapterBinding $binding): string => $binding->enabled ? 'Yes' : 'No')
            ->addColumn('actions', fn (AdapterBinding $binding): string => DataTableHelpers::actionsDropdown([
                ['type' => 'edit', 'href' => route('admin.access-bindings.edit', $binding)],
                ['type' => 'delete', 'href' => route('admin.access-bindings.destroy', $binding)],
            ]))
            ->rawColumns(['direction', 'adapter_type', 'action_key', 'target', 'channel', 'actions'])
            ->filterColumn('source_name', fn (QueryBuilder $query, string $keyword) => $query->whereHas(
                'source',
                fn (QueryBuilder $sourceQuery) => $sourceQuery->whereRaw('LOWER(name) LIKE ?', ['%'.strtolower($keyword).'%'])
            ))
            ->setRowId('id');
    }

    /** @return QueryBuilder<AdapterBinding> */
    public function query(AdapterBinding $model): QueryBuilder
    {
        return $model->newQuery()->with('source')
            ->when($this->httpRequest->filled('direction'), fn (QueryBuilder $query) => $query->where('direction', $this->httpRequest->string('direction')->toString()))
            ->when($this->httpRequest->filled('adapter_type'), fn (QueryBuilder $query) => $query->where('adapter_type', $this->httpRequest->string('adapter_type')->toString()))
            ->when($this->httpRequest->filled('target_type'), fn (QueryBuilder $query) => $query->where('target_type', $this->httpRequest->string('target_type')->toString()))
            ->when($this->httpRequest->filled('target_id'), fn (QueryBuilder $query) => $query->where('target_id', (int) $this->httpRequest->input('target_id')))
            ->when($this->httpRequest->filled('source_id'), fn (QueryBuilder $query) => $query->where('source_id', (int) $this->httpRequest->input('source_id')))
            ->when($this->httpRequest->filled('enabled'), fn (QueryBuilder $query) => $query->where('enabled', $this->httpRequest->boolean('enabled')))
            ->when($this->httpRequest->filled('action_key'), function (QueryBuilder $query): void {
                $action = AccessBindingActionKey::fromStored($this->httpRequest->input('action_key'));
                $action instanceof AccessBindingActionKey
                    ? $query->whereIn('action_key', $action->queryCandidates())
                    : $query->whereRaw('1 = 0');
            });
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()->setTableId('adapter-bindings-table')->columns($this->getColumns())->minifiedAjax()->orderBy(1, 'asc')->responsive(true)->serverSide(true);
    }

    /** @return array<int, Column> */
    public function getColumns(): array
    {
        return [
            Column::make('direction')->title('Direction'),
            Column::make('adapter_type')->title('Adapter'),
            Column::make('action_key')->title('Action'),
            Column::computed('target')->title('Target'),
            Column::computed('source_name')->title('Source')->searchable(true),
            Column::make('channel')->title('Channel'),
            Column::computed('reversed')->title('Reversed'),
            Column::computed('enabled_label')->title('Enabled'),
            Column::computed('actions')->title('Actions'),
        ];
    }
}
