@extends('layouts.main')

@section('title')
    {{ __('Payment') }}
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



            <div class="card-body">


                <div class="row">
                    <div class="col-12">
                        <table class="table-light" aria-describedby="mydesc" class='table-striped' id="table_list"
                            data-toggle="table" data-url="{{ url('getPaymentList') }}" data-click-to-select="true"
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
                                    <th scope="col" data-field="customer_name" data-align="center" data-sortable="false">
                                        {{ __('Client Name') }}</th>
                                    <th scope="col" data-field="package_name" data-align="center" data-sortable="false">
                                        {{ __('Package Name') }}
                                    </th>
                                    <th scope="col" data-field="amount" data-align="center" data-sortable="false">
                                        {{ __('Amount') }}
                                    </th>
                                    <th scope="col" data-field="transaction_id" data-align="center"
                                        data-sortable="false">{{ __('Transaction Id') }}
                                    </th>


                                    <th scope="col" data-field="payment_date" data-align="center" data-sortable="false">
                                        {{ __('Payment Date') }}
                                    <th scope="col" data-field="payment_gateway" data-align="center"
                                        data-sortable="false">{{ __('Payment Gateway') }}
                                    <th scope="col" data-field="status" data-align="center" data-sortable="false">
                                        {{ __('Status') }}</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>


        </div>
        <input type="hidden" id="customerid" value="{{ isset($_GET['customer']) ? $_GET['customer'] : '' }}">
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
                status: $('#status').val(),
                category: $('#category').val(),
                customer_id: $('#customerid').val(),
            };
        }
    </script>
@endsection
