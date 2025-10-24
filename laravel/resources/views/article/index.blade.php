@extends('layouts.main')

@section('title')
    {{ __('Article') }}
@endsection

@section('page-title')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h4>@yield('title')</h4>

            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">

            </div>
        </div>
    </div>
@endsection


@section('content')
    <section class="section">
        <div class="card">
            @if (has_permissions('create', 'property'))
                <div class="card-header">

                    <div class="row ">

                        <div class="col-12 col-xs-12 d-flex justify-content-end">

                            {!! Form::open(['route' => 'article.create']) !!}
                            {{ method_field('get') }}
                            {{ Form::submit(__('Add Article'), ['class' => 'btn btn-primary']) }}
                            {!! Form::close() !!}
                        </div>

                    </div>
                </div>
            @endif

            <hr>
            <div class="card-body">

                {{-- <div class="row " id="toolbar"> --}}

                <div class="row">
                    <div class="col-12">

                        <table class="table-light" aria-describedby="mydesc" class='table-striped' id="table_list"
                            data-toggle="table" data-url="{{ url('article_list') }}" data-click-to-select="true"
                            data-side-pagination="server" data-pagination="true"
                            data-page-list="[5, 10, 20, 50, 100, 200,All]" data-search="true" data-search-align="right"
                            data-toolbar="#toolbar" data-show-columns="true" data-show-refresh="true"
                            data-fixed-columns="true" data-fixed-number="1" data-fixed-right-number="1"
                            data-trim-on-search="false" data-responsive="true" data-sort-name="id" data-sort-order="desc"
                            data-pagination-successively-size="3" data-query-params="queryParams">
                            <thead>
                                <tr>
                                    <th scope="col" data-field="id" data-align="center" data-sortable="true">
                                        {{ __('ID') }}</th>
                                    <th scope="col" data-field="image" data-align="center" data-sortable="true">
                                        {{ __('Image') }}
                                    </th>

                                    <th scope="col" data-field="title" data-align="center" data-sortable="false">
                                        {{ __('Title') }}</th>
                                    <th scope="col" data-field="description" style="text-overflow: ellipsis;"
                                        data-align="center" data-sortable="false">
                                        {{ __('Description') }}
                                    </th>

                                    @if (has_permissions('update', 'property_inquiry'))
                                        <th scope="col" data-field="operate" data-align="center" data-sortable="false">
                                            {{ __('Action') }}</th>
                                    @endif

                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>


            </div>
        </div>

    </section>
@endsection

@section('script')
    <script>
        function queryParams(p) {

            return {
                sort: p.sort,
                order: p.order,
                offset: p.offset,
                limit: p.limit,
                search: p.search,

            };
        }
    </script>
@endsection
