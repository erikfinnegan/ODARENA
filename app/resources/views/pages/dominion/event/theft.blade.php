@extends('layouts.master')

@section('content')
    @php
        $boxColor = 'success';
    @endphp
    @if($selectedDominion->realm->id !== $event->source->realm->id and $selectedDominion->realm->id !== $event->target->realm->id)
        <div class="row">
            <div class="col-sm-12 col-md-8 col-md-offset-2">
                <div class="box box-{{ $boxColor }}">
                    <div class="box-header with-border">
                        <h3 class="box-title">
                            <i class="fas fa-hand-lizard"></i> Theft
                        </h3>
                    </div>
                    <div class="box-bod no-padding">
                        You cannot view this event.
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="row">
            <div class="col-sm-12 col-md-8 col-md-offset-2">
                <div class="box box-{{ $boxColor }}">
                    <div class="box-header with-border">
                        <h3 class="box-title">
                            <i class="fas fa-hand-lizard"></i> Theft
                        </h3>
                    </div>
                    <div class="box-bod no-padding">
                        <div class="row">
                            <div class="col-xs-12 col-sm-12">
                                <div class="text-center">
                                <h4>{{ $event->source->name }} theft from {{ $event->target->name }} </h4>
                                </div>
                                <table class="table">
                                    <colgroup>
                                        <col width="25%">
                                        <col width="25%">
                                        <col width="25%">
                                        <col width="25%">
                                    </colgroup>
                                    <thead>
                                        <tr>
                                            <th>Unit</th>
                                            <th>Sent</th>
                                            <th>Lost</th>
                                            <th>Returning</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($event->data['units'] as $slot => $amount)
                                            @php
                                                if($slot == 'spies')
                                                {
                                                    $unitType = 'spies';
                                                }
                                                else
                                                {
                                                    $unitType = 'unit' . $slot;
                                                }

                                            @endphp
                                            <tr>
                                                <td>
                                                    <span data-toggle="tooltip" data-placement="top" title="{{ $unitHelper->getUnitHelpString($unitType, $event->source->race) }}">
                                                        {{ $event->source->race->units->where('slot', $slot)->first()->name }}
                                                    </span>
                                                </td>
                                                <td>{{ number_format($event->data['units'][$slot]) }}</td>
                                                <td>{{ number_format($event->data['killed_units'][$slot]) }}</td>
                                                <td>{{ number_format($event->data['returning_units'][$slot]) }}</td>
                                            </tr>
                                        @endforeach
                                </table>

                                <table class="table">
                                    <colgroup>
                                        <col width="25%">
                                        <col>
                                    </colgroup>
                                    <tbody>
                                        <tr>
                                            <td>Resource:</td>
                                            <td>{{ $event->data['resource']['name'] }}</td>
                                        </tr>
                                        <tr>
                                            <td>Amount stolen:</td>
                                            <td>{{ number_format($event->data['amount_stolen']) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="box-footer">
                        <div class="pull-right">
                            <small class="text-muted">
                                Theft recorded at
                                {{ $event->created_at }}, tick
                                {{ number_format($event->tick) }}.
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection
