@extends('layouts.topnav')
@section('title', "Scribes | Advancements")

@section('content')
@include('partials.scribes.nav')
    <div class="box box-primary">
        <div class="box-header with-border">
            <h3 class="box-title">Advancements</h3>
        </div>
        <div class="box-body">
            <div class="row">
                <div class="col-md-12">
                    <p>Advancements give benefits to your dominion. There are {{ count($advancements) }} Advancements, each one having 10 levels. Levels 1 through 6 have the same increment; levels 7 and 8 have half the originel increment and levels 9 and 10 are halved again.</p>
                    <p>You level up Advancements by spending XP, which is generated each tick based on your prestige. You can also earn XP from invasions, expeditions, and by successuflly performing hostile spy ops and casting hostile spells on other dominions.</p>
                    <p>The cost of levelling up Advancements is based on your land size.</p>
                    @foreach($advancements as $advancement)
                        <a href="#{{ $advancement->name }}">{{ $advancement->name }}</a> |
                    @endforeach

                </div>
            </div>
        </div>
    </div>
    @foreach ($advancements as $advancement)
        <div class="box">
            <div class="box-header with-border">
                <a id="{{ $advancement->name }}"></a><h3 class="box-title">{{ $advancement->name }}</h3>

                @if($advancementHelper->hasExclusivity($advancement))
                    {!! $advancementHelper->getExclusivityString($advancement) !!}
                @endif
            </div>
            <div class="row">
                <div class="box-body">
                    <ul>
                        @foreach($advancementHelper->getAdvancementPerksString($advancement) as $effect)
                            <li>{{ ucfirst($effect) }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endforeach
@endsection
