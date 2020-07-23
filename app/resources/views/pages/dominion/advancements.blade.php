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
                                <col width="200">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>Advancement</th>
                                    @for ($i = 1; $i <= 8; $i++)
                                        <th class="text-center">Level {{ $i }}</th>
                                    @endfor
                                </tr>
                            </thead>
                            @foreach ($techs as $tech)
                                @if($tech->level == 1)
                                    <tr>
                                    <td>{{ $tech->name }}</td>
                                @else
                                @endif

                                <td class="text-center">
                                    <span data-toggle="tooltip" data-placement="top" title="{{ $techHelper->getTechDescription($tech) }}<br>XP: {{ number_format($techCalculator->getTechCost($selectedDominion, null, $tech->level)) }}" style="display:block;">

                                @if(in_array($tech->key, $unlockedTechs))
                                    <i class="fa fa-check text-green"></i>
                                @elseif(count(array_diff($tech->prerequisites, $unlockedTechs)) == 0 and $techCalculator->canAffordTech($selectedDominion, $tech->level))
                                    <input type="radio" name="key" id="{{ $tech->key }}" value="{{ $tech->key }}">
                                @else
                                    <input type="radio" name="key" id="{{ $tech->key }}" value="{{ $tech->key }}" disabled>
                                @endif
                                    </span>
                                </td>

                                @if($tech->level == 8)
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
                    <p>You can unlock technological advancements by earning enough experience points (XP). You can XP by invading, exploring, and every tick from your prestige. The cost of each advancement level is shown below:</p>
                    <ul>
                    @for ($i = 1; $i <= 6; $i++)
                        <li>Level {{ $i }}: {{ number_format($techCalculator->getTechCost($selectedDominion, null, $i)) }}</li>
                    @endfor
                    </ul>
                    <p>You have <b>{{ number_format($selectedDominion->resource_tech) }} XP</b>.</p>
                    <p>Only the perks from the highest level advancement counts. if you have Level 1 and Level 2, only Level 2 counts.</p>

                </div>
            </div>
        </div>

    </div>
@endsection
