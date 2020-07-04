@extends('layouts.master')

@section('page-header', 'Technological Advances')

@section('content')
    @php($unlockedTechs = $selectedDominion->techs->pluck('key')->all())

    <div class="row">

        <div class="col-sm-12 col-md-9">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-flask"></i> Advancements</h3>
                </div>
                <form action="{{ route('dominion.advancements') }}" method="post" role="form">
                    @csrf
                    <div class="box-body table-responsive no-padding">
                        <table class="table">
                            <colgroup>
                                <col width="50">
                                <col width="200">
                                <col>
                                <col width="150">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>&nbsp;</th>
                                    <th>Name</th>
                                    <th>Description</th>
                                    <th>Requires</th>
                                    <th>XP Cost</th>
                                </tr>
                            </thead>
                            {{ dd($techs) }}
                            @foreach ($techs as $tech)
                                @if($tech->enabled == 1)
                                    @if(count(array_diff($tech->prerequisites, $unlockedTechs)) == 0 or in_array($tech->key, $unlockedTechs))
                                        <tr class="{{ in_array($tech->key, $unlockedTechs) ? 'text-green' : 'text-default' }}">
                                            <td class="text-center">
                                                @if(in_array($tech->key, $unlockedTechs))
                                                    <i class="fa fa-check"></i>
                                                @else
                                                    <input type="radio" name="key" id="{{ $tech->key }}" value="{{ $tech->key }}" {{ count(array_diff($tech->prerequisites, $unlockedTechs)) != 0 ? 'disabled' : null }}>
                                                @endif
                                            </td>
                                              <td class="text-normal"><label for="{{ $tech->key }}" style="font-weight: normal;">{{ $tech->name }}</label></td>

                                            <td><label for="{{ $tech->key }}" style="font-weight: normal;">{{ $techHelper->getTechDescription($tech) }}</label></td>
                                            <td>
                                                @if ($tech->prerequisites)
                                                    @foreach ($tech->prerequisites as $key)
                                                        {{ $techs[$key]->name }}@if(!$loop->last),<br/>@endif
                                                    @endforeach
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td>
                                                {{ number_format($techCalculator->getTechCost($selectedDominion, $tech)) }}
                                            </td>
                                        </tr>
                                    @endif
                                @endif
                            @endforeach
                        </table>
                    </div>
                    <div class="box-footer">
                        <button type="submit" class="btn btn-primary">Unlock</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-sm-12 col-md-3">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">Information</h3>
                </div>
                <div class="box-body">
                    <p>You can unlock technological advancements by earning enough experience points (XP). You can XP by invading, exploring, and every tick from your prestige.</p>
                    <p>Each advancement improves an aspect of your dominion. Only the highest level advancement counts. If you have unlocked Level 1 and Level 2, only the bonus from the Level 2 advancement counts.</p>
                    <p>You have <b>{{ number_format($selectedDominion->resource_tech) }} experience points</b>.</p>
                </div>
            </div>
        </div>

    </div>
@endsection
