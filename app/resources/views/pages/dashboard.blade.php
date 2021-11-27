@extends('layouts.master')

@section('page-header', 'Dashboard')

@section('content')
    <div class="row">
        <div class="col-lg-9">
            <div class="box box-primary">
                <table class="table table-striped">
                    <colgroup>
                        <col width="60">
                        <col width="120">
                        <col width="180">
                        <col width="360">
                        <col width="180">
                        <col width="120">
                        <col width="120">
                        <col>
                    </colgroup>
                    <thead>
                        <tr>
                            <th class="text-center">Round</th>
                            <th>Era</th>
                            <th>Chapter</th>
                            <th>Dominion</th>
                            <th>Status</th>
                            <th>Faction</th>
                            <th>Land</th>
                            <th>Networth</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($rounds->all() as $round)
                        @if($roundService->hasUserDominionInRound($round))
                            @php
                                $dominion = $roundService->getUserDominionFromRound($round);
                            @endphp
                        @else
                            @php
                                $dominion = null;
                            @endphp
                        @endif
                        <tr>
                            <td class="text-center">{{ $round->number }}</td>
                            <td>{{ $round->league->description }}</td>
                            <td>{{ $round->name }}</td>
                            <td>
                                @if(isset($dominion))
                                    @if ($dominion->isSelectedByAuthUser())
                                        <a href="{{ route('dominion.status') }}">{{ $dominion->name }}</a>&nbsp;<span class="label label-success">Selected</span>
                                    @else
                                        <form action="{{ route('dominion.select', $dominion) }}" method="post">
                                            @csrf
                                            <button type="submit" class="btn btn-link" style="padding: 0;">{{ $dominion->name }}</button>
                                        </form>
                                    @endif
                                @else
                                    &mdash;
                                @endif
                            </td>
                            <td>
                                @if($roundService->hasUserDominionInRound($round))

                                    @if($round->hasEnded())
                                        <span data-toggle="tooltip" data-placement="top" title="Start: {{ $round->start_date }}.<br>Registration: {{ $dominion->created_at }}.<br>Ended: {{ $round->end_date }}.">
                                            <span class="label label-info">Finished</span>
                                        </span>
                                    @endif

                                    @if($dominion->is_locked)
                                        <span data-toggle="tooltip" data-placement="top" title="This dominion was locked.">
                                            <span class="label label-warning">Locked</span>
                                        </span>
                                    @endif

                                    @if(!$round->hasEnded() and !$dominion->isLocked())
                                        <span data-toggle="tooltip" data-placement="top" title="Start: {{ $round->start_date }}.<br>Current tick: {{ number_format($round->ticks) }}.<br>You joined: {{ $dominion->created_at }}.">
                                            <span class="label label-success">Playing</span>
                                        </span>
                                    @endif

                                @else
                                    @if($round->hasEnded() and $user->created_at <= $round->start_date)
                                        <span data-toggle="tooltip" data-placement="top" title="Start: {{ $round->start_date }}.<br>Ended: {{ $round->end_date }}.">
                                            <span class="label label-primary">Ended</span>
                                        </span>
                                    @elseif($round->hasEnded() and $user->created_at > $round->start_date)
                                        <span data-toggle="tooltip" data-placement="top" title="Start: {{ $round->start_date }}.<br>Ended: {{ $round->end_date }}.<br>User registration date: {{ $user->created_at }}.">
                                            <span class="label label-primary">User was not registered</span>
                                        </span>
                                    @elseif(!$round->hasEnded())
                                        <span data-toggle="tooltip" data-placement="top" title="Join round {{ $round->number }}!">
                                            <a href="{{ route('round.register', $round) }}" class="btn btn-success btn-round"><i class="fas fa-plus-circle"></i> Register</a>
                                        </span>
                                        <p>
                                        @if(!$round->hasStarted())
                                            <small style="text-muted">The round starts at {{ $round->start_date }}. The target land size is {{ number_format($round->land_target) }} acres.</p>
                                        @else
                                            <small style="text-muted">The round started at {{ $round->start_date }}. Current tick: {{ number_format($round->ticks) }}.</p>
                                        @endif
                                        </p>
                                    @endif
                                @endif
                            </td>

                            <td>
                                @if($dominion)
                                    <a href="{{ route('scribes.faction', str_slug($dominion->race->name)) }}" target="_blank"><i class="ra ra-scroll-unfurled"></i></a> {{ $dominion->race->name }}
                                @else
                                    &mdash;
                                @endif
                            </td>

                            <td>
                                @if($dominion)
                                    {{ number_format($landCalculator->getTotalLand($dominion)) }}
                                @else
                                    &mdash;
                                @endif
                            </td>

                            <td>
                                @if($dominion)
                                    {{ number_format($networthCalculator->getDominionNetworth($dominion)) }}
                                @else
                                    &mdash;
                                @endif
                            </td>


                        </tr>
                    @endforeach
                </table>
            </div>
        </div>
        <div class="col-sm-12 col-md-3">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">Information</h3>
                </div>
                <div class="box-body">
                    <p>Welcome {{ $dominions->isEmpty() ? '' : 'back' }} to ODARENA, <strong>{{ Auth::user()->display_name }}</strong>!</p>
                    <p>You have been playing since {{ $user->created_at }} and have participated in {{ number_format($dominions->count()) }} of {{ number_format($rounds->where('start_date', '>=', $user->created_at)->count()) }} rounds since joining.</p>
                </div>
            </div>
        </div>
    </div>
@endsection
