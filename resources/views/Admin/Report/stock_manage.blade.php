@extends('Admin.theme')
<?php

$from_date = isset($_GET['from_date']) && $_GET['from_date'] != '' ? $_GET['from_date'] : date('Y-m-d');
$to_date = isset($_GET['to_date']) && $_GET['to_date'] != '' ? $_GET['to_date'] : date('Y-m-d');
$session_branch = getSessionBranch() ? ' - ' . getBranchById(getSessionBranch()) : '';
$branch_name = auth()->user()->branch_id ? ' - ' . auth()->user()->branch->branch_name : $session_branch;
$title = 'STOCK MOVING REPORT' . $branch_name . ' - ' . $from_date . ' - ' . $to_date;
?>
@section('title', $title)

@section('style')

@endsection

@section('content')


    <?php

    $item_id = isset($_GET['item_id']) && $_GET['item_id'] != '' ? $_GET['item_id'] : '';
    $action_type = isset($_GET['action_type']) && $_GET['action_type'] != '' ? $_GET['action_type'] : '';

    ?>

    <div class="az-content az-content-dashboard  animate__animated animate__fadeIn">
        <div class="container-fluid">
            <div class="az-content-body">
                <div class="col-12">
                    <div class="az-dashboard-one-title">
                        <div>
                            <h2 class="az-dashboard-title">Stock Moving Report</h2>
                            <p class="az-dashboard-text"></p>
                        </div>
                        <div class="az-content-header-right">
                            <nav class="nav">
                                <a id="createbtn"
                                    class="nav-linkk btn btn-dark rounded-10 shadoww mr-2 mb-2 d-flex justify-content-center align-items-center"
                                    href="{{ url('admin/available-stock') }}"> Available Stock</a>
                            </nav>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="card rounded-10 shadow">
                        <div class="card-header">
                            <form>
                                <div class="row d-flex flex-wrap">
                                    <div class="w-auto ml-3">
                                        <label class="mb-0 d-block small font-weight-bold">From Date</label>
                                        <input type="date" value="{{ $from_date }}" name="from_date"
                                            class="form-control rounded-10" required onchange="this.form.submit()">
                                    </div>
                                    <div class="w-auto ml-3">
                                        <label class="mb-0 d-block small font-weight-bold">To Date</label>
                                        <input type="date" value="{{ $to_date }}" name="to_date"
                                            class="form-control rounded-10" required onchange="this.form.submit()">
                                    </div>
                                    <div class="w-auto ml-3">
                                        <label class="mb-0 d-block small font-weight-bold">Item</label>
                                        <select class="form-control rounded-10 select2" id="item_id" name="item_id" onchange="this.form.submit()">
    <option value="">Select Item</option>
    @foreach ($items as $item)
        <option value="{{ $item->price_id }}" @if ($item->price_id == $item_id) selected="selected" @endif>
            {{ Str::ucfirst($item->item_name) }}
            {{ $item->size_name === 'Unit price' ? '' : ' - ' . $item->size_name }}
        </option>
    @endforeach
</select>

                                    </div>
                                    <div class="w-auto ml-3">
                                        <label class="mb-0 d-block small font-weight-bold">Action Type</label>
                                        <select class="form-control rounded-10 select2" id="action_type" name="action_type"
                                            onchange="this.form.submit()">
                                            <option value="">Select Action Type</option>
                                            <option value="add"
                                                @if ('add' == $action_type) selected="selected" @endif>
                                                Add
                                            </option>
                                            <option value="sub"
                                                @if ('sub' == $action_type) selected="selected" @endif>
                                                Sub
                                            </option>
                                        </select>
                                    </div>
                                    <div class="w-auto ml-3">
                                        <label class="mb-0 d-block small font-weight-bold">&nbsp;</label>
                                        <button type="submit" class="btn btn-dark rounded-10 px-3">
                                            Submit
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-12 mt-4">
                    <div class="row">
                        <div class="col-12">
                            <div class="card rounded-10 shadow">
                                <div class="card-body overflow-auto">
                                    {{-- <div class="dt-buttons">
                                        <a href="{{ url('admin/stock-excel') }}?from_date={{ $from_date }}&to_date={{ $to_date }}&item_id={{ $item_id }}&action_type={{ $action_type }}"
                                            class="dt-button buttons-excel buttons-html5 btn btn-dark px-3 rounded-10"
                                            tabindex="0" aria-controls="example" type="button"><span><i
                                                    class="fa fa-file-excel-o" style="font-size:1.2rem"></i></span>
                                        </a>
                                        <a href="{{ url('admin/stock-print') }}?from_date={{ $from_date }}&to_date={{ $to_date }}&item_id={{ $item_id }}&action_type={{ $action_type }}" class="dt-button buttons-print btn btn-dark px-3 rounded-10" tabindex="0"
                                            aria-controls="example" type="button"><span><i class="fa fa-file-pdf-o"
                                                    style="font-size:1.2rem"></i></span>
                                        </a>
                                    </div> --}}
                                    <table id="example" class="table table-hover table-custom border-bottom-0"
                                        style="width:100%">
                                        <thead>
                                            <tr>
                                                <th>S.No</th>
                                                @if (!auth()->user()->branch_id)
                                                    <th>Branch</th>
                                                @endif
                                                <th>Date</th>
                                                <th>Item</th>

                                                <th>Reference</th>
                                                <th>Action Type</th>
                                                <th>Unit</th>
                                                <th>Open Stock</th>
                                                <th>Qty</th>
                                                <th>Closing Stock</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @if (count($data) > 0)
                                                @foreach ($data as $key => $value)
                                                    <tr>
                                                        <td>{{ $key + 1 }}</td>
                                                        @if (!auth()->user()->branch_id)
                                                            <td>{{ getBranchById($value->shop_id) }}</td>
                                                        @endif
                                                        <td>{{ dateFormat($value->date_added, 1) }}</td>
                                                        @if ($value->item_price_id)
                                                            <td>{{ Str::ucfirst(getItemNameSize($value->item_price_id)) }}
                                                            </td>
                                                        @else
                                                            <td></td>
                                                        @endif

                                                        {{-- <td>{{ $value->reference_no }}</td> --}}
                                                        <td>{{ str_replace('_', ' ', $value->reference_key) }}</td>
                                                        <td>{{ $value->action_type }}</td>
                                                        <td>
                                                            {{ getUnitByItemId($value->item_id) }}
                                                        </td>
                                                        <td>{{ $value->open_stock }}</td>
                                                        <td>{{ $value->stock_value }}</td>
                                                        <td>{{ $value->closing_stock }}</td>
                                                    </tr>
                                                @endforeach
                                            @endif
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

@section('script')

    <script>
        $(document).ready(function() {
            $('#item_id').select2({
                theme: "bootstrap-5",
            });
        });
    </script>

@endsection
