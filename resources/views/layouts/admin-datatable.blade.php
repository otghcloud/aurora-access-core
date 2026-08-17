@extends('layouts.admin')

@section('content')
    <div class="card">
        @hasSection('page-extra')
            <div class="card-body border-bottom">
                @yield('page-extra')
            </div>
        @endif

        <div class="card-header">
            <div class="row w-100 align-items-center g-3">
                <div class="col">
                    <h3 class="card-title mb-0">@yield('page-title')</h3>
                    @hasSection('page-table-description')
                        <p class="text-secondary mb-0">@yield('page-table-description')</p>
                    @endif
                </div>
                <div class="col-md-auto col-sm-12">
                    <div class="ms-auto d-flex flex-wrap gap-2 align-items-center">
                        <div class="input-group input-group-flat w-auto">
                            <span class="input-group-text" aria-hidden="true">
                                <i class="fa-solid fa-magnifying-glass"></i>
                            </span>
                            <input autocomplete="off" class="form-control" id="advanced-table-search" placeholder="Search" type="search">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-responsive-datatable">
            {{ $dataTable->table(['class' => 'table table-hover align-middle mb-0']) }}
        </div>
        <div class="card-footer datatable-card-footer"></div>
    </div>
@endsection

@push('scripts')
    {{ $dataTable->scripts(attributes: ['type' => 'module']) }}
@endpush
