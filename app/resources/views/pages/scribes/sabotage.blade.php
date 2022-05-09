@extends('layouts.topnav')
@section('title', "Scribes | Sabotage")

@section('content')
@include('partials.scribes.nav')
<div class="box box-primary">
    <div class="box-header with-border">
        <h3 class="box-title">Sabotage</h3>
    </div>
    <div class="box-body">
        <div class="row">
            <div class="col-md-12">
                <p>The formula used to calculate sabotage damage is:</p>
                <code>
                    [Ratio Multiplier] =  1 + (([Saboteur SPA] - [Target SPA]) / [Saboteur SPA]) (min 0, max 1)<br>
                    <br>
                    [Target Multiplier] = the sum of the target's sabotage damage suffered<br>
                    <br>
                    [Saboteur Multiplier] = the sum of the saboteur's sabotage damage dealt perks<br>
                    <br>
                    [Damage] = [Base Damage] * [Ratio Multiplier] * [Target Multiplier] * [Saboteur Multiplier]
                </code>
            </div>
        </div>
    </div>
</div>
<div class="box">
    <div class="box-header with-border">
        <h3 class="box-title">Operations</h3>
    </div>

    <div class="box-body table-responsive">
        <div class="row">
            <div class="col-md-12">
              <table class="table table-striped">
                  <colgroup>
                      <col width="200">
                      <col>
                  </colgroup>
                  <thead>
                      <tr>
                          <th>Operation</th>
                          <th>Effect</th>
                      </tr>
                  </thead>
                  @foreach ($spyops as $spyop)
                      @if($spyop->scope == 'hostile')
                      <tr>
                          <td>
                              {{ $spyop->name }}
                              {!! $espionageHelper->getExclusivityString($spyop) !!}
                          </td>
                          <td>
                              <ul>
                                  @foreach($espionageHelper->getSpyopEffectsString($spyop) as $effect)
                                      <li>{{ ucfirst($effect) }}</li>
                                  @endforeach
                              <ul>
                          </td>
                      </tr>
                      @endif
                  @endforeach
              </table>
            </div>
        </div>
    </div>
</div>
@endsection
