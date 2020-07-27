@extends('layouts.master')

@section('page-header', 'Military Advisor')

@section('content')
    @include('partials.dominion.advisor-selector')

    <div class="row">

        <div class="col-sm-12 col-md-6">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="ra ra-sword"></i> Units Training and Home</h3>
                </div>
                <div class="box-body table-responsive no-padding">
                    <table class="table">
                        <colgroup>
                            <col>
                            @for ($i = 1; $i <= 12; $i++)
                                <col width="20">
                            @endfor
                            <col width="100">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>Unit</th>
                                @for ($i = 1; $i <= 12; $i++)
                                    <th class="text-center">{{ $i }}</th>
                                @endfor
                                <th class="text-center">Home<br>(Training)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($unitHelper->getUnitTypes() as $unitType)
                                <tr>
                                    <td>

                                        <span data-toggle="tooltip" data-placement="top" title="{{ $unitHelper->getUnitHelpString($unitType, $selectedDominion->race) }}">
                                            {{ $unitHelper->getUnitName($unitType, $selectedDominion->race) }}
                                        </span>
                                    </td>
                                    @for ($i = 1; $i <= 12; $i++)
                                        <td class="text-center">
                                            @if ($queueService->getTrainingQueueAmount($selectedDominion, "military_{$unitType}", $i) === 0)
                                                -
                                            @else
                                                {{ number_format($queueService->getTrainingQueueAmount($selectedDominion, "military_{$unitType}", $i)) }}
                                            @endif
                                        </td>
                                    @endfor
                                    <td class="text-center">
                                        {{ number_format($selectedDominion->{'military_' . $unitType}) }}<br>
                                        ({{ number_format($queueService->getTrainingQueueTotalByResource($selectedDominion, "military_{$unitType}")) }})
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-sm-12 col-md-6">
            <div class="box box-danger">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="ra ra-boot-stomp"></i> Units Invading</h3>
                </div>
                <div class="box-body table-responsive no-padding">
                    <table class="table">
                        <colgroup>
                            <col>
                            @for ($i = 1; $i <= 12; $i++)
                                <col width="20">
                            @endfor
                            <col width="100">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>Unit</th>
                                @for ($i = 1; $i <= 12; $i++)
                                    <th class="text-center">{{ $i }}</th>
                                @endfor
                                <th class="text-center">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (range(1, 4) as $slot)
                                @php($unitType = ('unit' . $slot))
                                <tr>
                                    <td>

                                        <span data-toggle="tooltip" data-placement="top" title="{{ $unitHelper->getUnitHelpString($unitType, $selectedDominion->race) }}">
                                            {{ $unitHelper->getUnitName($unitType, $selectedDominion->race) }}
                                        </span>
                                    </td>
                                    @for ($i = 1; $i <= 12; $i++)
                                        <td class="text-center">
                                            @if ($queueService->getInvadingQueueAmount($selectedDominion, "military_{$unitType}", $i) === 0)
                                                -
                                            @else
                                                {{ number_format($queueService->getInvadingQueueAmount($selectedDominion, "military_{$unitType}", $i)) }}
                                            @endif
                                        </td>
                                    @endfor
                                    <td class="text-center">
                                        {{ number_format($queueService->getInvadingQueueTotalByResource($selectedDominion, "military_{$unitType}")) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="box box-success">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="ra ra-sideswipe"></i> Units Returning</h3>
                </div>
                <div class="box-body table-responsive no-padding">
                    <table class="table">
                        <colgroup>
                            <col>
                            @for ($i = 1; $i <= 12; $i++)
                                <col width="20">
                            @endfor
                            <col width="100">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>Unit</th>
                                @for ($i = 1; $i <= 12; $i++)
                                    <th class="text-center">{{ $i }}</th>
                                @endfor
                                <th class="text-center">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (range(1, 4) as $slot)
                                @php($unitType = ('unit' . $slot))
                                <tr>
                                    <td>

                                        <span data-toggle="tooltip" data-placement="top" title="{{ $unitHelper->getUnitHelpString($unitType, $selectedDominion->race) }}">
                                            {{ $unitHelper->getUnitName($unitType, $selectedDominion->race) }}
                                        </span>
                                    </td>
                                    @for ($i = 1; $i <= 12; $i++)
                                        <td class="text-center">
                                            @if ($queueService->getInvasionQueueAmount($selectedDominion, "military_{$unitType}", $i) === 0)
                                                -
                                            @else
                                                {{ number_format($queueService->getInvasionQueueAmount($selectedDominion, "military_{$unitType}", $i)) }}
                                            @endif
                                        </td>
                                    @endfor
                                    <td class="text-center">
                                        {{ number_format($queueService->getInvasionQueueTotalByResource($selectedDominion, "military_{$unitType}")) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
@endsection
