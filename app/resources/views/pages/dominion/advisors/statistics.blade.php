@extends('layouts.master')
@section('title', 'Statistics Advisor')

@section('content')
    @include('partials.dominion.advisor-selector')

<div class="row">
    <div class="col-md-12 col-md-12">
        <div class="box box-primary">
            <div class="box-body no-padding">
                <div class="col-xs-12 col-sm-12">
                    <div class="box-header with-border">
                        <h4 class="box-title"><i class="fa fa-chart-bar fa-fw"></i> Statistics</h4>
                    </div>
                </div>
                <div class="col-xs-12 col-sm-12">
                    <table class="table table-striped table-hover" id="stats-table">
                        <colgroup>
                            <col width="20%">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>Statistic</th>
                                <th>Value</th>
                            </tr>
                        </thead>
                            @foreach($dominionStats as $dominionStat)
                                @php
                                    $stat = OpenDominion\Models\Stat::where('id', $dominionStat->stat_id)->first();
                                @endphp

                                @if($dominionStat->value !== 0)
                                    <tr>
                                        <td>{{ $stat->name }}</td>
                                        <td>{{ number_format($dominionStat->value) }}
                                    </tr>
                                @endif
                            @endforeach
                        <tbody>

                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('page-styles')
    <link rel="stylesheet" href="{{ asset('assets/vendor/datatables/css/dataTables.bootstrap.css') }}">
    <style>
        #dominion-search #dominions-table_filter { display: none !important; }
    </style>
@endpush

@push('page-scripts')
    <script type="text/javascript" src="{{ asset('assets/vendor/datatables/js/jquery.dataTables.js') }}"></script>
    <script type="text/javascript" src="{{ asset('assets/vendor/datatables/js/dataTables.bootstrap.js') }}"></script>
@endpush

@push('inline-scripts')
    <script type="text/javascript">
        (function ($) {
            var table = $('#stats-table').DataTable({
                order: [0, 'asc'],
                paging: true,
            });
        })(jQuery);
    </script>
@endpush
