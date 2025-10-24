@extends('layouts.main')
@section('title')
    {{ __('Update Product') }}
@endsection
<script src="https://unpkg.com/filepond/dist/filepond.js"></script>
@section('page-title')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h4>@yield('title')</h4>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
                <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="{{ route('property.index') }}" id="subURL">{{ __('View Property') }}</a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">
                            {{ __('Update') }}
                        </li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
@endsection
@section('content')
    {!! Form::open([
        'route' => ['property.update', $id],
        'method' => 'PATCH',
        'data-parsley-validate',
        'files' => true,
        'id' => 'myForm',
    ]) !!}

    <div class='row'>
        <div class='col-md-6'>

            <div class="card">

                <h3 class="card-header">{{ __('Details') }}</h3>
                <hr>
                <div class="card-body">
                    <div class="col-md-12 col-12 form-group">
                        {{ Form::label('category', __('Category'), ['class' => 'form-label col-12 ']) }}
                        <select name="category" class="select2 form-select form-control-sm" data-parsley-minSelect='1'
                            id="category" required='true'>

                            @foreach ($category as $row)
                                <option value="{{ $row->id }}"
                                    {{ $list->category_id == $row->id ? ' selected=selected' : '' }}
                                    data-parametertypes='{{ $row->parameter_types }}'> {{ $row->category }}
                                </option>
                            @endforeach
                            <option value=""></option>

                        </select>
                    </div>
                    <div class="col-md-12 col-12 form-group mandatory">

                        {{ Form::label('title', __('Title'), ['class' => 'form-label col-12 ']) }}
                        {{ Form::text('title', isset($list->title) ? $list->title : '', ['class' => 'form-control ', 'placeholder' => 'Title', 'required' => 'true', 'id' => 'title']) }}

                    </div>

                    <div class="col-md-12 col-12  form-group  mandatory">
                        <div class="row">
                            {{ Form::label('', __('Property Type'), ['class' => 'form-label col-12 ']) }}

                            <div class="col-md-6">
                                {{ Form::radio('property_type', 0, null, ['class' => 'form-check-input', 'id' => 'property_type', 'required' => true, isset($list->propery_type) && $list->propery_type == 0 ? 'checked' : '']) }}
                                {{ Form::label('property_type', __('For Sell'), ['class' => 'form-check-label']) }}
                            </div>
                            <div class="col-md-6">
                                {{ Form::radio('property_type', 1, null, ['class' => 'form-check-input', 'id' => 'property_type', 'required' => true, isset($list->propery_type) && $list->propery_type == 1 ? 'checked' : '']) }}
                                {{ Form::label('property_type', __('For Rent'), ['class' => 'form-check-label']) }}
                            </div>
                        </div>
                    </div>
                    <div class="col-md-12 col-12 form-group mandatory" id='duration'>
                        {{ Form::label('Duration', __('Duration For Price'), ['class' => 'form-label col-12 ']) }}
                        <select name="price_duration" id="price_duration"class="select2 form-select form-control-sm"
                            data-parsley-minSelect='1'>

                            <option value="Daily" {{ $list->rentduration == 'Daily' ? 'selected' : '' }}>Daily
                            </option>
                            <option value="Monthly" {{ $list->rentduration == 'Monthly' ? 'selected' : '' }}>Monthly
                            </option>
                            <option value="Yearly" {{ $list->rentduration == 'Yearly' ? 'selected' : '' }}>Yearly</option>
                            <option value="Quarterly" {{ $list->rentduration == 'Quarterly' ? 'selected' : '' }}>Quarterly
                            </option>
                        </select>
                    </div>
                    <div class="control-label col-12 form-group">
                        {{ Form::label('price', __('price') . '(' . $currency_symbol . ')', ['class' => 'form-label col-12 ']) }}
                        {{ Form::number('price', isset($list->price) ? $list->price : '', ['class' => 'form-control ', 'placeholder' => 'Price', 'required' => 'true', 'min' => '1', 'id' => 'price']) }}
                    </div>
                </div>
            </div>
        </div>
        <div class='col-md-6'>

            <div class="card">
                <h3 class="card-header">{{ __('Description') }}</h3>
                <hr>
                <div class="row card-body">
                    <div class="col-md-12 col-12 form-group mandatory">
                        {{ Form::label('description', 'Description', ['class' => 'form-label col-12 ']) }}

                        {{ Form::textarea('description', isset($list->description) ? $list->description : '', ['class' => 'form-control mb-3', 'rows' => '10', 'id' => '', 'required' => 'true']) }}

                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-12" id="outdoor_facility">
            <div class="card">
                <h3 class="card-header">{{ __('near_by_places') }}</h3>
                <hr>
                <div class="card-body">
                    <div class="row">

                        @foreach ($facility as $key => $value)
                            <div class='col-md-3  form-group'>
                                {{ Form::label('description', $value->name, ['class' => 'form-check-label']) }}
                                @if (count($value->assign_facilities))
                                    {{ Form::text('facility' . $value->id, $value->assign_facilities[0]['distance'], ['class' => 'form-control mt-3', 'placeholder' => 'distance', 'id' => 'dist' . $value->id]) }}
                                @else
                                    {{ Form::text('facility' . $value->id, '', ['class' => 'form-control mt-3', 'placeholder' => 'distance', 'id' => 'dist' . $value->id]) }}
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-12" id="facility">

            <div class="card">

                <h3 class="card-header">{{ __('Facilities') }}</h3>
                <hr>
                {{ Form::hidden('category_count[]', $category, ['id' => 'category_count']) }}
                {{ Form::hidden('parameter_count[]', $parameters, ['id' => 'parameter_count']) }}
                {{ Form::hidden('parameter_add', '', ['id' => 'parameter_add']) }}
                <div id="parameter_type" name=parameter_type class="row card-body">
                    @foreach ($edit_parameters as $res)
                        {{-- {{ print_r($res->assigned_parameter) }} --}}

                        {{-- @foreach ($par_arr as $key => $arr) --}}
                        <div class="col-md-3 form-group">


                            {{ Form::label($res->name, $res->name, ['class' => 'form-label col-12']) }}

                            @if ($res->type_of_parameter == 'dropdown')
                                <select name="{{ $res->id }}" class="select2 form-select form-control-sm"
                                    selected=false false name={{ $res->id }}>


                                    @foreach ($res->type_values as $key => $value)
                                        <option value="{{ $value }}"
                                            {{ $res->assigned_parameter && $res->assigned_parameter->value == $value ? ' selected=selected' : '' }}>
                                            {{ $value }} </option>
                                    @endforeach
                                </select>
                            @endif
                            @if ($res->type_of_parameter == 'radiobutton')
                                @foreach ($res->type_values as $key => $value)
                                    <input type="radio" name="{{ $res->id }}" id=""
                                        value={{ $value }} class="form-check-input"
                                        {{ $res->assigned_parameter && ($res->assigned_parameter->value == $value) == $value ? 'checked' : '' }}>
                                    {{ $value }}
                                @endforeach
                            @endif
                            @if ($res->type_of_parameter == 'number')
                                <input type="number" name="{{ $res->id }}" id="" class="form-control"
                                    value="{{ $res->assigned_parameter ? $res->assigned_parameter->value : '' }}">
                            @endif
                            @if ($res->type_of_parameter == 'textbox')
                                <input type="text" name="{{ $res->id }}" id="" class="form-control"
                                    value="{{ $res->assigned_parameter && $res->assigned_parameter->value != 'null' ? $res->assigned_parameter->value : '' }}">
                            @endif
                            @if ($res->type_of_parameter == 'textarea')
                                <textarea name="{{ $res->id }}" id="" cols="10" rows="10"
                                    value="{{ $res->assigned_parameter && $res->assigned_parameter->value != 'null' ? $res->assigned_parameter->value : '' }}"></textarea>
                            @endif
                            @if ($res->type_of_parameter == 'checkbox')
                                @foreach ($res->type_values as $key => $value)
                                    <input type="checkbox" name="{{ $res->id . '[]' }}" id=""
                                        class="form-check-input" value={{ $value }}
                                        {{-- HuyTBQ --}}
                                        {{-- {{ !empty($res->assigned_parameter->value) && in_array($value, $res->assigned_parameter->value) ? 'Checked' : '' }}>{{ $value }} --}}
                                        {{ !empty($res->assigned_parameter->value) && is_array($res->assigned_parameter->value) && in_array($value, $res->assigned_parameter->value) ? 'Checked' : '' }}

                                @endforeach
                            @endif

                            @if ($res->type_of_parameter == 'file')
                                @if (!empty($res->assigned_parameter->value))
                                    <a href="{{ url('') . config('global.IMG_PATH') . config('global.PARAMETER_IMG_PATH') . '/' . $res->assigned_parameter->value }}"
                                        class="text-center col-12" style="text-align: center"> Click
                                        here to View</a> OR
                                @endif
                                <input type="hidden" name="{{ $res->id }}"
                                    value="{{ $res->assigned_parameter ? $res->assigned_parameter->value : '' }}">
                                <input type="file" class='form-control' name="{{ $res->id }}" id='edit_param_img'>
                            @endif
                        </div>
                        {{-- @endforeach --}}
                    @endforeach
                </div>
            </div>
        </div>
        <div class='col-md-12'>

            <div class="card">
                <h3 class="card-header">{{ __('Location') }}</h3>
                <hr>
                <div class="card-body">
                    <div class="row">
                        <div class='col-md-6'>
                            <div class="card col-md-12">
                                <input id="searchInput" class="controls" type="text" placeholder="Enter a location"
                                    style="position: absolute;left: 188px;width: 64%;height: 8%;margin-top:9px">
                            </div>
                            <div class="card col-md-12" id="map" style="height: 90%">

                                <!-- Google map -->

                            </div>
                        </div>
                        <div class='col-md-6'>
                            <div class="row">
                                <div class="col-md-6">
                                    {{ Form::label('country', __('Country'), ['class' => 'form-label col-12 ']) }}
                                    {{ Form::text('country', isset($list->country) ? $list->country : '', ['class' => 'form-control ', 'placeholder' => 'country', 'id' => 'country']) }}
                                </div>
                                <div class="col-md-6">
                                    {{ Form::label('state', __('State'), ['class' => 'form-label col-12 ']) }}
                                    {{ Form::text('state', isset($list->state) ? $list->state : '', ['class' => 'form-control ', 'placeholder' => 'State', 'id' => 'state']) }}
                                </div>
                                <div class="col-md-12 col-12 form-group">
                                    {{ Form::label('city', __('City'), ['class' => 'form-label col-12 ']) }}
                                    {{ Form::text('city', isset($list->city) ? $list->city : '', ['class' => 'form-control ', 'placeholder' => 'City', 'id' => 'city']) }}
                                </div>
                                <div class="col-md-6 form-group  mandatory">
                                    {{ Form::label('latitude', __('Latitude'), ['class' => 'form-label col-12 ']) }}
                                    {{ Form::number('latitude', isset($list->latitude) ? $list->latitude : '', ['class' => 'form-control ', 'placeholder' => 'Latitude', 'required', 'id' => 'latitude', 'step' => 'any']) }}
                                </div>
                                <div class="col-md-6 form-group  mandatory">
                                    {{ Form::label('longitude', __('Longitude'), ['class' => 'form-label col-12 ']) }}
                                    {{ Form::number('longitude', isset($list->longitude) ? $list->longitude : '', ['class' => 'form-control', 'placeholder' => 'Longitude', 'required' => true, 'id' => 'longitude', 'step' => 'any']) }}
                                </div>
                                <div class="col-md-12 col-12 form-group mandatory">
                                    {{ Form::label('address', __('Client Address'), ['class' => 'form-label col-12 ']) }}
                                    {{ Form::textarea('client_address', isset($list->client_address) ? $list->client_address : '', ['class' => 'form-control ', 'placeholder' => 'Client Address', 'rows' => '4', 'id' => 'address', 'autocomplete' => 'off', 'required' => 'true']) }}
                                </div>
                                <div class="col-md-12 col-12 form-group mandatory">
                                    {{ Form::label('address', __('Address'), ['class' => 'form-label col-12 ']) }}
                                    {{ Form::textarea('address', isset($list->address) ? $list->address : '', ['class' => 'form-control ', 'placeholder' => 'Address', 'rows' => '4', 'id' => 'address', 'autocomplete' => 'off', 'required' => 'true']) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-12">
            <div class="card">
                <h3 class="card-header">{{ __('Images') }}</h3>
                <hr>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 col-sm-12">
                            <div class="row">
                                <div class="col-md-6 col-sm-12 card title_card">
                                    {{ Form::label('title_image', __('Title Image'), ['class' => 'form-label col-12 ']) }}
                                    <input type="file" class="filepond" id="filepond_title" name="title_image">
                                    @if ($list->title_image)
                                        <div class="card1 title_img">
                                            <img src="{{ $list->title_image }}" alt="Image" class="card1-img">

                                        </div>
                                    @endif
                                </div>
                                <div class="col-md-6 col-sm-12 card">
                                    {{ Form::label('title_image', __('3D Image'), ['class' => 'form-label col-12 ']) }}
                                    <input type="file" class="filepond" id="filepond_3d" name="3d_image">
                                    @if ($list->threeD_image)
                                        <div class="card1 3d_img">
                                            <img src="{{ $list->threeD_image }}" alt="Image" class="card1-img"
                                                id="3d_img">
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-12 ">
                            <div class="row card" style="margin-bottom:0;">
                                {{ Form::label('title_image', __('Gallary Images'), ['class' => 'form-label col-12 ']) }}

                                <input type="file" class="filepond" id="filepond2" name="gallery_images[]" multiple>
                            </div>
                            <div class="row mt-0">
                                <?php $i = 0; ?>
                                @if (!empty($list->gallery))
                                    @foreach ($list->gallery as $row)
                                        <div class="col-md-6 col-sm-12" id='{{ $row->id }}'>
                                            <div class="card1" style="height:90%;">

                                                <img src="{{ url('') . config('global.IMG_PATH') . config('global.PROPERTY_GALLERY_IMG_PATH') . $list->id . '/' . $row->image }}"
                                                    alt="Image" class="card1-img">
                                                <button data-rowid="{{ $row->id }}"
                                                    class="RemoveBtn1 RemoveBtngallary">x</button>
                                            </div>
                                        </div>

                                        <?php $i++; ?>
                                    @endforeach
                                @endif
                            </div>
                        </div>
                        <div class="col-md-3">
                            {{ Form::label('video_link', __('Video Link'), ['class' => 'form-label col-12 ']) }}
                            {{ Form::text('video_link', isset($list->video_link) ? $list->video_link : '', ['class' => 'form-control ', 'placeholder' => 'Video Link', 'id' => 'address', 'autocomplete' => 'off']) }}

                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class='col-md-12 d-flex justify-content-end mb-3'>
            <input type="submit" class="btn btn-primary" value="save">
            &nbsp;
            &nbsp;

            <button class="btn btn-secondary" type="button" onclick="formname.reset();">{{ __('Reset') }}</button>
        </div>
        {!! Form::close() !!}

    </div>
@endsection
@section('script')
    <script type="text/javascript"
        src="https://maps.googleapis.com/maps/api/js?libraries=places&key={{ env('PLACE_API_KEY') }}&callback=initMap"
        async defer></script>

    <script>
        function initMap() {
            var latitude = parseFloat($('#latitude').val());
            var longitude = parseFloat($('#longitude').val());
            var map = new google.maps.Map(document.getElementById('map'), {

                center: {
                    lat: latitude,
                    lng: longitude
                },


                zoom: 13
            });
            var marker = new google.maps.Marker({
                position: {
                    lat: latitude,
                    lng: longitude
                },
                map: map,
                draggable: true,
                title: 'Marker Title'
            });
            google.maps.event.addListener(marker, 'dragend', function(event) {
                var geocoder = new google.maps.Geocoder();
                geocoder.geocode({
                    'latLng': event.latLng
                }, function(results, status) {
                    if (status == google.maps.GeocoderStatus.OK) {
                        if (results[0]) {
                            var address_components = results[0].address_components;
                            var city, state, country, full_address;

                            for (var i = 0; i < address_components.length; i++) {
                                var types = address_components[i].types;
                                if (types.indexOf('locality') != -1) {
                                    city = address_components[i].long_name;
                                } else if (types.indexOf('administrative_area_level_1') != -1) {
                                    state = address_components[i].long_name;
                                } else if (types.indexOf('country') != -1) {
                                    country = address_components[i].long_name;
                                }
                            }

                            full_address = results[0].formatted_address;

                            // Do something with the city, state, country, and full address
                            $('#city').val(city);
                            $('#country').val(state);
                            $('#state').val(country);
                            $('#address').val(full_address);
                            $('#latitude').val(event.latLng.lat());
                            $('#longitude').val(event.latLng.lng());

                        } else {
                            console.log('No results found');
                        }
                    } else {
                        console.log('Geocoder failed due to: ' + status);
                    }
                });
            });
            var input = document.getElementById('searchInput');
            map.controls[google.maps.ControlPosition.TOP_LEFT].push(input);

            var autocomplete = new google.maps.places.Autocomplete(input);
            autocomplete.bindTo('bounds', map);

            var infowindow = new google.maps.InfoWindow();
            var marker = new google.maps.Marker({
                map: map,
                anchorPoint: new google.maps.Point(0, -29)
            });
            autocomplete.addListener('place_changed', function() {
                infowindow.close();
                marker.setVisible(false);
                var place = autocomplete.getPlace();
                if (!place.geometry) {
                    window.alert("Autocomplete's returned place contains no geometry");
                    return;
                }

                // If the place has a geometry, then present it on a map.
                if (place.geometry.viewport) {
                    map.fitBounds(place.geometry.viewport);
                } else {
                    map.setCenter(place.geometry.location);
                    map.setZoom(17);
                }
                marker.setIcon(({
                    url: place.icon,
                    size: new google.maps.Size(71, 71),
                    origin: new google.maps.Point(0, 0),
                    anchor: new google.maps.Point(17, 34),
                    scaledSize: new google.maps.Size(35, 35)
                }));
                marker.setPosition(place.geometry.location);
                marker.setVisible(true);

                var address = '';
                if (place.address_components) {
                    address = [
                        (place.address_components[0] && place.address_components[0].short_name || ''),
                        (place.address_components[1] && place.address_components[1].short_name || ''),
                        (place.address_components[2] && place.address_components[2].short_name || '')
                    ].join(' ');
                }

                infowindow.setContent('<div><strong>' + place.name + '</strong><br>' + address);
                infowindow.open(map, marker);

                // Location details
                for (var i = 0; i < place.address_components.length; i++) {
                    console.log(place);

                    if (place.address_components[i].types[0] == 'locality') {
                        $('#city').val(place.address_components[i].long_name);


                    }
                    if (place.address_components[i].types[0] == 'country') {
                        $('#country').val(place.address_components[i].long_name);


                    }
                    if (place.address_components[i].types[0] == 'administrative_area_level_1') {
                        console.log(place.address_components[i].long_name);
                        $('#state').val(place.address_components[i].long_name);


                    }
                }
                var latitude = place.geometry.location.lat();
                var longitude = place.geometry.location.lng();
                $('#address').val(place.formatted_address);
                $('#latitude').val(place.geometry.location.lat());
                $('#longitude').val(place.geometry.location.lng());
            });
        }

        $(document).ready(function() {
            console.log($('input[name="property_type"]').val());
            if ($('input[name="property_type"]:checked').val() == 0) {
                $('#duration').hide();
                $('#price_duration').removeAttr('required');
            } else {
                $('#duration').show();

            }

        });
        $('input[name="property_type"]').change(function() {
            // Get the selected value
            var selectedType = $('input[name="property_type"]:checked').val();

            // Perform actions based on the selected value

            if (selectedType == 1) {
                $('#duration').show();
                $('#price_duration').attr('required', 'true');
            } else {
                $('#duration').hide();
                $('#price_duration').removeAttr('required');
            }
        });
        $(".RemoveBtngallary").click(function(e) {
            e.preventDefault();
            var id = $(this).data('rowid');
            Swal.fire({
                title: 'Are You Sure Want to Remove This Image',
                icon: 'error',
                showDenyButton: true,

                confirmButtonText: 'Yes',
                denyCanceButtonText: `No`,
            }).then((result) => {
                /* Read more about isConfirmed, isDenied below */
                if (result.isConfirmed) {
                    $.ajax({
                        url: "{{ route('property.removeGalleryImage') }}",

                        type: "POST",
                        data: {
                            '_token': "{{ csrf_token() }}",
                            "id": id
                        },
                        success: function(response) {

                            if (response.error == false) {
                                Toastify({
                                    text: 'Image Delete Successful',
                                    duration: 6000,
                                    close: !0,
                                    backgroundColor: "linear-gradient(to right, #00b09b, #96c93d)"
                                }).showToast();
                                $("#" + id).html('');
                            } else if (response.error == true) {
                                Toastify({
                                    text: 'Something Wrong !!!',
                                    duration: 6000,
                                    close: !0,
                                    backgroundColor: '#dc3545' //"linear-gradient(to right, #dc3545, #96c93d)"
                                }).showToast()
                            }
                        },
                        error: function(xhr) {}
                    });
                }
            })

        });
        $(document).on('click', '#filepond_3d', function(e) {

            $('.3d_img').hide();
        });
        $(document).on('click', '#filepond_title', function(e) {

            $('.title_img').hide();
        });
        jQuery(document).ready(function() {
            initMap();

            $('#map').append('<iframe src="https://maps.google.com/maps?q=' + $('#latitude').val() + ',' + $(
                    '#longitude').val() +
                '&hl=en&amp;z=18&amp;output=embed" height="375px" width="800px"></iframe>');
        });
        $(document).ready(function() {
            $('.parsley-error filled,.parsley-required').attr("aria-hidden", "true");
            $('.parsley-error filled,.parsley-required').hide();

        });
    </script>
    <style>
        .error-message {
            color: red;
            margin-top: 5px;
            font-size: 15px;
        }
    </style>
@endsection
