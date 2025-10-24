@extends('layouts.main')

@section('title')
    {{ __('Edit Article') }}
@endsection

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
                            <a href="{{ route('article.index') }}" id="subURL">{{ __('View Article') }}</a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">
                            {{ __('Edit') }}
                        </li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
@endsection


@section('content')
    <section class="section">
        <div class="card">
            {!! Form::open([
                'route' => ['article.update', $id],
                'method' => 'PATCH',
                'data-parsley-validate',
                'files' => true,
            ]) !!}
            <div class="card-body">

                <div class="row ">

                    <div class="col-md-6 col-sm-12 form-group mandatory">

                        {{ Form::label('title', __('Title'), ['class' => 'form-label col-12 ']) }}
                        {{ Form::text('title', $list->title, ['class' => 'form-control ', 'placeholder' => __('Title'), 'data-parsley-required' => 'true', 'id' => 'title']) }}

                    </div>
                    <div class="col-md-6 col-sm-12 form-group">


                        {{ Form::label('image', __('Image'), ['class' => 'col-12 form-label']) }}
                        <input type="button" class="input-btn1-ghost-dashed bottomleft  h-100" value="+">
                        <input accept="image/*" name='image' type='file' id="edit_image" style="display: none" />

                        <img id="blah" height="100%" width="25%" src="{{ $list->image }}"
                            style="margin-left: 2%" />



                    </div>

                </div>
                <div class="row  mt-4">

                    <div class="row mt-4">
                        <div class="col-md-12 col-12 form-group mandatory">
                            {{ Form::label('description', __('Description'), ['class' => 'form-label col-12 ']) }}


                            {{ Form::textarea('description', $list->description, ['class' => 'form-control ', 'rows' => '2', 'id' => 'tinymce_editor', 'data-parsley-required' => 'true']) }}

                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="col-12 d-flex justify-content-end">

                        {{ Form::submit(__('Save'), ['class' => 'btn btn-primary me-1 mb-1']) }}
                    </div>

                </div>
                {!! Form::close() !!}
            </div>
    </section>
@endsection

@section('script')
    <script>
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
