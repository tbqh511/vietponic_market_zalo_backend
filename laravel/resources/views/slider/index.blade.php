@extends('layouts.main')

@section('title')
    {{ __('Slider') }}
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
            <div class="card-content">
                <div class="card-body">
                    {!! Form::open(['url' => route('slider.store'), 'files' => true]) !!}
                    <div class="row mt-1">

                        <div class="form-group mandatory row">
                            {{ Form::label('image', __('Image'), ['class' => 'col-md-1 col-sm-12 col-form-label text-center']) }}
                            <div class="col-md-3 col-sm-12">
                                {{ Form::file('image[]', ['class' => 'form-control', 'multiple' => true, 'accept' => 'image/*']) }}
                                @if (count($errors) > 0)
                                    @foreach ($errors->all() as $error)
                                        <div class="alert alert-danger error-msg">{{ $error }}</div>
                                    @endforeach
                                @endif
                            </div>
                            {{ Form::label('category', __('Category'), ['class' => 'col-md-1 col-sm-12 col-form-label text-center']) }}
                            <div class="col-md-3 col-sm-12">
                                <select name="category" class="select2 form-select form-control-sm" id="categories"
                                    required="required">

                                    @if (isset($category))
                                        @foreach ($category as $row)
                                            <option value="{{ $row->id }}">{{ $row->category }} </option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>
                            {{ Form::label('property', __('Property'), ['class' => 'col-md-1 col-sm-12 col-form-label text-center']) }}
                            <div class="col-md-3 col-sm-12">
                                <select name="property" id="property" class="select2 form-select form-control-sm"
                                    required="required">

                                </select>
                            </div>
                        </div>
                        <div class="col-12 d-flex justify-content-end" style="padding: 1% 2%;">

                            {{ Form::submit(__('Save'), ['class' => 'btn btn-primary me-1 mb-1']) }}
                        </div>



                    </div>
                    {!! Form::close() !!}
                </div>
            </div>
        </div>
    </section>
    <section class="section">
        <div class="card">
            <form class="form" action="{{ route('slider.slider-order') }}" method="post">
                {{ csrf_field() }}

                <div class="card-content">

                    <div class="row mt-1">
                        <div class="card-body">
                            <div class="form-group row ">

                                <div class="col-12">
                                    <table class="table-light" aria-describedby="mydesc" class='table-striped'
                                        id="table_list" data-toggle="table" data-url="{{ url('sliderList') }}"
                                        data-click-to-select="true" data-side-pagination="server" data-pagination="true"
                                        data-page-list="[5, 10, 20, 50, 100, 200,All]" data-search="true"
                                        data-toolbar="#toolbar" data-show-columns="true" data-show-refresh="true"
                                        data-fixed-columns="true" data-fixed-number="1" data-fixed-right-number="1"
                                        data-trim-on-search="false" data-responsive="true" data-sort-name="id"
                                        data-sort-order="desc" data-pagination-successively-size="3"
                                        data-query-params="queryParams" data-id-field="id"
                                        data-editable-emptytext="Default empty text."
                                        data-editable-url="{{ route('slider.slider-order') }}">

                                        <thead>
                                            <tr>
                                                <th scope="col" data-field="id" data-align="center" data-sortable="true">
                                                    {{ __('ID') }}</th>
                                                <th scope="col" data-field="image" data-align="center"
                                                    data-sortable="false">{{ __('Image') }}</th>
                                                <th scope="col" data-field="category" data-align="center"
                                                    data-sortable="false">{{ __('Category') }}</th>
                                                <th scope="col" data-field="title" data-align="center"
                                                    data-sortable="false">{{ __('Property') }}</th>


                                                <th scope="col" data-field="operate" data-align="center"
                                                    data-sortable="false">{{ __('Action') }}</th>
                                            </tr>
                                        </thead>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
            </form>
        </div>
        </div>

        </div>
    </section>
@endsection

@section('script')
    <link href="{{ url('assets/extensions/bootstrap-table/editable/css/bootstrap-editable.css') }}" rel="stylesheet" />

    <script src="{{ url('assets/extensions/bootstrap-table/editable/js/bootstrap-editable.min.js') }} "></script>
    <script src="{{ url('assets/extensions/bootstrap-table/editable/bootstrap-table-editable.js') }} "></script>

    <script>
        $.fn.editable.defaults.mode = 'inline';
        $.fn.editableform.buttons =
            '<button type="submit" class="btn btn-primary btn-sm editable-submit"><i class="bi bi-check-lg"></i></button><button type="button" class="btn btn-danger btn-sm editable-cancel"><i class="bi bi-x-lg"></i></button>';



        $.fn.editable.defaults.params = function(params) {
            params._token = "{{ csrf_token() }}";

            // params._token = $("#_token").data("token");
            return params;
        };
    </script>

    <script>
        function queryParams(p) {
            return {
                sort: p.sort,
                order: p.order,
                offset: p.offset,
                limit: p.limit,
                search: p.search
            };
        }
        $(function() {
            $("#categories").change(function() {
                console.log('on');
                var id = $(this).val();
                $.ajax({
                    type: "GET",
                    url: "{{ route('slider.getpropertybycategory') }}",
                    dataType: 'json',
                    data: {
                        id: id
                    },

                    success: function(response) {
                        $('#property').empty();

                        if (response.error == false) {
                            $('#property').append($('<option>', {
                                value: '',
                                text: 'Choose option'

                            }));
                            $.each(response.data, function(i, item) {

                                var text_name = item.title + " - " + item.name;
                                $('#property').append($('<option>', {
                                    value: item.id,
                                    text: text_name

                                }));
                            });
                        } else {
                            $('#property').empty();
                        }
                    }
                });

            });
        });
    </script>
@endsection
