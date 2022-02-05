@extends('layouts.topnav')

@section('content')
    <div class="box box-primary">
        <div class="box-header with-border">
            <h1 class="box-title"><i class="ra ra-knight-helmet"></i> Chronicles of {{ $user->display_name }}</h1>
        </div>

        <div class="box-body">
            <div class="row">

                <div class="col-sm-3">
                    <img src="{{ $user->getAvatarUrl() }}"  class="img-responsive" style="width: 100%; display: inline; vertical-align: top; margin: 4px;">
                    <p>
                        <strong>{{ $user->display_name }}</strong> joined ODARENA {{ $user->created_at->toFormattedDateString() }} and has played {{ number_format($userHelper->getRoundsPlayed($user)) }} rounds.
                    </p>
                </div>
                <div class="col-sm-3">
                    <div class="box-header with-border">
                        <h4 class="box-title"><i class="ra ra-sword ra-fw"></i> Military Accomplishments</h4>
                    </div>

                    <table class="table table-striped table-hover">
                        <colgroup>
                            <col width="50%">
                            <col>
                        </colgroup>
                        <tbody>
                        <tr>
                        @foreach ($militarySuccessStats as $statKey)
                            <tr>
                                <td class="text-right">{{ $statsHelper->getStatName($statKey) }}:</td>
                                <td>{{ number_format($userHelper->getStatSumForUser($user, $statKey)) }}</td>
                            </tr>
                        @endforeach
                        </tr>
                    </table>
                </div>

                <div class="col-sm-3">
                    <div class="box-header with-border">
                        <h4 class="box-title"><i class="ra ra-broken-skull ra-fw"></i> Military Failures</h4>
                    </div>

                    <table class="table table-striped table-hover">
                        <colgroup>
                            <col width="50%">
                            <col>
                        </colgroup>
                        <tbody>
                        <tr>
                        @foreach ($militaryFailureStats as $statKey)
                            <tr>
                                <td class="text-right">{{ $statsHelper->getStatName($statKey) }}:</td>
                                <td>{{ number_format($userHelper->getStatSumForUser($user, $statKey)) }}</td>
                            </tr>
                        @endforeach
                        </tr>
                    </table>
                </div>

                <div class="col-sm-3">
                    <div class="box-header with-border">
                        <h4 class="box-title"><i class="fas fa-flag fa-fw"></i> Factions</h4>
                    </div>

                    <table class="table table-striped table-hover">
                        <colgroup>
                            <col width="10">
                            <col width="50%">
                            <col>
                        </colgroup>
                        <tbody>
                        <tr>
                        @foreach ($userHelper->getTopRaces($user, 5) as $topRace => $timesPlayed)
                            <tr>
                                <td>{{ array_search($topRace, array_keys($userHelper->getTopRaces($user, 5)))+1 }}.</td>
                                <td>{{ $topRace }}</td>
                                <td>{{ number_format($timesPlayed) }}</td>
                            </tr>
                        @endforeach
                        </tr>
                    </table>
                </div>

            </div>

            <table class="table table-striped table-hover" id="dominions-table">
                <colgroup>
                    <col width="60">
                    <col>
                    <col width="180">
                    <col width="150">
                    <col width="120">
                    <col width="120">
                    <col width="120">
                </colgroup>
                <thead>
                    <tr>
                        <th class="text-center">Round</th>
                        <th>Dominion</th>
                        <th>Faction</th>
                        <th>Land</th>
                        <th>Networth</th>
                        <th>Chapter</th>
                        <th>Era</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($dominions as $dominion)
                    <tr>
                        <td class="text-center"><a href="{{ route('chronicles.round', $dominion->round) }}">{{ $dominion->round->number }}</a></td>
                        <td>{{ $dominion->name }}</td>
                        <td><a href="{{ route('scribes.faction', str_slug($dominion->race->name)) }}" target="_blank"><i class="ra ra-scroll-unfurled"></i></a> {{ $dominion->race->name }}</td>
                        <td>{{ number_format($landCalculator->getTotalLand($dominion)) }}</td>
                        <td>{{ number_format($networthCalculator->getDominionNetworth($dominion)) }}</td>
                        <td><a href="{{ route('chronicles.round', $dominion->round) }}">{{ $dominion->round->name }}</a></td>
                        <td>{{ $dominion->round->league->description }}</td>
                    </tr>
                @endforeach
            </table>

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
            var table = $('#dominions-table').DataTable({
                order: [0, 'desc'],
                paging: true,
            });
        })(jQuery);
    </script>
@endpush
