@extends('layouts.topnav')

@section('content')
    <div class="box box-primary">
        <div class="box-header with-border">
            <h1 class="box-title"><i class="ra ra-angel-wings"></i> Valhalla for round {{ number_format($round->number) }}: {{ $round->name }}</h1>
        </div>
        <div class="box-body">

            <div class="row">
                <div class="col-md-12 text-center">
                    <h3>Overall</h3>
                </div>
            </div>
            <div class="row form-group">
                <div class="col-sm-6 text-center">
                    <h4>Largest</h4>
                    <a href="{{ route('valhalla.round.type', [$round, 'largest-dominions']) }}">The Largest Dominions</a><br>
                    <a href="{{ route('valhalla.round.type', [$round, 'largest-realms']) }}">The Largest Realms</a><br>
                </div>
                <div class="col-sm-6 text-center">
                    <h4>Strongest</h4>
                    <a href="{{ route('valhalla.round.type', [$round, 'strongest-dominions']) }}">The Strongest Dominions</a><br>
                    <a href="{{ route('valhalla.round.type', [$round, 'strongest-realms']) }}">The Strongest Realms</a><br>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12 text-center">
                    <h3>Military</h3>
                </div>
            </div>
            <div class="row form-group">
                <div class="col-sm-12 text-center">
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-total-land-conquered']) }}">Largest Attacking Dominions<br>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-attacking-success']) }}">Most Victorious Dominions</a><br>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-total-land-explored']) }}">Largest Exploring Dominions<br>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-prestige']) }}">Most Prestigious Dominions</a><br>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-total-units-killed']) }}">Most Units Killed</a><br>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-total-units-converted']) }}">Most Units Converted</a><br>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-total-land-lost']) }}">Most Land Lost</a><br>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12 text-center">
                    <h3>Spies and Wizards</h3>
                </div>
            </div>
            <div class="row form-group">
                <div class="col-sm-6 text-center">
                    <h4>Spies</h4>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-espionage-success']) }}">Most Successful Spies</a><br>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-total-platinum-stolen']) }}">Top Platinum Thieves</a><br>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-total-food-stolen']) }}">Top Food Thieves</a><br>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-total-lumber-stolen']) }}">Top Lumber Thieves</a><br>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-total-mana-stolen']) }}">Top Mana Thieves</a><br>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-total-ore-stolen']) }}">Top Ore Thieves</a><br>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-total-gems-stolen']) }}">Top Gem Thieves</a><br>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-top-saboteurs']) }}">Top Saboteurs</a><br>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-top-magical-assassins']) }}">Top Magical Assassins</a><br>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-top-military-assassins']) }}">Top Military Assassins</a><br>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-top-snare-setters']) }}">Top Snare Setters</a><br>
                    <!-- Top Demoralizers -->
                </div>
                <div class="col-sm-6 text-center">
                    <h4>Wizards</h4>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-spell-success']) }}">Most Successful Wizards</a><br>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-masters-of-fire']) }}">Masters of Fire</a><br>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-masters-of-plague']) }}">Masters of Plague</a><br>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-masters-of-swarm']) }}">Masters of Swarm</a><br>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-masters-of-lightning']) }}">Masters of Lightning</a><br>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-masters-of-water']) }}">Masters of Water</a><br>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-masters-of-earth']) }}">Masters of Earth</a><br>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-top-spy-disbanders']) }}">Top Spy Disbanders</a><br>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12 text-center">
                    <h3>Production and Spending</h3>
                </div>
            </div>
            <div class="row form-group text-center">
                <div class="col-sm-4 text-center">
                    <h4>Platinum</h4>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-total-platinum-spent-training']) }}">Most Spent Training</a><br>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-total-platinum-spent-building']) }}">Most Spent Building</a><br>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-total-platinum-spent-rezoning']) }}">Most Spent Rezoning</a><br>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-total-platinum-spent-exploring']) }}">Most Spent Exploring</a><br>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-total-platinum-spent-improving']) }}">Most Spent Improving</a><br>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-total-platinum-plundered']) }}">Most Plundered</a><br>
                    {{--<a href="{{ route('valhalla.round.type', [$round, 'stat-total-platinum-salvaged']) }}">Most Salvaged</a><br>--}}
                </div>
                <div class="col-sm-4 text-center">
                    <h4>Food</h4>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-total-food-spent-training']) }}">Most Spent Training</a><br>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-total-food-spent-building']) }}">Most Spent Building</a><br>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-total-food-spent-rezoning']) }}">Most Spent Rezoning</a><br>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-total-food-spent-exploring']) }}">Most Spent Exploring</a><br>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-total-food-spent-improving']) }}">Most Spent Improving</a><br>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-total-food-plundered']) }}">Most Plundered</a><br>
                    {{--<a href="{{ route('valhalla.round.type', [$round, 'stat-total-food-salvaged']) }}">Most Salvaged</a><br>--}}
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-total-food-decayed']) }}">Most Decayed</a><br>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-total-food-consumed']) }}">Most Consumed</a><br>
                </div>
                <div class="col-sm-4 text-center">
                    <h4>Ore</h4>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-total-ore-spent-training']) }}">Most Spent Training</a><br>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-total-ore-spent-building']) }}">Most Spent Building</a><br>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-total-ore-spent-rezoning']) }}">Most Spent Rezoning</a><br>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-total-ore-spent-exploring']) }}">Most Spent Exploring</a><br>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-total-ore-spent-improving']) }}">Most Spent Improving</a><br>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-total-ore-plundered']) }}">Most Plundered</a><br>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-total-ore-salvaged']) }}">Most Salvaged</a><br>
                </div>


                </div>
                <div class="row form-group text-center">

                <div class="col-sm-4 text-center">
                    <h4>Lumber</h4>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-total-lumber-spent-training']) }}">Most Spent Training</a><br>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-total-lumber-spent-building']) }}">Most Spent Building</a><br>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-total-lumber-spent-rezoning']) }}">Most Spent Rezoning</a><br>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-total-lumber-spent-exploring']) }}">Most Spent Exploring</a><br>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-total-lumber-spent-improving']) }}">Most Spent Improving</a><br>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-total-lumber-plundered']) }}">Most Plundered</a><br>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-total-lumber-salvaged']) }}">Most Salvaged</a><br>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-total-lumber-rotted']) }}">Most Rotted</a><br>
                </div>
                <div class="col-sm-4 text-center">
                    <h4>Gems</h4>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-total-gem-spent-training']) }}">Most Spent Training</a><br>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-total-gem-spent-building']) }}">Most Spent Building</a><br>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-total-gem-spent-rezoning']) }}">Most Spent Rezoning</a><br>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-total-gem-spent-exploring']) }}">Most Spent Exploring</a><br>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-total-gem-spent-improving']) }}">Most Spent Improving</a><br>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-total-gem-plundered']) }}">Most Plundered</a><br>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-total-gem-salvaged']) }}">Most Salvaged</a><br>
                </div>
                <div class="col-sm-4 text-center">
                    <h4>Mana</h4>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-total-mana-spent-training']) }}">Most Spent Training</a><br>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-total-mana-spent-building']) }}">Most Spent Building</a><br>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-total-mana-spent-rezoning']) }}">Most Spent Rezoning</a><br>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-total-mana-spent-exploring']) }}">Most Spent Exploring</a><br>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-total-mana-spent-improving']) }}">Most Spent Improving</a><br>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-total-mana-plundered']) }}">Most Plundered</a><br>
                    {{--<a href="{{ route('valhalla.round.type', [$round, 'stat-total-mana-salvaged']) }}">Most Salvaged</a><br>--}}
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-total-mana-drained']) }}">Most Drained</a><br>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-total-mana-cast']) }}">Most Cast</a><br>
                </div>


            </div>
            <div class="row form-group text-center">
                <div class="col-sm-6 text-center">
                    <h4>Souls</h4>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-total-soul-spent-training']) }}">Most Spent Training</a><br>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-total-soul-spent-improving']) }}">Most Spent Improving</a><br>
                </div>

                <div class="col-sm-6 text-center">
                    <h4>Legendary Champions</h4>
                    <a href="{{ route('valhalla.round.type', [$round, 'stat-total-champion-spent-training']) }}">Most Spent Training</a><br>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12 text-center">
                    <h3>Factions</h3>
                </div>
            </div>
            <div class="row form-group">
                <div class="col-sm-6 text-center">
                    <h4>Strongest</h4>
                    @foreach ($races as $race)
                        @php $raceSlug = 'strongest-' . str_slug($race); @endphp
                        <a href="{{ route('valhalla.round.type', [$round, $raceSlug]) }}">The Strongest {{ str_plural($race) }}</a><br>
                    @endforeach
                </div>
                <div class="col-sm-6 text-center">
                    <h4>Largest</h4>
                    @foreach ($races as $race)
                        @php $raceSlug = 'largest-' . str_slug($race); @endphp
                        <a href="{{ route('valhalla.round.type', [$round, $raceSlug]) }}">The Largest {{ str_plural($race) }}</a><br>
                    @endforeach
                </div>
            </div>

        </div>
    </div>
@endsection
