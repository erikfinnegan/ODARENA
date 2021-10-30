@extends('layouts.master')

@section('content')
    @php
        $boxColor = 'success';
    @endphp
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
                            <h4>{{ $event->source->name }}</h4>
                            @if ($event->source->realm->id === $selectedDominion->realm->id)
                                @if (isset($event->data['instant_return']))
                                    <p class="text-center text-blue">
                                        ⫷⫷◬⫸◬⫸◬<br>The waves align in your favour. <b>The invading units return home instantly.</b>
                                    </p>
                                @endif
                            @endif
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
                                    @for ($slot = 1; $slot <= 4; $slot++)
                                    @if((isset($event->data['units_sent'][$slot]) and $event->data['units_sent'][$slot] > 0) or
                                        (isset($event->data['units_lost'][$slot]) and $event->data['units_lost'][$slot] > 0) or
                                        (isset($event->data['units_returning'][$slot]) and $event->data['units_returning'][$slot] > 0)
                                        )

                                        @php
                                            $unitType = "unit{$slot}";
                                        @endphp
                                        <tr>
                                            <td>
                                                <span data-toggle="tooltip" data-placement="top" title="{{ $unitHelper->getUnitHelpString($unitType, $event->source->race) }}">
                                                    {{ $event->source->race->units->where('slot', $slot)->first()->name }}
                                                </span>
                                            </td>
                                            <td>
                                                @if (isset($event->data['units_sent'][$slot]))
                                                  {{ number_format($event->data['units_sent'][$slot]) }}
                                                @else
                                                  0
                                                @endif
                                            </td>
                                            {{--
                                            <td>
                                                @if (isset($event->data['units_lost'][$slot]))
                                                  {{ number_format($event->data['units_lost'][$slot]) }}
                                                @else
                                                  0
                                                @endif
                                            </td>
                                            --}}
                                            <td>
                                              @if ($event->source->realm->id === $selectedDominion->realm->id)
                                                  @if (isset($event->data['units_returning'][$slot]))
                                                    {{ number_format($event->data['units_returning'][$slot]) }}
                                                  @else
                                                    0
                                                  @endif
                                              @else
                                                    <span class="text-muted">?</span>
                                              @endif
                                            </td>
                                        </tr>
                                    @endif
                                    @endfor
                            </table>

                            @if ($event->source->realm->id === $selectedDominion->realm->id)
                            <table class="table">
                                <colgroup>
                                    <col width="33%">
                                    <col>
                                </colgroup>
                                <tbody>
                                    <tr>
                                        <td>OP:</td>
                                        <td>{{ number_format($event->data['op_sent']) }}</td>
                                    </tr>
                                    <tr>
                                        <td>Prestige:</td>
                                        <td>{{ number_format($event->data['prestige_change']) }}</td>
                                    </tr>
                                    <tr>
                                        <td>XP:</td>
                                        <td>{{ number_format($event->data['xp']) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                            @endif

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
@endsection
