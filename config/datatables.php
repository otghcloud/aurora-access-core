<?php

use Yajra\DataTables\ApiResourceDataTable;
use Yajra\DataTables\CollectionDataTable;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\PaginatorDataTable;
use Yajra\DataTables\QueryDataTable;

return [
    'search' => [
        'smart' => true,
        'multi_term' => true,
        'case_insensitive' => true,
        'use_wildcards' => false,
        'starts_with' => false,
    ],
    'index_column' => 'DT_RowIndex',
    'engines' => [
        'eloquent' => EloquentDataTable::class,
        'query' => QueryDataTable::class,
        'collection' => CollectionDataTable::class,
        'paginator' => PaginatorDataTable::class,
        'resource' => ApiResourceDataTable::class,
    ],
    'builders' => [],
    'nulls_last_sql' => ':column :direction NULLS LAST',
    'error' => env('DATATABLES_ERROR', null),
    'columns' => [
        'excess' => ['rn', 'row_num'],
        'escape' => '*',
        'raw' => ['action'],
        'blacklist' => ['password', 'remember_token'],
        'whitelist' => '*',
    ],
    'json' => [
        'header' => [],
        'options' => 0,
    ],
    'callback' => ['$', '$.', 'function'],
];
