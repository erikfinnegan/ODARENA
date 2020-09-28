@extends('layouts.topnav')

@section('content')
    @include('partials.scribes.nav')
    <div class="box box-primary">
        <div class="box-header with-border">
            <h3 class="box-title">Advancements</h3>
        </div>
        <div class="box-body">
            <div class="row">
                <div class="col-md-12">
                    <p>You can level up Advancements in your dominion by spending XP. You earn XP every tick based on your prestige. You can also earn XP from invasions, exploring, and performing certain spy ops and casting certain spells on other dominions.</p>
                    <p>The cost of levelling up Advancements is based on your land size.</p>
                    <p>Each Advancement has eight levels. For every level, the cost is increased by 10%.</p>
                </div>
            </div>
        </div>
    </div>
    <div class="box">
        <div class="box-header with-border">
            <h3 class="box-title">Advancements</h3>
        </div>
        <div class="box-body table-responsive">
            <div class="row">
                <div class="col-md-12">
                  <table class="table table-striped">
                      <colgroup>
                          <col width="200">
                      </colgroup>
                      <thead>
                          <tr>
                              <th>Advancement</th>
                              @for ($i = 1; $i <= 10; $i++)
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
                              {{ $techHelper->getTechDescription($tech) }}
                          </td>

                          @if($tech->level == 10)
                              </tr>
                          @endif
                      @endforeach
                  </table>
                </div>
            </div>
        </div>
    </div>
@endsection
