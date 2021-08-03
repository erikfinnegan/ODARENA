@extends('layouts.topnav')

@section('content')
    @include('partials.scribes.nav')
<div class="row">

    <div class="col-sm-12 col-md-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h2 class="box-title">{{ $race->name }}</h2>

                @if($race->experimental)
                    <span class="label label-danger">Experimental</span>
                @endif

                <span>
                    <a href="#units">Units</a> |
                    <a href="#buildings">Buildings</a> |
                    <a href="#improvements">Improvements</a> |
                    <a href="#spells">Spells</a> |
                    <a href="#spy_ops">Spy Ops</a>
                </span>

                <span class="pull-right">
                    Skill level:
                    @if($race->skill_level === 1)
                        <span class="label label-success">Beginner</span>
                    @elseif($race->skill_level === 2)
                        <span class="label label-warning">Intermediate</span>
                    @elseif($race->skill_level === 3)
                        <span class="label label-danger">Advanced</span>
                    @endif

                    |

                    Attacking:
                    @if($race->attacking === 0)
                        <i class="fa fa-star text-gray"></i><i class="fa fa-star text-gray"></i><i class="fa fa-star text-gray"></i>
                    @elseif($race->attacking === 1)
                        <i class="fa fa-star text-yellow"></i><i class="fa fa-star text-gray"></i><i class="fa fa-star text-gray"></i>
                    @elseif($race->attacking === 2)
                        <i class="fa fa-star text-yellow"></i><i class="fa fa-star text-yellow"></i><i class="fa fa-star text-gray"></i>
                    @elseif($race->attacking === 3)
                        <i class="fa fa-star text-yellow"></i><i class="fa fa-star text-yellow"></i><i class="fa fa-star text-yellow"></i>
                    @endif

                    |

                    Converting:
                    @if($race->converting === 0)
                        <i class="fa fa-star text-gray"></i><i class="fa fa-star text-gray"></i><i class="fa fa-star text-gray"></i>
                    @elseif($race->converting === 1)
                        <i class="fa fa-star text-yellow"></i><i class="fa fa-star text-gray"></i><i class="fa fa-star text-gray"></i>
                    @elseif($race->converting === 2)
                        <i class="fa fa-star text-yellow"></i><i class="fa fa-star text-yellow"></i><i class="fa fa-star text-gray"></i>
                    @elseif($race->converting === 3)
                        <i class="fa fa-star text-yellow"></i><i class="fa fa-star text-yellow"></i><i class="fa fa-star text-yellow"></i>
                    @endif

                    |

                    Exploring:
                    @if($race->exploring === 0)
                        <i class="fa fa-star text-gray"></i><i class="fa fa-star text-gray"></i><i class="fa fa-star text-gray"></i>
                    @elseif($race->exploring === 1)
                        <i class="fa fa-star text-yellow"></i><i class="fa fa-star text-gray"></i><i class="fa fa-star text-gray"></i>
                    @elseif($race->exploring === 2)
                        <i class="fa fa-star text-yellow"></i><i class="fa fa-star text-yellow"></i><i class="fa fa-star text-gray"></i>
                    @elseif($race->exploring === 3)
                        <i class="fa fa-star text-yellow"></i><i class="fa fa-star text-yellow"></i><i class="fa fa-star text-yellow"></i>
                    @endif

                </span>
            </div>
            <div>


            </div>
            @if($race->description)
                <div class="box-body">
                {!! $race->description !!}
                </div>
            @endif
        </div>
    </div>
</div>
<div class="row">

    <a id="units"></a>
    <div class="col-sm-12 col-md-9">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Units</h3>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table">
                    <colgroup>
                        <col width="100">
                        <col width="100">
                        <col>
                        <col>
                        <col width="150">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Unit</th>
                            <th class="text-center">OP / DP</th>
                            <th>Special Abilities</th>
                            <th>Attributes</th>
                            <th>Cost</th>
                        </tr>
                    </thead>
                    <tbody>
                      @foreach ($race->units as $unit)
                          @if(in_array($unit->slot, ['wizards','spies','archmages']))
                              @php
                                  $unitType = $unit->slot;
                              @endphp
                          @else
                              @php
                                  $unitType = 'unit' . $unit->slot;
                              @endphp
                          @endif
                          <tr>
                              <td>
                                  <span data-toggle="tooltip" data-placement="top" title="{{ $unitHelper->getUnitHelpString($unitType, $race) }}">
                                      {{ $unitHelper->getUnitName($unitType, $race) }}
                                  </span>
                              </td>
                                @if (in_array($unitType, ['unit1', 'unit2', 'unit3', 'unit4']))
                                    <td class="text-center">  <!-- OP / DP -->
                                        {{ $unit->power_offense }} / {{ $unit->power_defense }}
                                    </td>
                                @else
                                    <td class="text-center">&mdash;</td>
                                    <td class="text-center">  <!-- If Spy/Wiz/AM --></td>
                                @endif

                              <td>
                                  {!! $unitHelper->getUnitHelpString("unit{$unit->slot}", $race) !!}
                              </td>
                              <td>
                                  {!! $unitHelper->getUnitAttributesList("unit{$unit->slot}", $race) !!}
                              </td>

                              <td>  <!-- Cost -->
                                    @if($race->getUnitPerkValueForUnitSlot($unit->slot,'cannot_be_trained'))
                                        &mdash;
                                    @else
                                        @php
                                            $unitCostString = (number_format($unit->cost_gold) . ' gold');

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

                                            if ($unit->cost_morale !== 0) {
                                                $unitCostString .= (', ' . number_format($unit->cost_morale) . '% morale');
                                            }

                                            if ($unit->cost_peasant > 0) {
                                                $unitCostString .= (', ' . number_format($unit->cost_peasant) . ' peasant');
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
                                        {{ $unitCostString }}
                                    @endif
                              </td>
                          </tr>
                      @endforeach

                    </tbody>
                </table>
            </div>
        </div>
      </div>

      <a id="perks"></a>
      <div class="col-sm-12 col-md-3 no-padding">
          <div class="col-sm-12 col-md-12">
              <div class="box">
                  <div class="box-header with-border">
                      <h3 class="box-title">Perks</h3>
                  </div>
                  <div class="box-body table-responsive no-padding">
                      <table class="table table-striped">
                          <colgroup>
                              <col>
                              <col>
                          </colgroup>
                          <tbody>
                              @foreach ($race->perks->sort() as $perk)
                                  @php
                                      $perkDescription = $raceHelper->getPerkDescriptionHtmlWithValue($perk);
                                  @endphp
                                  <tr>
                                      <td>
                                          {!! $perkDescription['description'] !!}
                                      </td>
                                      <td>
                                          {!! $perkDescription['value']  !!}
                                      </td>
                                  </tr>
                              @endforeach
                          </tbody>
                      </table>
                  </div>
              </div>
          </div>
          <a id="lands"></a>
          <div class="col-sm-12 col-md-12">
              <div class="box">
                  <div class="box-header with-border">
                      <h3 class="box-title">Resources</h3>
                  </div>
                  <div class="box-body table-responsive no-padding">
                      <table class="table table-striped">
                          <colgroup>
                              <col>
                              <col>
                          </colgroup>
                          <tbody>
                              <tr>
                                  <td>Home land type:</td>
                                  <td>{{ ucwords($race->home_land_type) }} {!! $landHelper->getLandTypeIconHtml($race->home_land_type) !!}<td>
                              </tr>

                              <tr>
                                  <td colspan="2" class="text-center"><b>Improvements</b></th>
                              </tr>
                              @foreach($race->improvement_resources as $resource => $value)
                                  <tr>
                                      <td>{{ ucwords($resource) }}:</td>
                                      <td>{{ number_format($value,2) . ' ' . str_plural('points', $value) }}</td>
                                  </tr>
                              @endforeach

                              <tr>
                                  <td colspan="2" class="text-center"><b>Construction</b></th>
                              </tr>
                              @if($race->getPerkValue('cannot_construct'))
                                  <tr>
                                      <td colspan="2" class="text-center">Cannot construct buildings.</td>
                                  </tr>
                              @elseif(count($race->construction_materials) === 1)
                                  <tr>
                                      <td>Resource:</td>
                                      <td>{{ ucwords($race->construction_materials[0]) }}<td>
                                  </tr>
                              @else
                                  <tr>
                                      <td>Primary:</td>
                                      <td>{{ ucwords($race->construction_materials[0]) }}<td>
                                  </tr>
                                  <tr>
                                      <td>Secondary:</td>
                                      <td>{{ ucwords($race->construction_materials[1]) }}<td>
                                  </tr>
                              @endif
                          </tbody>
                      </table>
                  </div>
              </div>
          </div>

      </div>
</div>


<a id="buildings"></a>
<div class="row">
    <div class="col-sm-12 col-md-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Buildings</h3>
            </div>

            <div class="box-body table-responsive">
                <div class="row">
                    <div class="col-md-12">
                      <table class="table table-striped">
                          <colgroup>
                              <col width="200">
                              <col width="100">
                          </colgroup>
                          <thead>
                              <tr>
                                  <th>Building</th>
                                  <th>Land Type</th>
                                  <th>Perks</th>
                              </tr>
                          </thead>
                          @foreach ($buildings as $building)
                              <tr>
                                  <td>{{ $building->name }}</td>
                                  <td>{{ ucwords($building->land_type) }}</td>
                                  <td>{!! $buildingHelper->getBuildingDescription($building) !!}</td>
                              </tr>
                          @endforeach
                      </table>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>


<a id="improvements"></a>
<div class="row">
    <div class="col-sm-12 col-md-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Improvements</h3>
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
                                  <th>Building</th>
                                  <th>Perks</th>
                              </tr>
                          </thead>
                          @foreach ($improvements as $improvement)
                              <tr>
                                  <td>
                                      {{ $improvement->name }}
                                  </td>
                                  <td>
                                      <table>
                                          <colgroup>
                                              <col width="180">
                                              <col width="80">
                                              <col width="100">
                                          </colgroup>
                                          <thead>
                                              <tr>
                                                  <td><u>Perk</u></td>
                                                  <td><u>Max</u></td>
                                                  <td><u>Coefficient</u></td>
                                              </tr>
                                      @foreach($improvement->perks as $perk)
                                          @php
                                              $improvementPerkMax = number_format($improvementHelper->extractImprovementPerkValuesForScribes($perk->pivot->value)[0]);
                                              $improvementPerkCoefficient = number_format($improvementHelper->extractImprovementPerkValuesForScribes($perk->pivot->value)[1]);
                                              if($improvementPerkMax > 0)
                                              {
                                                  $improvementPerkMax = '+' . $improvementPerkMax;
                                              }
                                          @endphp
                                          <tr>
                                              <td>{{ ucwords($improvementHelper->getImprovementPerkDescription($perk->key)) }}</td>
                                              <td>{{ $improvementPerkMax }}%</td>
                                              <td>{{ $improvementPerkCoefficient }}</td>
                                          <tr>
                                      @endforeach
                                      </table>
                                  </td>
                              </tr>
                          @endforeach
                      </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<div class="row">
    <a id="spells"></a>
    <div class="col-sm-12 col-md-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Spells</h3>
            </div>
            <div class="box-body">
                <h4 class="box-title">Friendly Auras</h4>
                <table class="table table-striped">
                    <colgroup>
                        <col width="200">
                        <col width="50">
                        <col width="50">
                        <col width="50">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Spell</th>
                            <th>Cost</th>
                            <th>Duration</th>
                            <th>Cooldown</th>
                            <th>Effect</th>
                        </tr>
                    <tbody>
                    @foreach ($spells as $spell)
                        @if($spell->class == 'passive' and $spell->scope == 'friendly' and $spellCalculator->isSpellAvailableToRace($race, $spell))
                        <tr>
                            <td>{{ $spell->name }}</td>
                            <td>{{ $spell->cost }}x</td>
                            <td>{{ $spell->duration }} ticks</td>
                            <td>
                                @if($spell->cooldown > 0)
                                    {{ $spell->cooldown }} hours
                                @else
                                    None
                                @endif
                            </td>
                            <td>
                                <ul>
                                    @foreach($spellHelper->getSpellEffectsString($spell) as $effect)
                                        <li>{{ ucfirst($effect) }}</li>
                                    @endforeach
                                <ul>
                            </td>
                        </tr>
                        @endif
                    @endforeach
                    </tbody>
                </table>

                <h4 class="box-title">Hostile Auras</h4>
                <table class="table table-striped">
                    <colgroup>
                        <col width="200">
                        <col width="50">
                        <col width="50">
                        <col width="50">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Spell</th>
                            <th>Cost</th>
                            <th>Duration</th>
                            <th>Cooldown</th>
                            <th>Effect</th>
                        </tr>
                    <tbody>
                    @foreach ($spells as $spell)
                        @if($spell->class == 'passive' and $spell->scope == 'hostile' and $spellCalculator->isSpellAvailableToRace($race, $spell))
                        <tr>
                            <td>{{ $spell->name }}</td>
                            <td>{{ $spell->cost }}x</td>
                            <td>{{ $spell->duration }} ticks</td>
                            <td>
                                @if($spell->cooldown > 0)
                                    {{ $spell->cooldown }} hours
                                @else
                                    None
                                @endif
                            </td>
                            <td>
                                <ul>
                                    @foreach($spellHelper->getSpellEffectsString($spell) as $effect)
                                        <li>{{ ucfirst($effect) }}</li>
                                    @endforeach
                                <ul>
                            </td>
                        </tr>
                        @endif
                    @endforeach
                    </tbody>
                </table>

                <h4 class="box-title">Self Auras</h4>
                <table class="table table-striped">
                    <colgroup>
                        <col width="200">
                        <col width="50">
                        <col width="50">
                        <col width="50">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Spell</th>
                            <th>Cost</th>
                            <th>Duration</th>
                            <th>Cooldown</th>
                            <th>Effect</th>
                        </tr>
                    <tbody>
                    @foreach ($spells as $spell)
                        @if($spell->class == 'passive' and $spell->scope == 'self' and $spellCalculator->isSpellAvailableToRace($race, $spell))
                        <tr>
                            <td>{{ $spell->name }}</td>
                            <td>{{ $spell->cost }}x</td>
                            <td>{{ $spell->duration }} ticks</td>
                            <td>
                                @if($spell->cooldown > 0)
                                    {{ $spell->cooldown }} hours
                                @else
                                    None
                                @endif
                            </td>
                            <td>
                                <ul>
                                    @foreach($spellHelper->getSpellEffectsString($spell) as $effect)
                                        <li>{{ ucfirst($effect) }}</li>
                                    @endforeach
                                <ul>
                            </td>
                        </tr>
                        @endif
                    @endforeach
                    </tbody>
                </table>


                <h4 class="box-title">Friendly Impact Spells</h4>
                <table class="table table-striped">
                    <colgroup>
                        <col width="200">
                        <col width="50">
                        <col width="50">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Spell</th>
                            <th>Cost</th>
                            <th>Cooldown</th>
                            <th>Effect</th>
                        </tr>
                    <tbody>
                    @foreach ($spells as $spell)
                        @if($spell->class == 'active' and $spell->scope == 'friendly' and $spellCalculator->isSpellAvailableToRace($race, $spell))
                        <tr>
                            <td>{{ $spell->name }}</td>
                            <td>{{ $spell->cost }}x</td>
                            <td>
                                @if($spell->cooldown > 0)
                                    {{ $spell->cooldown }} hours
                                @else
                                    None
                                @endif
                            </td>
                            <td>
                                <ul>
                                    @foreach($spellHelper->getSpellEffectsString($spell) as $effect)
                                        <li>{{ ucfirst($effect) }}</li>
                                    @endforeach
                                <ul>
                            </td>
                        </tr>
                        @endif
                    @endforeach
                    </tbody>
                </table>

                <h4 class="box-title">Hostile Impact Spells</h4>
                <table class="table table-striped">
                    <colgroup>
                        <col width="200">
                        <col width="50">
                        <col width="50">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Spell</th>
                            <th>Cost</th>
                            <th>Cooldown</th>
                            <th>Effect</th>
                        </tr>
                    <tbody>
                    @foreach ($spells as $spell)
                        @if($spell->class == 'active' and $spell->scope == 'hostile' and $spellCalculator->isSpellAvailableToRace($race, $spell))
                        <tr>
                            <td>{{ $spell->name }}</td>
                            <td>{{ $spell->cost }}x</td>
                            <td>
                                @if($spell->cooldown > 0)
                                    {{ $spell->cooldown }} hours
                                @else
                                    None
                                @endif
                            </td>
                            <td>
                                <ul>
                                    @foreach($spellHelper->getSpellEffectsString($spell) as $effect)
                                        <li>{{ ucfirst($effect) }}</li>
                                    @endforeach
                                <ul>
                            </td>
                        </tr>
                        @endif
                    @endforeach
                    </tbody>
                </table>

                <h4 class="box-title">Self Impact Spells</h4>
                <table class="table table-striped">
                    <colgroup>
                        <col width="200">
                        <col width="50">
                        <col width="50">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Spell</th>
                            <th>Cost</th>
                            <th>Cooldown</th>
                            <th>Effect</th>
                        </tr>
                    <tbody>
                    @foreach ($spells as $spell)
                        @if($spell->class == 'active' and $spell->scope == 'self' and $spellCalculator->isSpellAvailableToRace($race, $spell))
                        <tr>
                            <td>{{ $spell->name }}</td>
                            <td>{{ $spell->cost }}x</td>
                            <td>
                                @if($spell->cooldown > 0)
                                    {{ $spell->cooldown }} hours
                                @else
                                    None
                                @endif
                            </td>
                            <td>
                                <ul>
                                    @foreach($spellHelper->getSpellEffectsString($spell) as $effect)
                                        <li>{{ ucfirst($effect) }}</li>
                                    @endforeach
                                <ul>
                            </td>
                        </tr>
                        @endif
                    @endforeach
                    </tbody>
                </table>

                <h4 class="box-title">Invasion Spells</h4>
                <table class="table table-striped">
                    <colgroup>
                        <col width="200">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Spell</th>
                            <th>Effect</th>
                        </tr>
                    <tbody>
                    @foreach ($spells as $spell)
                        @if($spell->class == 'hostile' and $spell->scope == 'invasion' and $spellCalculator->isSpellAvailableToRace($race, $spell))
                        <tr>
                            <td>{{ $spell->name }}</td>
                            <td>
                                <ul>
                                    @foreach($spellHelper->getSpellEffectsString($spell) as $effect)
                                        <li>{{ ucfirst($effect) }}</li>
                                    @endforeach
                                <ul>
                            </td>
                        </tr>
                        @endif
                    @endforeach
                    </tbody>
                </table>

            </div>
        </div>
    </div>
</div>


<div class="row">
    <a id="spy_ops"></a>
    <div class="col-sm-12 col-md-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Spy Ops</h3>
            </div>
            <div class="box-body">
                <h4>Hostile</h4>
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
                    <tbody>
                    @foreach ($spyops as $spyop)
                        @if($spyop->scope == 'hostile' and $espionageCalculator->isSpyopAvailableToRace($race, $spyop))
                        <tr>
                            <td>
                                {{ $spyop->name }}
                            </td>
                            <td>
                                <ul>
                                    @foreach($espionageHelper->getSpyopEffectsString($spyop) as $effect)
                                        <li>{{ ucfirst($effect) }}</li>
                                    @endforeach
                                </ul>
                            </td>
                        </tr>
                        @endif
                    @endforeach
                    </tbody>
                </table>
                <h4>Theft</h4>
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
                    <tbody>
                    @foreach ($spyops as $spyop)
                        @if($spyop->scope == 'theft' and $espionageCalculator->isSpyopAvailableToRace($race, $spyop))
                        <tr>
                            <td>
                                {{ $spyop->name }}
                            </td>
                            <td>
                                <ul>
                                    @foreach($espionageHelper->getSpyopEffectsString($spyop) as $effect)
                                        <li>{{ ucfirst($effect) }}</li>
                                    @endforeach
                                </ul>
                            </td>
                        </tr>
                        @endif
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>


<div class="row">
    <a id="chronicles"></a>
    <div class="col-sm-12 col-md-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Chronicles</h3>
            </div>
            <div class="box-body">
              @php
                  $factionUrlName = str_replace(' ','-',strtolower($race->name));
                  $alignments = ['good' => 'commonwealth', 'evil' => 'empire', 'independent' => 'independent', 'npc' => 'barbarian-horde'];
                  $alignment = $alignments[$race->alignment];
              @endphp
              <p><a href="https://lounge.odarena.com/chronicles/factions/{{ $alignment }}/{{ $factionUrlName }}/" target="_blank"><i class="fa fa-book"></i> Read the history of {{ $race->name }} in the Chronicles.</a></p>

            </div>
        </div>
    </div>
</div>


</div>
@endsection
