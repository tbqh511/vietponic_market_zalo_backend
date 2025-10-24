@extends('layouts.main')

@section('title')
    {{ __('near_by_places') }}
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
                        <h4>{{ __('Create Facility') }}</h4>
                    </div>
                </div>
            </div>
            <div class="card-content">
                <div class="card-body">
                    <div class="row">
                        <div class="col-12">
                            {!! Form::open(['files' => true, 'data-parsley-validate', 'url' => route('outdoor_facilities.store')]) !!}
                            <div class="row">
                                <div class="col-sm-12 col-md-4 form-group mandatory">
                                    {{ Form::label('type', __('Facility Name'), ['class' => 'form-label text-center']) }}
                                    {{ Form::text('facility', '', ['class' => 'form-control', 'placeholder' => 'Facility', 'data-parsley-required' => 'true']) }}
                                </div>
                                <div class="col-md-4 form-group mandatory">
                                    {{ Form::label('image', __('Image'), ['class' => ' form-label text-center']) }}

                                    {{ Form::file('image', ['class' => 'form-control', 'data-parsley-required' => 'true', 'accept' => 'image/*']) }}
                                </div>
                                <input type="hidden" name="optionvalues" id="optionvalues">
                                <div class="col-md-4 center" style="margin-top:1.5%">

                                    {{ Form::submit(__('Save'), ['class' => 'btn btn-primary me-1 mb-1', 'id' => 'btn_submit']) }}
                                </div>

                            </div>


                        </div>
                        {!! Form::close() !!}
                    </div>
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
                            data-toggle="table" data-url="{{ url('facility-list') }}" data-click-to-select="true"
                            data-side-pagination="server" data-pagination="true"
                            data-page-list="[5, 10, 20, 50, 100, 200,All]" data-search="true" data-toolbar="#toolbar"
                            data-show-columns="true" data-show-refresh="true" data-fixed-columns="true"
                            data-fixed-number="1" data-fixed-right-number="1" data-trim-on-search="false"
                            data-responsive="true" data-sort-name="id" data-sort-order="desc"
                            data-pagination-successively-size="3" data-query-params="queryParams">
                            <thead>
                                <tr>
                                    <th scope="col" data-field="id" data-sortable="true">{{ __('ID') }}</th>
                                    <th scope="col" data-field="name" data-sortable="true">{{ __('Name') }}</th>

                                    <th scope="col" data-field="image" data-sortable="false">{{ __('Image') }}</th>

                                    @if (has_permissions('update', 'type'))
                                        <th scope="col" data-field="operate" data-sortable="false">{{ __('Action') }}
                                        </th>
                                    @endif
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
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="myModalLabel1">{{ __('Edit Type') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form action="{{ url('facility-update') }}" class="form-horizontal" enctype="multipart/form-data"
                        method="POST" data-parsley-validate>
                        {{ csrf_field() }}

                        <input type="hidden" id="edit_id" name="edit_id">
                        <div class="row">
                            <div class="col-md-12 col-12 ">
                                <div class="form-group mandatory">
                                    <label for="edit_name" class="form-label col-12">{{ __('Name') }}</label>
                                    <input type="text" id="edit_name" class="form-control col-12" placeholder=""
                                        name="edit_name" data-parsley-required="true">
                                </div>
                            </div>
                        </div>
                        <div class="row">


                            {{ Form::label('image', __('Image'), ['class' => 'col-sm-12 col-form-label']) }}
                            <div class="col-md-12 col-12">


                                <input type="button" class="input-btn1-ghost-dashed bottomleft h-100" value="+">
                                <input accept="image/*" name='image' type='file' id="edit_image"
                                    style="display: none" />

                                <img id="blah" height="100" width="110" style="margin-left: 2%" />

                            </div>
                        </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary waves-effect"
                        data-bs-dismiss="modal">{{ __('Close') }}</button>
                    <button type="submit" class="btn btn-primary waves-effect waves-light"
                        id="btn_submit">{{ __('Save') }}</button>
                    </form>
                </div>
            </div>
        </div>
        <!-- /.modal-content -->
    </div>
    <!-- /.modal-dialog -->
    </div>
    <!-- EDIT MODEL -->
@endsection

@section('script')
    <script>
        function setValue(id) {

            $("#edit_id").val(id);
            $("#edit_name").val($("#" + id).parents('tr:first').find('td:nth-child(2)').text());
            $('#edit_options').val($("#" + id).parents('tr:first').find('td:nth-child(3)').text()).trigger('change');
            src = ($("#" + id).parents('tr:first').find('td:nth-child(3)').find($('.image-popup-no-margins'))).attr('href');
            console.log(src);
            $('#blah').attr('src', src);
        }


        $('.bottomleft').click(function() {
            $('#edit_image').click();

        });
        edit_image.onchange = evt => {
            const [file] = edit_image.files
            if (file) {
                blah.src = URL.createObjectURL(file)

            }
        }
    </script>
@endsection
