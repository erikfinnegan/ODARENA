@extends('layouts.topnav')

@section('content')
    @include('partials.scribes.nav')
    <div class="box box-primary">
        <div class="box-header with-border">
            <h3 class="box-title">{{ $race->name }}</h3>
        </div>
        <div class="box-body">
            <div class="row">
                <div class="col-md-12 col-md-9">

                    <div class="row">
                        <div class="col-md-12 col-md-12">

                        @if($race->description)
                            <h4 style="border-bottom: 1px solid #f4f4f4; margin-top: 0; padding: 10px 0">Important</h4>
                            {!! $race->description !!}
                        @endif

                        @php
                            $factionUrlName = str_replace(' ','-',strtolower($race->name));
                            $alignments = ['good' => 'commonwealth', 'evil' => 'empire', 'independent' => 'independent', 'npc' => 'barbarian-horde'];
                            $alignment = $alignments[$race->alignment];
                        @endphp
                            <h4 style="border-bottom: 1px solid #f4f4f4; margin-top: 0; padding: 10px 0"> Chronicles</h4>
                            <p><a href="https://lounge.odarena.com/chronicles/factions/{{ $alignment }}/{{ $factionUrlName }}/" target="_blank"><i class="fa fa-book"></i> Click here to read the history and lore of {{ $race->name }} in the Chronicles.</a></p>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12 col-md-3">
                            {{-- Home land --}}
                            <h4 style="border-bottom: 1px solid #f4f4f4; margin-top: 0; padding: 10px 0">Home land</h4>
                            <p>
                                {!! $landHelper->getLandTypeIconHtml($race->home_land_type) !!} {{ ucfirst($race->home_land_type) }}
                            </p>
                        </div>
                        <div class="col-md-12 col-md-3">
                            {{-- Home land --}}
                            <h4 style="border-bottom: 1px solid #f4f4f4; margin-top: 0; padding: 10px 0">Construction Materials</h4>
                            <p>
                                @php
                                    $constructionMaterials = $raceHelper->getConstructionMaterials($race);
                                    $materials = count($constructionMaterials);
                                @endphp

                                @if($race->getPerkValue('cannot_construct'))
                                    <em>Cannot construct buildings.</em>
                                @elseif($materials === 1)
                                    <b>Resource</b>: {{ ucwords($constructionMaterials[0]) }}<br>
                                @else
                                    <b>Primary</b>: {{ ucwords($constructionMaterials[0]) }}<br>
                                    <b>Secondary</b>: {{ ucwords($constructionMaterials[1]) }}
                                @endif
                            </p>
                        </div>
                        <div class="col-md-12 col-md-6">
                            {{-- Racial Spell --}}
                            <h4 style="border-bottom: 1px solid #f4f4f4; margin-top: 0; padding: 10px 0">Unique Spell</h4>
                            @php
                                $racialSpell = $spellHelper->getRacialSelfSpellForScribes($race);
                            @endphp

                            <p>
                                <strong>{{ $racialSpell['name'] }}</strong>: {{ $racialSpell['description'] }}<br>
                                <strong>Cost:</strong> {{ $racialSpell['mana_cost']}}x<br>
                                <strong>Duration:</strong> {{ $racialSpell['duration']}} ticks<br>
                                @if(isset($racialSpell['cooldown']))
                                  <strong>Cooldown:</strong> {{ $racialSpell['cooldown']}} hours<br>
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-12 col-md-3">
                    <table class="table table-striped">
                        <colgroup>
                            <col>
                            <col width="50px">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>{{ $race->name }} Perks</th>
                                <th>Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($race->perks as $perk)
                                @php
                                    $perkDescription = $raceHelper->getPerkDescriptionHtmlWithValue($perk);
                                @endphp
                                <tr>
                                    <td>
                                        {!! $perkDescription['description'] !!}
                                    </td>
                                    <td class="text-center">
                                        {!! $perkDescription['value']  !!}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12">
                    {{-- Military Units --}}
                    <h4 style="border-bottom: 1px solid #f4f4f4; margin-top: 0; padding: 10px 0">Military Units</h4>

                    <table class="table table-striped">
                        <colgroup>
                            <col width="150px">
                            <col width="50px">
                            <col width="50px">
                            <col>
                            <col width="125px">
                            <col width="100px">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>Unit</th>
                                <th class="text-center">OP</th>
                                <th class="text-center">DP</th>
                                <th>Special Abilities</th>
                                <th>Attributes</th>
                                <th class="text-center">Cost</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($race->units as $unit)
                                @php
                                    $unitCostString = (number_format($unit->cost_platinum) . ' platinum');

                                    if ($unit->cost_ore > 0) {
                                        $unitCostString .= (', ' . number_format($unit->cost_ore) . ' ore');
                                    }

                                    if ($unit->cost_lumber > 0) {
                                        $unitCostString .= (', ' . number_format($unit->cost_lumber) . ' lumber');
                                    }

                                    if ($unit->cost_food > 0) {
                                        $unitCostString .= (', ' . number_format($unit->cost_food) . ' food');
                                    }

                                    if ($unit->cost_mana > 0) {
                                        $unitCostString .= (', ' . number_format($unit->cost_mana) . ' mana');
                                    }

                                    if ($unit->cost_gem > 0) {
                                        $unitCostString .= (', ' . number_format($unit->cost_gem) . ' gem');
                                    }

                                    if ($unit->cost_prestige > 0) {
                                        $unitCostString .= (', ' . number_format($unit->cost_prestige) . ' Prestige');
                                    }

                                    if ($unit->cost_boat > 0) {
                                        $unitCostString .= (', ' . number_format($unit->cost_boat) . ' boat');
                                    }

                                    if ($unit->cost_champion > 0) {
                                        $unitCostString .= (', ' . number_format($unit->cost_champion) . ' Champion');
                                    }

                                    if ($unit->cost_soul > 0) {
                                        $unitCostString .= (', ' . number_format($unit->cost_soul) . ' Soul');
                                    }

                                    if ($unit->cost_blood > 0) {
                                        $unitCostString .= (', ' . number_format($unit->cost_blood) . ' blood');
                                    }

                                    if ($unit->cost_unit1 > 0) {
                                        $unitCostString .= (', ' . number_format($unit->cost_unit1) . ' ' . $unitHelper->getUnitName('unit1', $race));
                                    }

                                    if ($unit->cost_unit2 > 0) {
                                        $unitCostString .= (', ' . number_format($unit->cost_unit2) . ' ' . $unitHelper->getUnitName('unit2', $race));
                                    }

                                    if ($unit->cost_unit3 > 0) {
                                        $unitCostString .= (', ' . number_format($unit->cost_unit3) . ' ' . $unitHelper->getUnitName('unit3', $race));
                                    }

                                    if ($unit->cost_unit4 > 0) {
                                        $unitCostString .= (', ' . number_format($unit->cost_unit4) . ' ' . $unitHelper->getUnitName('unit4', $race));
                                    }

                                    if ($unit->cost_morale > 0) {
                                        $unitCostString .= (', ' . number_format($unit->cost_morale) . '% morale');
                                    }

                                    if ($unit->cost_peasant > 0) {
                                        $unitCostString .= (', ' . number_format($unit->cost_peasant) . ' peasant');
                                    }

                                    if ($unit->cost_wild_yeti > 0) {
                                        $unitCostString .= (', ' . number_format($unit->cost_wild_yeti) . '  wild yeti');
                                    }

                                    if ($unit->cost_spy > 0) {
                                        $unitCostString .= (', ' . number_format($unit->cost_spy) . '  Spy');
                                    }

                                    if ($unit->cost_wizard > 0) {
                                        $unitCostString .= (', ' . number_format($unit->cost_wizard) . '  Wizard');
                                    }

                                    if ($unit->cost_archmage > 0) {
                                        $unitCostString .= (', ' . number_format($unit->cost_archmage) . '  ArchMage');
                                    }

                                @endphp
                                <tr>
                                    <td>
                                        {{ $unit->name }}
                                    </td>
                                    <td class="text-center">
                                        {{ number_format($unit->power_offense,2) }}
                                    </td>
                                    <td class="text-center">
                                        {{ number_format($unit->power_defense,2) }}
                                    </td>
                                    <td>
                                        {!! $unitHelper->getUnitHelpString("unit{$unit->slot}", $race) !!}
                                    </td>
                                    <td>
                                        {!! $unitHelper->getUnitAttributesList("unit{$unit->slot}", $race) !!}
                                    </td>
                                    <td class="text-center">
                                        {{ $unitCostString }}
                                    </td>
                                </tr>
                            @endforeach
                            <tr>
                                <td>Spies</td>
                                @php
                                    $spyCost = $raceHelper->getSpyCost($race);
                                @endphp
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td class="text-center">{{ number_format($spyCost['amount']) . ' ' . $spyCost['resource'] }}</td>
                            </tr>
                            <tr>
                                <td>Wizards</td>
                                @php
                                    $wizCost = $raceHelper->getWizardCost($race);
                                @endphp
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td class="text-center">{{ number_format($wizCost['amount']) . ' ' . $wizCost['resource'] }}</td>
                            </tr>
                            <tr>
                                <td>Archmages</td>
                                @php
                                    $archmageCost = $raceHelper->getArchmageCost($race);
                                @endphp
                                <td></td>
                                <td></td>
                                <td>
                                    <ul>
                                        <li>Twice as powerful as a wizard.</li>
                                        <li>Immortal. Does not die on any failed spells and cannot be assassinated.</li>
                                    </ul>
                                </td>
                                <td></td>
                                <td class="text-center">{{ number_format($archmageCost['amount']) . ' ' . $archmageCost['resource'] }}, 1 Wizard</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
