import $ from 'jquery';
import DataTable from 'datatables.net-bs5';
import 'datatables.net-responsive-bs5';
import 'datatables.net-buttons-bs5';
import 'datatables.net-select-bs5';
import 'laravel-datatables-vite/js/dataTables.buttons.js';
import 'laravel-datatables-vite/js/dataTables.renderers.js';

window.DataTable = DataTable;

let defaultsConfigured = false;

function configureDataTableDefaults() {
    if (defaultsConfigured) {
        return;
    }

    $.extend(true, DataTable.ext.classes, {
        layout: {
            row: 'row justify-content-between'
        }
    });

    $.extend($.fn.dataTable.defaults, {
        lengthChange: false,
        pageLength: 25,
        layout: {
            topStart: null,
            topEnd: null,
            bottomStart: 'info',
            bottomEnd: 'paging'
        }
    });

    defaultsConfigured = true;
}

function relocateDataTableFooter(tableNode) {
    if (!tableNode) {
        return;
    }

    const $table = $(tableNode);
    const $wrapper = $table.closest('.dt-container, .dataTables_wrapper');

    if (!$wrapper.length) {
        return;
    }

    const $card = $wrapper.closest('.card');
    const $footer = $card.find('.datatable-card-footer').first();

    if (!$footer.length) {
        return;
    }

    let $layout = $footer.children('.datatable-footer-layout');
    if (!$layout.length) {
        $footer.html('<div class="datatable-footer-layout d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2"><div class="datatable-footer-info"></div><div class="datatable-footer-pagination"></div></div>');
        $layout = $footer.children('.datatable-footer-layout');
    }

    const $info = $wrapper.find('.dt-info, .dataTables_info').first();
    const $paging = $wrapper.find('.dt-paging, .dataTables_paginate').first();

    if ($info.length) {
        $layout.find('.datatable-footer-info').append($info);
    }

    if ($paging.length) {
        $layout.find('.datatable-footer-pagination').append($paging);
    }
}

function bindFooterRelocationEvents() {
    $(document)
        .off('init.dt.auroraAccess draw.dt.auroraAccess')
        .on('init.dt.auroraAccess draw.dt.auroraAccess', function (_event, settings) {
            relocateDataTableFooter(settings?.nTable);
        });
}

function bindAdvancedSearch() {
    $('body')
        .off('input.auroraAccessDataTable', '#advanced-table-search')
        .on('input.auroraAccessDataTable', '#advanced-table-search', function () {
            const table = $('table.dataTable').first().DataTable();

            table.search(this.value).draw();
        });
}

export function initDataTables() {
    configureDataTableDefaults();
    bindFooterRelocationEvents();
    bindAdvancedSearch();
}

export default initDataTables;
