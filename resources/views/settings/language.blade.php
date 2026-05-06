@php
    
    $lang = Session::get('language');
    // dd($lang);
@endphp

@extends('layouts.main')

@section('title')
    {{ __('Languages') }}
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

            <div class="card-header">

                <div class="divider">
                    <div class="divider-text">
                        <h4>{{ __('Add Language') }}</h4>
                    </div>
                </div>
            </div>

            <div class="card-content">
                <div class="card-body">
                    <div class="row form-group">
                        <div class="col-sm-12 col-md-12 form-group">
                            {!! Form::open(['url' => route('language.store'), 'files' => true, 'data-parsley-validate']) !!}


                            <div class="row">
                                <div class="col-sm-12 col-md-4 form-group mandatory ">

                                    {{ Form::label('Language Name', __('Language Name'), ['class' => 'form-label text-center']) }}
                                    {{ Form::text('name', '', ['class' => 'form-control', 'placeholder' => 'language name', 'data-parsley-required' => 'true']) }}

                                </div>

                                <div class="col-sm-12 col-md-4 form-group mandatory ">

                                    {{ Form::label('Language Code', __('Language Code'), ['class' => 'form-label text-center']) }}
                                    {{ Form::text('code', '', ['class' => 'form-control', 'placeholder' => 'language code', 'data-parsley-required' => 'true']) }}

                                </div>
                                <div class="col-sm-1 col-md-1">

                                    {{ Form::label('file', 'RTL', ['class' => 'col-form-label text-center']) }}
                                    <div class="form-check form-switch col-12"
                                        style={{ !empty($lang) ? (!$lang->rtl ? 'padding-left: 12.5rem;' : 'padding-right:12.5rem;') : 'padding-left: 12.5rem;' }}>
                                        {{ Form::checkbox('rtlswitch', '', false, ['class' => 'form-check-input', 'id' => 'rtlswitch']) }}
                                        <input type="hidden" name="rtl" id="rtl">
                                    </div>
                                </div>
                                <div class="col-sm-1 col-md-1">

                                    {{ Form::label('file', __('Sample for Admin Panel'), ['class' => 'col-form-label text-center']) }}
                                    <div class="form-check form-switch col-12"
                                        style={{ !empty($lang) ? (!$lang->rtl ? 'padding-left: 12.5rem;' : 'padding-right:12.5rem;') : 'padding-left: 12.5rem;' }}>
                                        <a class="btn icon btn-primary btn-sm rounded-pill"
                                            data-status="' . $row->status . '" href="{{ route('downloadPanelFile') }}"
                                            title="Edit"><i class="bi bi-download"></i></a>
                                    </div>
                                </div>
                                <div class="col-sm-1 col-md-1">

                                    {{ Form::label('file', __('Sample For App'), ['class' => 'col-form-label text-center']) }}
                                    <div class="form-check form-switch col-12"
                                        style={{ !empty($lang) ? (!$lang->rtl ? 'padding-left: 12.5rem;' : 'padding-right:12.5rem;') : 'padding-left: 12.5rem;' }}>
                                        <a class="btn icon btn-primary btn-sm rounded-pill"
                                            data-status="' . $row->status . '" href="{{ route('downloadAppFile') }}"
                                            title="Edit"><i class="bi bi-download"></i></a>
                                    </div>
                                </div>
                            </div>
                            <div class="row">

                                <div class="col-sm-2 col-md-4 form-group mandatory">

                                    {{ Form::label('file', __('File For Admin Panel'), ['class' => 'form-label  text-center', 'accept' => '.json.*']) }}
                                    {{ Form::file('file_for_panel', ['class' => 'form-control', 'language code', 'data-parsley-required' => 'true', 'accept' => '.json']) }}

                                </div>
                                <div class="col-sm-2 col-md-4  form-group mandatory">

                                    {{ Form::label('file', __('File For App'), ['class' => 'form-label text-center', 'accept' => '.json.*']) }}
                                    {{ Form::file('file', ['class' => 'form-control', 'data-parsley-required' => 'true', 'accept' => '.json']) }}

                                </div>
                                <div class="col-sm-2 col-md-4 justify-content-center" style="margin-top: 2.5%">
                                    {{ Form::submit(__('Save'), ['class' => 'btn btn-primary me-1 mb-1']) }}

                                </div>

                            </div>
                        </div>

                        <div class="col-sm-12 d-flex justify-content-end">
                        </div>

                        {!! Form::close() !!}

                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-12">
                        <table class="table-light" aria-describedby="mydesc" class='table-striped' id="table_list"
                            data-toggle="table" data-url="{{ url('language_list') }}" data-click-to-select="true"
                            data-side-pagination="server" data-pagination="true"
                            data-page-list="[5, 10, 20, 50, 100, 200,All]" data-search="true" data-toolbar="#toolbar"
                            data-show-columns="true" data-show-refresh="true" data-fixed-columns="true"
                            data-fixed-number="1" data-fixed-right-number="1" data-trim-on-search="false"
                            data-responsive="true" data-sort-name="id" data-sort-order="desc"
                            data-pagination-successively-size="3" data-query-params="queryParams">
                            <thead>
                                <tr>
                                    <th scope="col" data-field="id" data-sortable="true">{{ __('ID') }}</th>
                                    <th scope="col" data-field="name" data-sortable="false">{{ __('Name') }}</th>
                                    <th scope="col" data-field="code" data-sortable="true">{{ __('Code') }}</th>
                                    <th scope="col" data-field="rtl" data-sortable="true">{{ __('Is RTL') }}
                                    <th scope="col" data-field="operate" data-sortable="false">{{ __('Action') }}</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>


    <!-- EDIT MODEL MODEL -->
    <div id="editModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel1" aria-hidden="true">
        <div class="modal-dialog">
            <form action="{{ url('language_update') }}" class="form-horizontal" enctype="multipart/form-data"
                method="POST" data-parsley-validate>
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="myModalLabel1">{{ __('Edit Language') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        {{ csrf_field() }}
                        <input type="hidden" id="old_image" name="old_image">
                        <input type="hidden" id="edit_id" name="edit_id">
                        <div class="row">
                            <div class="col-sm-12">
                                <div class="col-md-12 col-12">
                                    <div class="form-group mandatory">
                                        <label for="edit_language"
                                            class="form-label col-12">{{ __('Language name') }}</label>
                                        <input type="text" id="edit_language_name" class="form-control col-12"
                                            placeholder="Name" name="edit_language_name" data-parsley-required="true">
                                    </div>
                                </div>

                            </div>
                            <div class="col-sm-12">

                                <div class="col-md-12 col-12">
                                    <div class="form-group mandatory">
                                        <label for="edit_language"
                                            class="form-label col-12">{{ __('Language Code') }}</label>
                                        <input type="text" id="edit_language_code" class="form-control col-12"
                                            placeholder="Name" name="edit_language_code" data-parsley-required="true">
                                    </div>
                                </div>

                            </div>

                            <div class="col-sm-12">
                                <div class="col-md-12 col-12">
                                    <div class="form-group">
                                        <label for="edit_json"
                                            class="form-label col-12">{{ __('File For Admin Panel') }}</label>
                                        <input type="file" id="edit_json" class="form-control col-12"
                                            name="edit_json">


                                        @if (count($errors) > 0)
                                            @foreach ($errors->all() as $error)
                                                <div class="alert alert-danger error-msg">{{ $error }}</div>
                                            @endforeach
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-12">
                                <div class="col-md-12 col-12">
                                    <div class="form-group">
                                        <label for="edit_json" class="form-label col-12">{{ __('File For App') }}</label>
                                        <input type="file" id="edit_json_admin" class="form-control col-12"
                                            name="edit_json">


                                        @if (count($errors) > 0)
                                            @foreach ($errors->all() as $error)
                                                <div class="alert alert-danger error-msg">{{ $error }}</div>
                                            @endforeach
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-12">
                                <div class="col-md-12 col-12">
                                    <div class="form-group form-check form-switch">
                                        <label for="edit_json" class="form-label col-12">{{ __('RTL') }}</label>
                                        <input type="checkbox" class="form-check-input" name="edit_rtl" id="edit_rtl">

                                        @if (count($errors) > 0)
                                            @foreach ($errors->all() as $error)
                                                <div class="alert alert-danger error-msg">{{ $error }}</div>
                                            @endforeach
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>


                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary waves-effect"
                            data-bs-dismiss="modal">{{ __('Close') }}</button>

                        <button type="submit"
                            class="btn btn-primary waves-effect waves-light">{{ __('Save') }}</button>
                    </div>
            </form>

        </div>
        <!-- /.modal-content -->
    </div>
    <!-- /.modal-dialog -->
    </div>
    <!-- EDIT MODEL -->
@endsection
@section('script')
    <script>
        $("#rtlswitch").on('change', function() {
            $("#rtlswitch").is(':checked') ? $("#rtl").val(1) : $("#rtl")
                .val(0);
        });

        function setValue(id) {

            $("#edit_id").val(id);
            $("#edit_language_name").val($("#" + id).parents('tr:first').find('td:nth-child(2)').text());
            $("#edit_language_code").val($("#" + id).parents('tr:first').find('td:nth-child(3)').text());
            $("#edit_rtl").prop('checked', $("#" + id).parents('tr:first').find('td:nth-child(4)').text() === "Yes");

        }

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

