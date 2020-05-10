@extends('layouts.topnav')

@section('content')
    @include('partials.scribes.nav')
    <div class="box box-primary">
        <div class="box-header with-border">
            <h3 class="box-title">Factions</h3>
        </div>
        <div class="box-body">
            <div class="row">
                <div class="col-md-12 col-md-12">
                    <p>Players must choose a faction for their dominion. Each faction has unique bonuses, military units, and spells.</p>
                    <em>
                        <p>More information can be found on the <a href="https://odarena.miraheze.org/wiki/Factions">wiki</a>.</p>
                    </em>
                </div>
            </div>
        </div>
    </div>
    <div class="box">
        <div class="box-body no-padding">
            <div class="row">
                <div class="col-md-12 col-md-6">
                    <div class="box-header with-border">
                        <h4 class="box-title">The Commonwealth</h4>
                    </div>
                    <table class="table table-striped" style="margin-bottom: 0">
                        <tbody>
                            @foreach ($goodRaces as $race)
                            @if($race['playable'] == 1)
                                <tr>
                                    <td>
                                        <a href="{{ route('scribes.faction', str_slug($race['name'])) }}">{{ $race['name'] }}</a>
                                    </td>
                                </tr>
                            @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="col-md-12 col-md-6">
                    <div class="box-header with-border">
                        <h4 class="box-title">The Empire</h4>
                    </div>
                    <table class="table table-striped" style="margin-bottom: 0">
                        <tbody>
                            @foreach ($evilRaces as $race)
                            @if($race['playable'] == 1)
                                <tr>
                                    <td>
                                        <a href="{{ route('scribes.faction', $race['name']) }}">{{ $race['name'] }}</a>
                                    </td>
                                </tr>
                            @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

              <div class="row">
                  <div class="col-md-12 col-md-6">
                      <div class="box-header with-border">
                          <h4 class="box-title">Independent</h4>
                      </div>
                      <table class="table table-striped" style="margin-bottom: 0">
                          <tbody>
                              @foreach ($independentRaces as $race)
                              @if($race['playable'] == 1)
                                  <tr>
                                      <td>
                                          <a href="{{ route('scribes.faction', str_slug($race['name'])) }}">{{ $race['name'] }}</a>
                                      </td>
                                  </tr>
                              @endif
                              @endforeach
                          </tbody>
                      </table>
                  </div>
                  <div class="col-md-12 col-md-6">
                      <div class="box-header with-border">
                          <h4 class="box-title">Barbarian Horde</h4>
                      </div>
                      <table class="table table-striped" style="margin-bottom: 0">
                          <tbody>
                              @foreach ($npcRaces as $race)
                                  <tr>
                                      <td>
                                          <a href="{{ route('scribes.faction', $race['name']) }}">{{ $race['name'] }}</a>
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
