@extends('layouts.master')

@section('page-header', 'Invasion Result')

@section('content')
    <div class="row">
        <div class="col-sm-12 col-md-8 col-md-offset-2">
            <div class="box box-green">
                <div class="box-header with-border">
                    <h3 class="box-title">
                        <i class="ra ra-boot-stomp"></i>
                        @if($event->target->realm->id === $selectedDominion->realm->id)
                          <span class="text-red">
                        @else
                          <span class="text-green">
                        @endif
                        {{ $event->source->name }}

                        is invading

                        {{ $event->target->name }}
                        </span>
                    </h3>
                </div>
                <div class="box-bod no-padding">
                    <div class="row">
                        <div class="col-xs-12 col-sm-12">
                            <div class="text-center">
                            <h4>Units Sent</h4>
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th class="text-center">Unit</th>
                                            <th class="text-center">Sent</th>
                                        </tr>
                                    </thead>
                                @foreach($event['data']['unitsSent'] as $unitSlot => $unitsSent)

                                @php
                                    $unit = $selectedDominion->race->units->filter(function ($unit) use ($unitSlot) {
                                        return ($unit->slot == (int)$unitSlot);
                                    })->first();
                                @endphp

                                    <tr>
                                        <td>
                                            <span data-toggle="tooltip" data-placement="top" title="{{ $unitHelper->getUnitHelpString('unit'.$unitSlot, $selectedDominion->race) }}">
                                            {{ str_plural($unit->name, $unitsSent) }}
                                            </span>
                                        </td>
                                        <td>{{ number_format($unitsSent) }}</td>
                                    </tr>
                                @endforeach
                                </table>
                                <p>The units will arrive at {{ $event->target->name }} in <strong>four</strong> ticks.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
