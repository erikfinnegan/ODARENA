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
                    <p>Advancements give benefits to your dominion. There are {{ count($techNames) }} Advancements, each one having 10 levels. Levels 1 through 6 have the same increment; levels 7 and 8 have half the originel increment and levels 9 and 10 are halved again.</p>
                    <p>You level up Advancements by spending XP, which is generated each tick based on your prestige. You can also earn XP from invasions, exploring, and by successuflly performing hostile spy ops and casting hostile spells on other dominions.</p>
                    <p>The cost of levelling up Advancements is based on your land size. For every level, the cost is increased by 10%.</p>
                    @foreach($techNames as $techName)
                        <a href="#{{ $techName }}">{{ $techName }}</a> |
                    @endforeach

                </div>
            </div>
        </div>
    </div>
    @foreach ($techs as $tech)
        @if($tech->level == 1)
        <a id="{{ $tech->name }}"></a>
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">{{ $tech->name }}</h3>
            </div>
            <div class="box-body table-responsive">
                <table class="table">
                    <tr>
        @endif

        <td>
            <strong>Level {{ $tech->level }}</strong><br>
            {{ $techHelper->getTechDescription($tech) }}
        </td>

        @if($tech->level == 10)
                    </tr>
                </table>
            </div>
        </div>
        @endif
    @endforeach
@endsection
