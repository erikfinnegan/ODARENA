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
                    <a href="#resources">Resources</a> |
                    <a href="#buildings">Buildings</a> |
                    <a href="#improvements">Improvements</a> |
                    @if($raceHelper->hasLandImprovements($race))
                        <a href="#land_improvements">Land Perks</a> |
                    @endif
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
                                        {{ (strpos($unit->power_offense, '.') !== false) ? number_format($unit->power_offense, 2) : number_format($unit->power_offense) }}
                                        /
                                        {{ (strpos($unit->power_defense, '.') !== false) ? number_format($unit->power_defense, 2) : number_format($unit->power_defense) }}
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
                                            // todo: move this shit to view presenter or something
                                            $labelParts = [];

                                            foreach($unit->cost as $costType => $value)
                                            {
                                                # Only show resource if there is a corresponding cost
                                                if($value != 0)
                                                {
                                                    switch ($costType)
                                                    {
                                                        case 'gold':
                                                            $labelParts[] = number_format($value) . ' gold';
                                                            break;

                                                        case 'ore':
                                                            $labelParts[] = number_format($value) . ' ore';
                                                            break;

                                                        case 'food':
                                                            $labelParts[] =  number_format($value) . ' food';
                                                            break;

                                                        case 'mana':
                                                            $labelParts[] =  number_format($value) . ' mana';
                                                            break;

                                                        case 'lumber':
                                                            $labelParts[] =  number_format($value) . ' lumber';
                                                            break;

                                                        case 'gems':
                                                            $labelParts[] =  number_format($value) . ' ' . str_plural('gems', $value);
                                                            break;

                                                        case 'prestige':
                                                            $labelParts[] =  number_format($value) . ' Prestige';
                                                            break;

                                                        case 'boat':
                                                            $labelParts[] =  number_format($value) . ' ' . str_plural('boat', $value);
                                                            break;

                                                        case 'champion':
                                                            $labelParts[] =  number_format($value) . ' ' . str_plural('Champion', $value);
                                                            break;

                                                        case 'soul':
                                                            $labelParts[] =  number_format($value) . ' ' . str_plural('soul', $value);
                                                            break;

                                                        case 'blood':
                                                            $labelParts[] =  number_format($value) . ' blood';
                                                            break;

                                                        case 'unit1':
                                                        case 'unit2':
                                                        case 'unit3':
                                                        case 'unit4':
                                                            $labelParts[] =  number_format($value) . ' ' . str_plural($unitHelper->getUnitName($costType, $race), $value);
                                                            break;

                                                        case 'morale':
                                                            $labelParts[] =  number_format($value) . '% morale';
                                                            break;

                                                        case 'peasant':
                                                            $labelParts[] =  number_format($value) . ' peasant';
                                                            break;

                                                        case 'spy':
                                                            $labelParts[] =  number_format($value) . ' ' . str_plural('Spy', $value);
                                                            break;

                                                        case 'wizard':
                                                            $labelParts[] =  number_format($value) . ' ' . str_plural('Wizard', $value);
                                                            break;

                                                        case 'archmage':
                                                            $labelParts[] =  number_format($value) . ' ' . str_plural('Archmage', $value);
                                                            break;

                                                        case 'wizards':
                                                            $labelParts[] = '1 Wizard';
                                                            break;

                                                        case 'spy_strength':
                                                            $labelParts[] =  number_format($value) . '% Spy Strength ';
                                                            break;

                                                        case 'wizard_strength':
                                                            $labelParts[] =  number_format($value) . '% Wizard Strength ';
                                                            break;

                                                        case 'brimmer':
                                                            $labelParts[] =  number_format($value) . ' brimmer';
                                                            break;

                                                        case 'prisoner':
                                                            $labelParts[] =  number_format($value) . ' ' . str_plural('prisoner', $value);
                                                            break;

                                                        case 'horse':
                                                            $labelParts[] =  number_format($value) . ' ' . str_plural('horse', $value);
                                                            break;

                                                        case 'mud':
                                                            $labelParts[] =  number_format($value) . ' mud';
                                                            break;

                                                        case 'swamp_gas':
                                                            $labelParts[] =  number_format($value) . ' swamp gas';
                                                            break;

                                                        default:
                                                            break;
                                                    }
                                                } #ENDIF
                                            }

                                            echo implode(',<br>', $labelParts);
                                        @endphp
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
                      <h3 class="box-title">Traits</h3>
                  </div>
                  <div class="box-body table-responsive no-padding">
                      <table class="table table-striped">
                          <colgroup>
                              <col>
                              <col>
                          </colgroup>
                          <tbody>
                              <tr>
                                  <td>{{ $raceHelper->getPeasantsTerm($race) }} production:</td>
                                  <td>
                                      @php
                                          $x = 0;
                                          $peasantProductions = count($race->peasants_production);
                                      @endphp
                                      @foreach ($race->peasants_production as $resourceKey => $amount)
                                          @php
                                              $resource = OpenDominion\Models\Resource::where('key', $resourceKey)->first();
                                              $x++;
                                          @endphp


                                          <span class="text-green">
                                              @if($x < $peasantProductions)
                                                  {{ number_format($amount,2) }}&nbsp;{{ $resource->name }},
                                              @else
                                                  {{ number_format($amount,2) }}&nbsp;{{ $resource->name }}
                                              @endif
                                          </span>
                                      @endforeach
                                  </td>
                              </tr>
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

      </div>
</div>

<a id="resources"></a>
<div class="row">
    <div class="col-sm-12 col-md-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Resources</h3>
            </div>

            <div class="box-body table-responsive">
                <div class="row">
                    <div class="col-md-12">
                      <table class="table table-striped">
                          <colgroup>
                              <col width="20%">
                              <col width="20%">
                              <col width="20%">
                              <col width="20%">
                              <col width="20%">
                          </colgroup>
                          <thead>
                              <tr>
                                  <th>Resource</th>
                                  <th>Construction</th>
                                  <th>Buy</th>
                                  <th>Sell</th>
                                  <th>Improvement Points</th>
                              </tr>
                          </thead>
                          @foreach ($race->resources as $resourceKey)
                              @php
                                  $resource = OpenDominion\Models\Resource::where('key', $resourceKey)->first();
                              @endphp
                              <tr>
                                  <td>{{ $resource->name }}</td>
                                  <td>{{ in_array($resourceKey, $race->construction_materials) ? 'Yes' : '' }}</td>
                                  <td>{{ $resource->buy ?: 'N/A' }}</td>
                                  <td>{{ $resource->sell ?: 'N/A' }}</td>
                                  <td>{{ isset($race->improvement_resources[$resourceKey]) ? number_format($race->improvement_resources[$resourceKey],2) : 'N/A' }}</td>
                              </tr>
                          @endforeach
                      </table>
                    </div>
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

@if($raceHelper->hasLandImprovements($race))
<a id="land_improvements"></a>
<div class="row">
    <div class="col-sm-12 col-md-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Land Perks</h3>
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
                                  <th>Land Type</th>
                                  <th>Perks</th>
                              </tr>
                          </thead>
                          @foreach ($landHelper->getLandTypes() as $landType)
                              <tr>
                                  <td>
                                      {!! $landHelper->getLandTypeIconHtml($landType) !!}&nbsp;{{ ucwords($landType) }}
                                  </td>
                                  <td>
                                      <ul>
                                      @if(isset($race->land_improvements[$landType]))
                                          @foreach($race->land_improvements[$landType] as $perk => $value)
                                              <li>
                                              {!! $LandImprovementHelper->getPerkDescription($perk, $value) !!}
                                              </li>
                                          @endforeach
                                      @else
                                          &mdash;
                                      @endif
                                      </ul>
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
@endif

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
                                    {{ $spell->cooldown }} ticks
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
                                    {{ $spell->cooldown }} ticks
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
                                    {{ $spell->cooldown }} ticks
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
                                    {{ $spell->cooldown }} ticks
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
                                    {{ $spell->cooldown }} ticks
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
                                    {{ $spell->cooldown }} ticks
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
