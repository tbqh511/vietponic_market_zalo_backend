@extends('layouts.main')

@section('title')
    {{ __('Facility') }}
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
                            {{-- {{!! Form::open(['url' => route('parameters.store')]) !!}} --}}
                            {!! Form::open(['files' => true, 'data-parsley-validate']) !!}

                            <div class="row">
                                <div class="col-sm-12 col-md-4 form-group mandatory">

                                    {{ Form::label('type', __('Facility Name'), ['class' => 'form-label text-center']) }}


                                    {{ Form::text('parameter', '', ['class' => 'form-control', 'placeholder' => 'Facility', 'data-parsley-required' => 'true']) }}
                                </div>


                                <div class="col-md-4 col-sm-12 form-group">

                                    {{ Form::label('type', __('Type'), ['class' => 'form-label text-center']) }}
                                    <style>
                                        .select2-selection__placeholder {
                                            color: #a9a9a9;
                                        }
                                    </style>

                                    <select placeholder="select" name="options" id="options"
                                        class="select2 form-select form-control-sm" data-parsley-required=true>
                                        <option selected='false'></option>
                                        <option value="textbox">Text Box</option>
                                        <option value="textarea">Text Area</option>
                                        <option value="dropdown">Dropdown</option>
                                        <option value="radiobutton">Radio Button</option>
                                        <option value="checkbox">Checkbox</option>
                                        <option value="file">File</option>
                                        <option value="number">Number</option>
                                    </select>

                                </div>



                                <div class="col-md-4 form-group mandatory">
                                    {{ Form::label('image', __('Image'), ['class' => ' form-label text-center']) }}

                                    {{ Form::file('image', ['class' => 'form-control', 'data-parsley-required' => 'true', 'accept' => 'image/*']) }}


                                </div>


                                <input type="hidden" name="optionvalues" id="optionvalues">

                                <div class="row pt-5" id="elements">

                                </div>
                                <div class="col-sm-4 d-flex justify-content-start pt-3">
                                    {{ Form::submit(__('Save'), ['class' => 'btn btn-primary me-1 mb-1', 'id' => 'btn_submit']) }}
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
                            data-toggle="table" data-url="{{ url('parameter-list') }}" data-click-to-select="true"
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
                                    <th scope="col" data-field="type" data-sortable="true">{{ __('Type') }}</th>
                                    <th scope="col" data-field="value" data-sortable="true">{{ __('Value') }}</th>
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
                    <form action="{{ url('parameter-update') }}" class="form-horizontal" enctype="multipart/form-data"
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
                        <div class="row">

                            {{ Form::label('type', 'Type', ['class' => 'col-12 form-label mt-3']) }}
                            <div class="col-sm-12 col-md-12">



                                <select name="edit_options" id="edit_options" class="form-select form-control-sm">
                                    <option value=""> Select Option </option>
                                    <option value="textbox">Text Box</option>
                                    <option value="textarea">Text Area</option>
                                    <option value="dropdown">Dropdown</option>
                                    <option value="radiobutton">Radio Button</option>
                                    <option value="checkbox">Checkbox</option>
                                    <option value="file">File</option>
                                    <option value="number">Number</option>
                                </select>

                            </div>





                            <input type="hidden" name="edit_optionvalues" id="edit_optionvalues">

                            <div class="row pt-5" id="edit_elements">

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
        function queryParams(p) {
            return {
                sort: p.sort,
                order: p.order,
                offset: p.offset,
                limit: p.limit,
                search: p.search
            };
        }
        window.onload = function() {
            $('#add_options').hide();
            $('#edit_opt').hide();

        }
        $('#options').on('change', function() {

            selected_option = $('#options').val();
            if (selected_option == "radiobutton" || selected_option == "dropdown" ||
                selected_option == "checkbox") {
                $('#elements').empty();

                $('#add_options').show();

                $('#elements').append(
                    ' <div class="card" style="width:15rem;" id="op">' +
                    '<div class="row">' +
                    ' <div class="col-6">' +
                    ' <input type="text" class="form-control opt" name="opt[]" data-parsley-required="true">' +
                    '      </div>' +
                    ' <div class="col-1">' +

                    ' <button type="button" class="btn btn-primary me-1 mb-1" id="btn1"> x</button>' +
                    '</div>' +
                    ' </div>' +
                    '</div>' +
                    ' <div class="card" style="width: 15rem;" id="op">' +
                    '<div class="row">' +
                    ' <div class="col-6">' +
                    ' <lable class="form-lable" name="">Click to Add More   values </lable>' +
                    '      </div>' +
                    ' <div class="col-1">' +

                    ' <button type="button" class="btn btn-primary me-1 mb-1" id="button-addon2"> +</button>' +
                    '</div>' +
                    ' </div>' +
                    '</div>'

                );
                $('#button-addon2').click(function() {
                    console.log("on");

                    newRowAdd =

                        ' <div class="card" style="width:15rem;" id="op">' +
                        '<div class="row">' +
                        ' <div class="col-6">' +
                        ' <input type="text" class="form-control opt" name="opt[]">' +
                        '      </div>' +
                        ' <div class="col-1">' +

                        ' <button type="button" class="btn btn-primary me-1 mb-1" id="btn1"> x</button>' +
                        '</div>' +
                        ' </div>' +
                        '</div>';

                    $('#elements').append(

                        newRowAdd

                    );
                });
                $("body").on("click", "#btn1", function() {
                    $(this).parents("#op").remove();
                })

            } else {

                $('#elements').empty();

            }

        });

        sum = [];
        $('#btn_submit').click(function() {
            $('#elements :input').each(function() {
                sum.push($(this).val().trimEnd());
                console.log($(this).val());
            });
            $('#optionvalues').val(sum);
        });






        $('#edit_options').on('change', function() {

            selected_option = $('#edit_options').val();
            console.log(selected_option);

            if (selected_option == "radiobutton" || selected_option == "dropdown" ||
                selected_option == "checkbox") {
                $('#edit_elements').empty();



                $('#edit_elements').append(

                    ' <div class="card" style="width: 15rem;" id="op">' +
                    '<div class="row">' +
                    ' <div class="col-6">' +
                    ' <lable class="form-lable" name="">Click to Add values </lable>' +
                    '      </div>' +
                    ' <div class="col-1">' +

                    ' <button type="button" class="btn btn-primary me-1 mb-1" id="button-editon2"> +</button>' +
                    '</div>' +
                    ' </div>' +
                    '</div>'

                );
                $('#button-editon2').click(function() {
                    console.log("on");

                    newRowAdd =

                        ' <div class="card" style="width:15rem;" id="edit_op">' +
                        '<div class="row">' +
                        ' <div class="col-6">' +
                        ' <input type="text" class="form-control opt" name="edit_opt[]">' +
                        '      </div>' +
                        ' <div class="col-1">' +

                        ' <button type="button" class="btn btn-primary me-1 mb-1" id="btn2"> x</button>' +
                        '</div>' +
                        ' </div>' +
                        '</div>';

                    $('#edit_elements').append(

                        newRowAdd

                    );
                });
                $("body").on("click", "#btn2", function() {
                    $(this).parents("#edit_op").remove();
                })

            } else {

                $('#edit_elements').empty();

            }

        });






        function setValue(id) {

            $("#edit_id").val(id);
            $("#edit_name").val($("#" + id).parents('tr:first').find('td:nth-child(2)').text());
            $('#edit_options').val($("#" + id).parents('tr:first').find('td:nth-child(3)').text()).trigger('change');
            src = ($("#" + id).parents('tr:first').find('td:nth-child(5)').find($('.image-popup-no-margins'))).attr('href');
            console.log(src);
            $('#blah').attr('src', src);
            // $('#image').attr('src', src);

            if ($('#edit_options').val() == "checkbox" || $('#edit_options').val() == "radiobutton" || $('#edit_options')
                .val() == "dropdown") {
                val_str = ($("#" + id).parents('tr:first').find('td:nth-child(4)').text());
                arr = val_str.split(",");
                console.log(arr);

                $.each(arr, function(key, value) {


                    console.log(value);

                    newRowAdd =

                        ' <div class="card" style="width:15rem;" id="edit_op">' +
                        '<div class="row">' +
                        ' <div class="col-6">' +
                        ' <input type="text" class="form-control opt" name="edit_opt[]" value="' + value +
                        '">' +
                        '      </div>' +
                        ' <div class="col-1">' +

                        ' <button type="button" class="btn btn-primary me-1 mb-1" id="btn2"> x</button>' +
                        '</div>' +
                        ' </div>' +
                        '</div>';

                    $('#edit_elements').append(

                        newRowAdd

                    );
                });
            }

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
