<script src="{{ url('assets/js/bootstrap.js') }}"></script>
{{-- <script src="{{ url('assets/extensions/jquery/jquery.min.js') }}"></script> --}}

{{-- <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.1.1/jquery.min.js"></script>
<script src="{{ url('assets/js/custom/firebase_config.js') }}"></script>
<script src="https://www.gstatic.com/firebasejs/8.10.0/firebase-app.js"></script>
<script src="https://www.gstatic.com/firebasejs/8.10.0/firebase-messaging.js"></script> --}}

<script src="https://www.gstatic.com/firebasejs/8.10.0/firebase-app.js"></script>
<script src="https://www.gstatic.com/firebasejs/8.10.0/firebase-messaging.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.1.1/jquery.min.js"></script>
<script type="text/javascript" src="{{ url('/assets/js/filepond/filepond.js') }}"></script>

<script type="text/javascript" src="{{ url('/assets/js/filepond/filepond.jquery.js') }}"></script>



<script src="{{ url('assets/js/custom/firebase_config.js') }}"></script>


<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>

<script src="{{ url('assets/extensions/sweetalert2/sweetalert2.min.js') }}"></script>
<script src="https://bevacqua.github.io/dragula/dist/dragula.js"></script>



<script src="{{ url('assets/js/app.js') }}"></script>
<script src="{{ url('assets/extensions/tinymce/tinymce.min.js') }}"></script>
<script src="{{ url('assets/js/custom/custom.js') }}"></script>
<script src="{{ url('assets/js/jquery-jvectormap-2.0.5.min.js') }}"></script>
<script src="{{ url('assets/js/jquery-jvectormap-asia-merc.js') }}"></script>
<script src="{{ url('assets/js/query-jvectormap-world-mill-en.js') }}"></script>
<script src="{{ url('assets/js/jquery-jvectormap-world-mill.js') }}"></script>









<script src="{{ url('assets/extensions/toastify-js/src/toastify.js') }}"></script>
<script src="{{ url('assets/extensions/parsleyjs/parsley.min.js') }}"></script>
<script src="{{ url('assets/js/pages/parsley.js') }}"></script>
<script src="{{ url('assets/extensions/bootstrap-table/bootstrap-table.min.js') }}"></script>
<script src="{{ url('assets/extensions/bootstrap-table/fixed-columns/bootstrap-table-fixed-columns.min.js') }}">
</script>
<script src="{{ url('assets/extensions/bootstrap-table/mobile/bootstrap-table-mobile.min.js') }}"></script>

<script src="{{ url('assets/extensions/magnific-popup/jquery.magnific-popup.min.js') }}"></script>
<script src="{{ url('assets/extensions/select2/dist/js/select2.min.js') }}"></script>

<script src="{{ url('assets/extensions/jquery-ui/jquery-ui.js') }}"></script>


<script src="{{ url('assets/extensions/clipboardjs/dist/clipboard.min.js') }}"></script>


{{-- <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/2.2.2/jquery.min.js"></script> --}}
<script src="https://harvesthq.github.io/chosen/chosen.jquery.js"></script>


<script src="{{ asset('assets/js/filepond/filepond.min.js') }}"></script>
<script src="{{ asset('assets/js/filepond/filepond-plugin-image-preview.min.js') }}"></script>
<script src="{{ asset('assets/js/filepond/filepond-plugin-pdf-preview.min.js') }}"></script>
<script src="{{ asset('assets/js/filepond/filepond-plugin-file-validate-size.js') }}"></script>
<script src="{{ asset('assets/js/filepond/filepond-plugin-file-validate-type.js') }}"></script>
<script src="{{ asset('assets/js/filepond/filepond-plugin-image-validate-size.js') }}"></script>
<script src="{{ asset('assets/js/filepond/filepond.jquery.js') }}"></script>




@if (Session::has('success'))
    <script type="text/javascript">
        Toastify({
            text: '{{ Session::get('success') }}',
            duration: 6000,
            close: !0,
            backgroundColor: "linear-gradient(to right, #00b09b, #96c93d)"
        }).showToast()
    </script>
@endif


@if (Session::has('error'))
    <script type="text/javascript">
        Toastify({
            text: '{{ Session::get('error') }}',
            duration: 6000,
            close: !0,
            backgroundColor: '#dc3545' //"linear-gradient(to right, #dc3545, #96c93d)"
        }).showToast()
    </script>
@endif
