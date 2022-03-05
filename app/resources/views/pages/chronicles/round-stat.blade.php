@extends('layouts.topnav')

@section('content')
<div class="box box-primary">
    <div class="box-header with-border text-center">
        <h3 class="box-title">{{ $statsHelper->getStatName($statKey) }}</h3>
        <span class="pull-left"><a href="{{ route('chronicles.round', $round) }}">Go back to Round</a></span>
    </div>

    <div class="box box-body">
        <table class="table table-striped table-hover">
            <colgroup>
                <col>
                <col>
            </colgroup>
            <thead>
                <tr>
                    <th>Value</th>
                    <th>Dominion</th>
                </tr>
            </thead>
            <tbody>
              @foreach($dominionStats as $dominionId => $value)
                  @if($value > 0)
                      @php
                          $dominion = OpenDominion\Models\Dominion::findOrFail($dominionId);
                      @endphp
                      <tr>
                          <td class="text-left">{{ number_format($value) }}</td>
                          <td class="text-left"><a href="{{ route('chronicles.dominion', $dominion->id) }}">{{ $dominion->name }}</a></td>
                      </tr>
                  @endif
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
