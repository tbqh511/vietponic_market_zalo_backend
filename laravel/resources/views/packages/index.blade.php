@extends('layouts.main')

@section('title')
    {{ __('Packages') }}
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

            <div class="card">
                {!! Form::open(['route' => 'package.store', 'data-parsley-validate', 'files' => true]) !!}
                <div class="card-body">

                    <div class="row ">

                        <div class="col-md-2 col-12 form-group mandatory">

                            {{ Form::label('name', __('Name'), ['class' => 'form-label col-12 ']) }}
                            {{ Form::text('name', '', ['class' => 'form-control ', 'placeholder' => 'Package Name', 'data-parsley-required' => 'true', 'id' => 'name']) }}

                        </div>
                        <div class="col-md-2 col-12 form-group ">

                            {{ Form::label('duration', __('Duration'), ['class' => 'form-label col-12 ']) }}
                            {{ Form::number('duration', '', ['class' => 'form-control ', 'placeholder' => 'Package Duration (in days)', 'id' => 'duration', 'min' => '1']) }}

                        </div>
                        <div class="col-md-2 col-12 form-group mandatory">

                            {{ Form::label('price', __('price') . '(' . $currency_symbol . ')', ['class' => 'form-label col-12 ']) }}
                            {{ Form::number('price', '', ['class' => 'form-control ', 'placeholder' => 'Package Price', 'data-parsley-required' => 'true', 'id' => 'price', 'min' => '1']) }}

                        </div>

                        <div id="property_limitation" class="col-md-2 col-sm-12 form-group">
                            {{ Form::label('price', __('Property'), ['class' => 'form-label col-12 ']) }}

                            <input type="radio" id="add_property" name="typep" value="add_limited_property">
                            <label for="age1">{{ __('Limited') }}</label>

                            <br>
                            <input type="radio" id="add_property" name="typep" value="add_unlimited_property">
                            <label for="age1">{{ __('Unlimited') }}</label>


                        </div>
                        <div id="limitation_for_property" class="col-md-1 col-sm-12 form-group">
                            {{ Form::label('limit', __('Limit'), ['class' => 'form-label col-12 ']) }}

                            {{ Form::number('property_limit', '', ['class' => 'form-control', 'type' => 'number', 'min' => '1', 'placeholder' => 'limitation', 'id' => 'propertylimit', 'min' => '1']) }}

                        </div>

                        <div id="advertisement_limitation" class="col-md-2 col-sm-12 form-group">
                            {{ Form::label('price', __('Advertisement'), ['class' => 'form-label col-12']) }}

                            <input type="radio" id="add_advertisement" name="typel" value="add_limited_advertisement">
                            <label for="age1">{{ __('Limited') }}</label>
                            <br>

                            <input type="radio" id="add_advertisement" name="typel" value="add_unlimited_advertisement">
                            <label for="age1">{{ __('Unlimited') }}</label>

                        </div>


                        {{-- </div> --}}
                        <div id="limitation_for_advertisement" class="col-md-1 col-sm-12 form-group">

                            {{ Form::label('limit', __('Limit'), ['class' => 'form-label col-12 ']) }}


                            {{ Form::number('advertisement_limit', '', ['class' => 'form-control ', 'type' => 'number', 'min' => '1', 'placeholder' => 'limitation ', 'id' => 'advertisementlimit', 'min' => '1']) }}



                        </div>

                    </div>

                    <div class="col-md-2 col-12  form-group pt-4">

                        {{ Form::submit('Add Package', ['class' => 'center btn btn-primary', 'style' => 'width:200']) }}


                    </div>


                </div>
                {!! Form::close() !!}
            </div>
        </div>
        @if (has_permissions('create', 'property'))
            <div class="card-header">

                <div class="row ">

                </div>
        @endif

        <hr>
        <div class="card-body">

            {{-- <div class="row " id="toolbar"> --}}

            <div class="row">
                <div class="col-12">

                    <table class="table-light" aria-describedby="mydesc" class='table-striped' id="table_list"
                        data-toggle="table" data-url="{{ url('package_list') }}" data-click-to-select="true"
                        data-side-pagination="server" data-pagination="true" data-page-list="[5, 10, 20, 50, 100, 200,All]"
                        data-search="true" data-search-align="right" data-toolbar="#toolbar" data-show-columns="true"
                        data-show-refresh="true" data-fixed-columns="true" data-fixed-number="1" data-fixed-right-number="1"
                        data-trim-on-search="false" data-responsive="true" data-sort-name="id" data-sort-order="desc"
                        data-pagination-successively-size="3" data-query-params="queryParams">
                        <thead>
                            <tr>
                                <th scope="col" data-field="id" data-align="center" data-sortable="true">
                                    {{ __('ID') }}</th>
                                <th scope="col" data-field="name" data-align="center" data-sortable="true">
                                    {{ __('Name') }}
                                </th>

                                <th scope="col" data-field="duration" data-align="center" data-sortable="false">
                                    {{ __('Duration') }}</th>
                                <th scope="col" data-field="price" data-align="center" data-sortable="false">
                                    {{ __(' Price') }}
                                </th>
                                <th scope="col" data-field="property_limit" data-align="center" data-sortable="false">
                                    {{ __('Limit For Property') }}
                                </th>
                                <th scope="col" data-field="advertisement_limit" data-align="center"
                                    data-sortable="false">
                                    {{ __('Limit For Advertisement') }}
                                </th>
                                <th scope="col" data-field="status" data-align="center" data-sortable="false">
                                    {{ __('Status') }}
                                </th>
                                <th scope="col" data-field="enble_disable" data-sortable="false" data-align="center"
                                    data-width="5%">
                                    {{ __('Enable/Disable') }}</th>

                                @if (has_permissions('update', 'property_inquiry'))
                                    <th scope="col" data-field="operate" data-align="center" data-sortable="false">
                                        {{ __(' Action') }}</th>
                                @endif

                            </tr>
                        </thead>
                    </table>
                </div>
            </div>


        </div>
        </div>


        <!-- EDIT MODEL MODEL -->
        <div id="editModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel1"
            aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="myModalLabel1">{{ __('Edit Package') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">

                        <form action="{{ url('package-update') }}" class="form-horizontal" enctype="multipart/form-data"
                            method="POST" data-parsley-validate>

                            {{ csrf_field() }}


                            <input type="hidden" id="edit_id" name="edit_id">
                            <div class="row">
                                <div class="col-sm-12">

                                    <div class="col-md-12 col-12">
                                        <div class="form-group mandatory">
                                            <label for="edit_name" class="form-label col-12 ">{{ __('Name') }}</label>
                                            <input type="text" id="edit_name" class="form-control col-12"
                                                placeholder="Name" name="edit_name" data-parsley-required="true">
                                        </div>
                                    </div>

                                </div>

                                <div class="col-sm-12">

                                    <div class="col-md-12 col-12">
                                        <div class="form-group ">
                                            <label for="edit_duration"
                                                class="form-label col-12 ">{{ __('Duration') }}</label>
                                            <input type="text" id="edit_duration" class="form-control col-12"
                                                placeholder="Name" name="edit_duration">
                                        </div>
                                    </div>

                                </div>

                                <div class="col-sm-12">

                                    <div class="col-md-12 col-12">
                                        <div class="form-group mandatory">
                                            <label for="edit_price"
                                                class="form-label col-12 ">{{ __('Price') }}</label>
                                            <input type="text" id="edit_price" class="form-control col-12"
                                                placeholder="Name" name="edit_price" data-parsley-required="true">
                                        </div>
                                    </div>

                                </div>
                                <div class="col-sm-12 col-12">
                                    <div class="form-group mandatory">
                                        <label for="email" class="form-label col-12 ">{{ __('Status') }}</label>
                                        {!! Form::select('status', ['0' => 'OFF', '1' => 'ON'], '', [
                                            'class' => 'form-select',
                                            'id' => 'status',
                                        ]) !!}

                                    </div>
                                </div>


                                <div class="row">

                                    {{-- <div id="property_limitation" class="col-md-12 col-6"> --}}
                                    {{ Form::label('price', __('Property'), ['class' => 'form-label col-sm-12 col-md-12']) }}


                                    <div class="col-sm-12 col-md-12 form-group mandatory" id="for_edit_property">

                                        <input type="radio" id="edit_property" name="edit_typep"
                                            value="edit_limited_property">
                                        <label for="age1">{{ __('Limited') }}</label>

                                        <br>
                                        <input type="radio" id="edit_property" name="edit_typep"
                                            value="edit_unlimited_property">
                                        <label for="age1">{{ __('Unlimited') }}</label>


                                    </div>

                                    <div class="col-sm-12 col-md-6 form-group" id="limitation_for_property">

                                        {{-- {{ Form::label('limitation', 'limitation for edit property', ['class' => 'form-label col-12 ']) }} --}}
                                        {{ Form::text('property_limit', '', ['class' => 'form-control ', 'placeholder' => 'limitation', 'id' => 'property_limit']) }}

                                    </div>
                                    {{-- </div> --}}
                                </div>

                                {{-- </div>
                                <div id="advertisement_limitation" class="col-md-12 col-12"> --}}

                                <div class="row">
                                    {{ Form::label('price', __('Advertisement'), ['class' => 'form-label col-sm-12 col-md-12 ']) }}

                                    <div class="col-sm-12 col-md-6 form-group mandatory" id="for_edit_advertisement">

                                        <input type="radio" id="edit_limited_advertisement" name="edit_typel"
                                            value="edit_limited_advertisement">
                                        <label for="age1">{{ __('Limited') }}</label>
                                        <br>

                                        <input type="radio" id="edit_advertisement" name="edit_typel"
                                            value="edit_unlimited_advertisement">
                                        <label for="age1">{{ __('Unlimited') }}</label>


                                    </div>

                                    <div class="col-sm-12 col-md-6 form-group " id="limitation_for_advertisement">

                                        {{ Form::text('advertisement_limit', '', ['class' => 'form-control ', 'placeholder' => 'limitation ', 'id' => 'advertisement_limit']) }}


                                    </div>
                                </div>

                            </div>

                    </div>



                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary waves-effect"
                            data-bs-dismiss="modal">{{ __('Close') }}</button>

                        <button type="submit"
                            class="btn btn-primary waves-effect waves-light">{{ __('Save') }}</button>
                        </form>
                    </div>
                </div>
            </div>
            <!-- /.modal-content -->
        </div>
        <!-- /.modal-dialog -->
        </div>
        <!-- EDIT MODEL -->
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


        function setValue(id) {

            $("#edit_id").val(id);
            $("#edit_name").val($("#" + id).parents('tr:first').find('td:nth-child(2)').text());
            $("#edit_duration").val($("#" + id).parents('tr:first').find('td:nth-child(3)').text());
            $("#edit_price").val($("#" + id).parents('tr:first').find('td:nth-child(4)').text());
            property_limit = ($("#" + id).parents('tr:first').find('td:nth-child(5)').text());
            advertisement_limit = ($("#" + id).parents('tr:first').find('td:nth-child(6)').text());

            if (property_limit != "") {
                console.log("inpp");

                $('input[type="radio"][name="edit_typep"][value="edit_limited_property"]').attr('checked', true);
                $('#property_limit').val(property_limit);
                if (property_limit == "unlimited") {
                    $('input[type="radio"][name="edit_typep"][value="edit_unlimited_property"]').attr('checked', true);
                    $('#property_limit').val();


                }
            } else {
                $('#property_limit').val('');
                $('input[type="radio"][name="edit_typep"][value="edit_limited_property"]').attr('checked', false);

                $('input[type="radio"][name="edit_typep"][value="edit_unlimited_property"]').attr('checked', false);

            }

            if (advertisement_limit != "") {
                console.log("in");
                $('input[type="radio"][name="edit_typel"][value="edit_limited_advertisement"]').attr('checked', true);
                $('#advertisement_limit').val(advertisement_limit);
                if (advertisement_limit == "Unlimited") {
                    $('input[type="radio"][name="edit_typel"][value="edit_unlimited_advertisement"]').attr('checked', true);
                    $('#advertisement_limit').val();


                }
            } else {
                $('#advertisement_limit').val('');
                $('input[type="radio"][name="edit_typel"][value="edit_limited_advertisement"]').attr('checked', false);
                $('input[type="radio"][name="edit_typel"][value="edit_unlimited_advertisement"]').attr('checked', false);

            }




        }
    </script>

    <script>
        window.onload = function() {

            $('#limitation_for_property').hide();

            $('#limitation_for_advertisement').hide();



        }


        $('input[type="radio"][name="edit_typep"]').click(function() {
            console.log("click");
            if ($(this).is(':checked')) {
                if ($(this).val() == 'edit_limited_property') {

                    $('#property_limit').val("").attr('required', 'true');



                } else {

                    $('#property_limit').val("Unlimited");


                }
            }
        });
        $('input[type="radio"][name="edit_typel"]').click(function() {
            console.log("click");

            if ($(this).is(':checked')) {
                if ($(this).val() == 'edit_limited_advertisement') {

                    $('#advertisement_limit').val("").attr('required', 'true');



                } else {

                    $('#advertisement_limit').val("Unlimited");


                }
            }
        });

        $('input[type="radio"][name="typep"]').click(function() {
            console.log("click typep");

            if ($(this).is(':checked')) {
                if ($(this).val() == 'add_limited_property') {
                    // $('#for_add_property').toggle();
                    $('#limitation_for_property').show();
                    $('#propertylimit').attr('required', 'true');



                } else {
                    $('#limitation_for_property').hide();
                    $('#propertylimit').removeAttr('required');



                }
            }
        });
        $('input[type="radio"][name="typel"]').click(function() {
            console.log("click typel");


            if ($(this).is(':checked')) {
                if ($(this).val() == 'add_limited_advertisement') {
                    // $('#for_add_property').toggle();
                    $('#limitation_for_advertisement').show();
                    $('#advertisementlimit').attr("required", "true");




                } else {
                    $('#limitation_for_advertisement').hide();
                    $('#advertisementlimit').removeAttr("required");



                }
            }
        });


        function disable(id) {
            $.ajax({
                url: "{{ route('package.updatestatus') }}",
                type: "POST",
                data: {
                    '_token': "{{ csrf_token() }}",
                    "id": id,
                    "status": 0,
                },
                cache: false,
                success: function(result) {

                    if (result.error == false) {
                        Toastify({
                            text: 'Package OFF successfully',
                            duration: 6000,
                            close: !0,
                            backgroundColor: "linear-gradient(to right, #00b09b, #96c93d)"
                        }).showToast();
                        $('#table_list').bootstrapTable('refresh');
                    } else {
                        Toastify({
                            text: result.message,
                            duration: 6000,
                            close: !0,
                            backgroundColor: "linear-gradient(to right, #00b09b, #96c93d)"
                        }).showToast();
                        $('#table_list').bootstrapTable('refresh');
                    }

                },
                error: function(error) {

                }
            });
        }

        function active(id) {
            $.ajax({
                url: "{{ route('package.updatestatus') }}",
                type: "POST",
                data: {
                    '_token': "{{ csrf_token() }}",
                    "id": id,
                    "status": 1,
                },
                cache: false,
                success: function(result) {

                    if (result.error == false) {
                        Toastify({
                            text: 'Package ON successfully',
                            duration: 6000,
                            close: !0,
                            backgroundColor: "linear-gradient(to right, #00b09b, #96c93d)"
                        }).showToast();
                        $('#table_list').bootstrapTable('refresh');
                    } else {
                        Toastify({
                            text: result.message,
                            duration: 6000,
                            close: !0,
                            backgroundColor: "linear-gradient(to right, #00b09b, #96c93d)"
                        }).showToast();
                        $('#table_list').bootstrapTable('refresh');
                    }

                },
                error: function(error) {

                }
            });
        }
        $(function() {
            $("#category").change(function() {
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
