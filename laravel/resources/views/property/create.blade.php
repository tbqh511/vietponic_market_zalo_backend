@extends('layouts.main')

@section('title')
    {{ __('Add Product') }}
@endsection
<!-- add before </body> -->

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
                            {{ __('Add') }}
                        </li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
@endsection
@section('content')
    {!! Form::open(['route' => 'property.store', 'data-parsley-validate', 'id' => 'myForm', 'files' => true]) !!}

    <div class='row'>
        <div class='col-md-6'>

            <div class="card">

                <h3 class="card-header"> {{ __('Details') }}</h3>
                <hr>
                <div class="card-body">
                    <div class="col-md-12 col-12 form-group mandatory">
                        {{ Form::label('category', __('Category'), ['class' => 'form-label col-12 ']) }}
                        <select name="category" class="select2 form-select form-control-sm" data-parsley-minSelect='1'
                            id="category" required>

                            @foreach ($category as $row)
                                <option value="{{ $row->id }}" data-parametertypes='{{ $row->parameter_types }}'>
                                    {{ $row->category }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-12 col-12 form-group mandatory">

                        {{ Form::label('title', __('Title'), ['class' => 'form-label col-12 ']) }}
                        {{ Form::text('title', '', ['class' => 'form-control ', 'placeholder' => 'Title', 'required' => 'true', 'id' => 'title']) }}

                    </div>
                    <div class="col-md-12 col-12  form-group  mandatory">
                        <div class="row">
                            {{ Form::label('', __('Property Type'), ['class' => 'form-label col-12 ']) }}

                            <div class="col-md-6">

                                {{ Form::radio('property_type', 0, null, ['class' => 'form-check-input', 'id' => 'property_type', 'required' => true]) }}
                                {{ Form::label('property_type', __('For Sell'), ['class' => 'form-check-label']) }}


                            </div>
                            <div class="col-md-6">
                                {{ Form::radio('property_type', 1, null, ['class' => 'form-check-input', 'id' => 'property_type', 'required' => true]) }}
                                {{ Form::label('property_type', __('For Rent'), ['class' => 'form-check-label']) }}

                            </div>
                        </div>
                    </div>


                    <div class="col-md-12 col-12 form-group mandatory" id='duration'>
                        {{ Form::label('Duration', __('Duration For Price'), ['class' => 'form-label col-12 ']) }}
                        <select name="price_duration" id="price_duration"class="select2 form-select form-control-sm"
                            data-parsley-minSelect='1'>

                            <option value="Daily">Daily</option>
                            <option value="Monthly">Monthly</option>
                            <option value="Yearly">Yearly</option>
                            <option value="Quarterly">Quarterly</option>
                        </select>
                    </div>
                    <div class="control-label col-12 form-group">
                        {{ Form::label('price', __('price') . '(' . $currency_symbol . ')', ['class' => 'form-label col-12 ']) }}
                        {{ Form::number('price', '', ['class' => 'form-control ', 'placeholder' => 'Price', 'required' => 'true', 'min' => '1', 'id' => 'price']) }}

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
                        {{ Form::label('description', __('Description'), ['class' => 'form-label col-12 ']) }}

                        {{ Form::textarea('description', '', ['class' => 'form-control mb-3', 'rows' => '10', 'id' => '', 'required' => 'true']) }}

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
                                {{ Form::checkbox($value->id, $value->name, false, ['class' => 'form-check-input', 'id' => 'chk' . $value->id]) }}

                                {{ Form::label('description', $value->name, ['class' => 'form-check-label']) }}

                                {{ Form::text('facility' . $value->id, '', ['class' => 'form-control mt-3', 'placeholder' => 'distance', 'id' => 'dist' . $value->id]) }}
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-12" id="facility">
            <div class="card">

                <h3 class="card-header"> {{ __('Facilities') }}</h3>
                <hr>
                {{ Form::hidden('category_count[]', $category, ['id' => 'category_count']) }}
                {{ Form::hidden('parameter_count[]', $parameters, ['id' => 'parameter_count']) }}
                {{ Form::hidden('facilities[]', $facility, ['id' => 'facilities']) }}

                {{ Form::hidden('parameter_add', '', ['id' => 'parameter_add']) }}
                <div id="parameter_type" class="row card-body"></div>

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
                                    {{ Form::text('country', '', ['class' => 'form-control ', 'placeholder' => 'Country', 'id' => 'country']) }}

                                </div>

                                <div class="col-md-6">
                                    {{ Form::label('state', __('State'), ['class' => 'form-label col-12 ']) }}
                                    {{ Form::text('state', '', ['class' => 'form-control ', 'placeholder' => 'State', 'id' => 'state']) }}

                                </div>
                                <div class="col-md-12 col-12 form-group">
                                    {{ Form::label('city', __('City'), ['class' => 'form-label col-12 ']) }}
                                    {{ Form::text('city', '', ['class' => 'form-control ', 'placeholder' => 'City', 'id' => 'city']) }}

                                </div>
                                <div class="col-md-6 form-group  mandatory">
                                    {{ Form::label('latitude', __('Latitude'), ['class' => 'form-label col-12 ']) }}
                                    {{ Form::number('latitude', '', ['class' => 'form-control ', 'placeholder' => 'Latitude', 'required', 'id' => 'latitude', 'step' => 'any']) }}

                                </div>
                                <div class="col-md-6 form-group  mandatory">
                                    {{ Form::label('longitude', __('Longitude'), ['class' => 'form-label col-12 ']) }}

                                    {{ Form::number('longitude', null, ['class' => 'form-control', 'placeholder' => 'Longitude', 'required' => true, 'id' => 'longitude', 'step' => 'any']) }}

                                </div>
                                <div class="col-md-12 col-12 form-group mandatory">
                                    {{ Form::label('address', 'Client Address', ['class' => 'form-label col-12 ']) }}
                                    {{ Form::textarea('client_address', '', ['class' => 'form-control ', 'placeholder' => 'Client Address', 'rows' => '4', 'id' => 'address', 'autocomplete' => 'off', 'required' => 'true']) }}
                                </div>
                                <div class="col-md-12 col-12 form-group mandatory">
                                    {{ Form::label('address', __('Address'), ['class' => 'form-label col-12 ']) }}
                                    {{ Form::textarea('address', '', ['class' => 'form-control ', 'placeholder' => 'Address', 'rows' => '4', 'id' => 'address', 'autocomplete' => 'off', 'required' => 'true']) }}
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

                                <style>

                                </style>
                                <div class="col-md-6 col-sm-12 card title_card">
                                    {{ Form::label('title_image', __('Title Image'), ['class' => 'form-label col-12 ']) }}

                                    <input type="file" class="filepond" id="filepond_title" name="title_image">
                                </div>

                                <div class="col-md-6 col-sm-12 card">
                                    {{ Form::label('title_image', __('3D Image'), ['class' => 'form-label col-12 ']) }}


                                    <input type="file" class="filepond" id="filepond2" name="3d_image">

                                </div>
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-12 ">
                            <div class="row card" style="margin-bottom:0;">
                                {{ Form::label('title_image', __('Gallary Images'), ['class' => 'form-label col-12 ']) }}

                                <input type="file" class="filepond" id="filepond2" name="gallery_images[]" multiple>
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
    <script type="text/javascript">
        $(document).ready(function() {
            $('#duration').hide();
            $('#price_duration').removeAttr('required');

            // Event handler for radio button change
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
        });

        jQuery(document).ready(function() {

            initMap();

            $('#map').append('<iframe src="https://maps.google.com/maps?q=' + 20.593684 + ',' + 78.96288 +
                '&hl=en&amp;z=18&amp;output=embed" height="375px" width="800px"></iframe>');
            $('#facility').hide();
            // $('.select2').prepend('<option value="" selected></option>');
        });

        $(document).ready(function() {
            $('.parsley-error filled,.parsley-required').attr("aria-hidden", "true");
            $('.parsley-error filled,.parsley-required').hide();

            // your code that uses .rules() function
        });
        $('property_type').on('Click', function() {
            console.log("click");
        });
        $('#unit_type').change(function() {
            $('.unit').empty();
            $('.unit').append('Unit Type (' + $('#unit_type :selected').text() + ')');

        });

        function validateForm(event) {
            event.preventDefault();

            let form = document.getElementById("myForm");
            let inputs = form.querySelectorAll("[required]");
            let isFormValid = true;
            inputs.forEach(function(input) {
                if (!input.value) {
                    isFormValid = false;
                    $('.parsley-error filled').hide();
                    $('.parsley-required').hide();
                    $('.parsley-required').attr('aria-hidden', 'false');

                    let errorMessage = document.createElement("div");
                    errorMessage.classList.add("error-message");
                    errorMessage.innerText = (input.placeholder != '' || input
                            .placeholder) ? input.placeholder + ' is required' :
                        'This Field is required.';
                    input.parentNode.insertBefore(errorMessage, input.nextSibling);
                }
            });

            if (isFormValid) {
                form.submit();
            }
        }

        let myForm = document.getElementById("myForm");
        myForm.addEventListener("submit",
            validateForm);

        $('.btn_gallary').click(function() {
            $('#gallary_image').click();


        });

        function initMap() {
            var map = new google.maps.Map(document.getElementById('map'), {
                center: {
                    lat: -33.8688,
                    lng: 151.2195
                },
                zoom: 13
            });
            var input = document.getElementById('searchInput');
            map.controls[google.maps.ControlPosition.TOP_LEFT].push(input);

            var autocomplete = new google.maps.places.Autocomplete(input);
            autocomplete.bindTo('bounds', map);

            var infowindow = new google.maps.InfoWindow();
            var marker = new google.maps.Marker({
                draggable: true,

                position: {
                    lat: -33.8688,
                    lng: 151.2195
                },
                map: map,
                anchorPoint: new google.maps.Point(0, -29)
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
            autocomplete.addListener('place_changed', function() {
                infowindow.close();
                marker.setVisible(false);
                var place = autocomplete.getPlace();
                if (!place.geometry) {
                    window.alert("Autocomplete's returned place contains no geometry");
                    return;
                }


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
        jQuery(document).ready(function() {

            initMap();
            $('#map').append('<iframe src="https://maps.google.com/maps?q=' + 20.593684 + ',' + 78.96288 +
                '&hl=en&amp;z=18&amp;output=embed" height="375px" width="800px"></iframe>');
            $('#facility').hide();
            $('.select2').prepend('<option value="" selected></option>');
            $facility = $.parseJSON($('#facilities').val());

            $.each($facility, function(key, value) {
                console.log(value);
                $('#dist' + value.id).hide();
                $('#chk' + value.id).on('click', function() {

                    if ($('#chk' + value.id).is(':checked')) {
                        $('#dist' + value.id).show();

                    } else {
                        $('#dist' + value.id).hide();

                    }
                });
            });
        });


        $('#gallary_image').on('change', function() {
            // Get the selected files
            var files = $(this)[0].files;
            // Loop through each selected file
            for (var i = 0; i < files.length; i++) {
                // Create a new FileReader instance
                var reader = new FileReader();
                // Set the onload function to generate a preview
                reader.onload = function(e) {
                    $('#preview_3d_img').append(
                        '<div class="col-md-3 position-relative mt-3"><img id="blah_gallary" class="box-img" src=' +
                        e.target
                        .result +
                        ' style="object-fit:fill;"/><button class="remove-btn position-absolute ">X</button></div>'
                    );

                }
                // Read the selected file as a data URL
                reader.readAsDataURL(files[i]);
            }
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
