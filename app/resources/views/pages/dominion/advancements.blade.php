@extends('layouts.master')

@section('page-header', 'Technological Advances')

@section('content')
    @php
      $unlockedTechs = $selectedDominion->techs->pluck('key')->all()
    @endphp

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
                                <col width="100">
                                <col width="50">
                                <col width="50">
                                <col width="50">
                                <col width="50">
                                <col width="50">
                                <col width="50">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>Advancement</th>
                                    <th>Level 1<br>{{ $techCalculator->getTechCost($selectedDominion, null, 1) }}</th>
                                    <th>Level 2<br>{{ $techCalculator->getTechCost($selectedDominion, null, 2) }}</th>
                                    <th>Level 3<br>{{ $techCalculator->getTechCost($selectedDominion, null, 3) }}</th>
                                    <th>Level 4<br>{{ $techCalculator->getTechCost($selectedDominion, null, 4) }}</th>
                                    <th>Level 5<br>{{ $techCalculator->getTechCost($selectedDominion, null, 5) }}</th>
                                    <th>Level 6<br>{{ $techCalculator->getTechCost($selectedDominion, null, 6) }}</th>
                                </tr>
                            </thead>
                            @foreach ($techs as $tech)
                                @if($tech->level == 1)
                                    <tr>
                                    <td>{{ $tech->name }}</td>
                                @else
                                @endif
                                <td>
                                @if(in_array($tech->key, $unlockedTechs))
                                    <i class="fa fa-check text-green"></i>
                                @else
                                    <input type="radio" name="key" id="{{ $tech->key }}" value="{{ $tech->key }}" {{ count(array_diff($tech->prerequisites, $unlockedTechs)) != 0 ? 'disabled' : null }}>
                                @endif
                                </td>

                                @if($tech->level == 6)
                                    </tr>
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
