@extends('layouts.topnav')

@section('content')

@include('partials.chronicles.top-row')

<div class="box box-primary">
    <div class="box-header with-border">
        <h1 class="box-title"><i class="ra ra-knight-helmet ra-fw"></i> Chronicles of {{ $user->display_name }}</h1>
    </div>

    <div class="box-body">
        <div class="row">

              <div class="col-sm-4 text-center">
                  <h4>Land max:</h4>
                  <h3>{{ number_format($userHelper->getMaxLandForUser($user)) }}</h3>
              </div>

              <div class="col-sm-4 text-center">
                  <h4>Land average:</h4>
                  <h3>{{ number_format($userHelper->getTotalLandForUser($user) / max(1, count($userHelper->getUserDominions($user)))) }}</h3>
              </div>

              <div class="col-sm-4 text-center">
                  <h4>Factions played:</h4>
                  <h3>{{ number_format($userHelper->getUniqueRacesCountForUser($user)) }}</h3>
              </div>

        </div>

        <div class="row">

            <div class="col-sm-3">
                <img src="{{ $user->getAvatarUrl() }}"  class="img-responsive" style="width: 100%; display: inline; vertical-align: top; margin: 4px;">
                <p>
                    <strong>{{ $user->display_name }}</strong> joined ODARENA {{ $user->created_at->toFormattedDateString() }} and has played {{ number_format($userHelper->getRoundsPlayed($user)) . ' ' . str_plural('round',$userHelper->getRoundsPlayed($user)) }}.
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
                    @foreach ($topRaces as $topRace => $timesPlayed)
                        <tr>
                            <td>{{ array_search($topRace, array_keys($topRaces))+1 }}.</td>
                            <td><a href="{{ route('chronicles.faction', $topRace) }}">{{ $topRace }}</a></td>
                            <td>{{ number_format($timesPlayed) }}</td>
                        </tr>
                    @endforeach
                    </tr>
                </table>
            </div>

        </div>



        <div class="row">
          @php
              $conquered = $userHelper->getStatSumForUser($user, 'land_conquered');
              $explored = $userHelper->getStatSumForUser($user, 'land_explored');
              $discovered = $userHelper->getStatSumForUser($user, 'land_discovered');
              $lost = $userHelper->getStatSumForUser($user, 'land_lost');

              $total = $conquered + $explored + $discovered + $lost;

          @endphp

          <div class="col-sm-12 text-center">
              <h4>Land stats:</h4>
              <p>Distribution of total land lost and gained ({{ number_format($total)}} acres).</p>
              <div class="progress">
              @if($total == 0)
                  <div class="progress-bar" role="progressbar" style="width: 100%" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100">No data</div>
              @else
                  <div class="progress-bar label-success" role="progressbar" style="width: {{ ($conquered / $total)*100 }}%" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100">Conquered ({{ number_format(($conquered / $total)*100,2) }}%)</div>
                  <div class="progress-bar label-info" role="progressbar" style="width: {{ ($discovered / $total)*100 }}%" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100">Discovered ({{ number_format(($discovered / $total)*100,2) }}%)</div>
                  <div class="progress-bar label-warning" role="progressbar" style="width: {{ ($explored / $total)*100 }}%" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100">Explored ({{ number_format(($explored / $total)*100,2) }}%)</div>
                  <div class="progress-bar label-danger" role="progressbar" style="width: {{ ($lost / $total)*100 }}%" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100">Lost ({{ number_format(($lost / $total)*100,2) }}%)</div>
              @endif
              </div>
          </div>

        </div>

        <div class="row">
          @php
              $successful = $userHelper->getStatSumForUser($user, 'invasion_victories') + $userHelper->getStatSumForUser($user, 'invasion_bottomfeeds') + $userHelper->getStatSumForUser($user, 'defense_success');
              $unsuccessful = $userHelper->getStatSumForUser($user, 'invasion_razes') + $userHelper->getStatSumForUser($user, 'invasion_failures');
              $timesInvaded = $userHelper->getStatSumForUser($user, 'defense_failures');

              $total = $successful + $unsuccessful + $timesInvaded;

          @endphp

          <div class="col-sm-12 text-center">
              <h4>Military success ratio:</h4>
              <p>How the armies of {{ $user->display_name }} have fared throughout {{ number_format($total) }} battles.</p>

              <div class="progress">
              @if($total == 0)
                  <div class="progress-bar" role="progressbar" style="width: 100%" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100">No data</div>
              @else
                  <div class="progress-bar label-success" role="progressbar" style="width: {{ ($successful / $total)*100 }}%" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100">Successful ({{ number_format(($successful / $total)*100,2) }}%)</div>
                  <div class="progress-bar label-warning" role="progressbar" style="width: {{ ($unsuccessful / $total)*100 }}%" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100">Unsuccessful ({{ number_format(($unsuccessful / $total)*100,2) }}%)</div>
                  <div class="progress-bar label-danger" role="progressbar" style="width: {{ ($timesInvaded / $total)*100 }}%" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100">Invaded ({{ number_format(($timesInvaded / $total)*100,2) }}%)</div>
              @endif
              </div>
          </div>

        </div>

        <div class="row">

            <div class="col-sm-12">
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
                            <td><a href="{{ route('chronicles.dominion', $dominion) }}">{{ $dominion->name }}</a></td>
                            <td>
                                <a href="{{ route('scribes.faction', str_slug($dominion->race->name)) }}" target="_blank"><i class="ra ra-scroll-unfurled"></i></a>&nbsp;
                                <a href="{{ route('chronicles.faction', $dominion->race->name) }}">{{ $dominion->race->name }}</a>
                            </td>
                            <td>{{ number_format($landCalculator->getTotalLand($dominion)) }}</td>
                            <td>{{ number_format($networthCalculator->getDominionNetworth($dominion)) }}</td>
                            <td><a href="{{ route('chronicles.round', $dominion->round) }}">{{ $dominion->round->name }}</a></td>
                            <td>{{ $dominion->round->league->description }}</td>
                        </tr>
                    @endforeach
                </table>
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
            var table = $('#dominions-table').DataTable({
                order: [0, 'desc'],
                paging: true,
            });
        })(jQuery);
    </script>
@endpush
