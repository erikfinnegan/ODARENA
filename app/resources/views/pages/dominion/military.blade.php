@extends('layouts.master')

@section('page-header', 'Military')

@section('content')
    <div class="row">

        <div class="col-sm-12 col-md-9">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="ra ra-sword"></i> Military</h3>
                </div>
                <form action="{{ route('dominion.military.train') }}" method="post" role="form">
                    @csrf
                    <div class="box-body table-responsive no-padding">
                        <table class="table">
                            <colgroup>
                                <col>
                                <col width="100">
                                <col width="100">
                                <col width="150">
                                <col width="150">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>Unit</th>
                                    <th class="text-center">OP / DP</th>
                                    <th class="text-center">Trained<br>(Training)</th>
                                    <th class="text-center">Train</th>
                                    <th class="text-center">Cost</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($unitHelper->getUnitTypes() as $unitType)
                                    <tr>
                                        <td>  <!-- Unit Name -->
                                            {!! $unitHelper->getUnitTypeIconHtml($unitType, $selectedDominion->race) !!}
                                            <span data-toggle="tooltip" data-placement="top" title="{{ $unitHelper->getUnitHelpString($unitType, $selectedDominion->race) }}">
                                                {{ $unitHelper->getUnitName($unitType, $selectedDominion->race) }}
                                            </span>
                                        </td>
                                          @if (in_array($unitType, ['unit1', 'unit2', 'unit3', 'unit4']))
                                              @php
                                                  $unit = $selectedDominion->race->units->filter(function ($unit) use ($unitType) {
                                                      return ($unit->slot == (int)str_replace('unit', '', $unitType));
                                                  })->first();

                                                  $offensivePower = $militaryCalculator->getUnitPowerWithPerks($selectedDominion, null, null, $unit, 'offense');
                                                  $defensivePower = $militaryCalculator->getUnitPowerWithPerks($selectedDominion, null, null, $unit, 'defense');

                                                  $hasDynamicOffensivePower = $unit->perks->filter(static function ($perk) {
                                                      return starts_with($perk->key, ['offense_from_', 'offense_staggered_', 'offense_vs_']);
                                                  })->count() > 0;
                                                  $hasDynamicDefensivePower = $unit->perks->filter(static function ($perk) {
                                                      return starts_with($perk->key, ['defense_from_', 'defense_staggered_', 'defense_vs_']);
                                                  })->count() > 0;
                                              @endphp
                                              <td class="text-center">  <!-- OP / DP -->
                                                  @if ($offensivePower === 0)
                                                      <span class="text-muted">0</span>
                                                  @else
                                                      {{ (strpos($offensivePower, '.') !== false) ? number_format($offensivePower, 2) : number_format($offensivePower) }}{{ $hasDynamicOffensivePower ? '*' : null }}
                                                  @endif
                                                  &nbsp;/&nbsp;
                                                  @if ($defensivePower === 0)
                                                      <span class="text-muted">0</span>
                                                  @else
                                                      {{ (strpos($defensivePower, '.') !== false) ? number_format($defensivePower, 2) : number_format($defensivePower) }}{{ $hasDynamicDefensivePower ? '*' : null }}
                                                  @endif
                                              </td>
                                              <td class="text-center">  <!-- Trained -->
                                                  {{ number_format($militaryCalculator->getTotalUnitsForSlot($selectedDominion, $unit->slot)) }}

                                                  @if($queueService->getTrainingQueueTotalByResource($selectedDominion, "military_{$unitType}") > 0)
                                                  <br>
                                                  ({{ number_format($queueService->getTrainingQueueTotalByResource($selectedDominion, "military_{$unitType}")) }})
                                                  @endif
                                              </td>
                                          @else
                                              <td class="text-center">&mdash;</td>
                                              <td class="text-center">  <!-- If Spy/Wiz/AM -->
                                                  {{ number_format($selectedDominion->{'military_' . $unitType}) }}

                                                  @if($queueService->getTrainingQueueTotalByResource($selectedDominion, "military_{$unitType}") > 0)
                                                  <br>
                                                  ({{ number_format($queueService->getTrainingQueueTotalByResource($selectedDominion, "military_{$unitType}")) }})
                                                  @endif
                                              </td>
                                              @endif
                                        <td class="text-center">  <!-- Train -->
                                          @if ($selectedDominion->race->getPerkValue('cannot_train_spies') and $unitType == 'spies')
                                            &mdash;
                                          @elseif ($selectedDominion->race->getPerkValue('cannot_train_wizards') and $unitType == 'wizards')
                                            &mdash;
                                          @elseif ($selectedDominion->race->getPerkValue('cannot_train_archmages') and $unitType == 'archmages')
                                            &mdash;
                                          @elseif ($selectedDominion->race->getUnitPerkValueForUnitSlot(intval($unitType), 'cannot_be_trained'))
                                            &mdash;
                                          @else
                                            <input type="number" name="train[military_{{ $unitType }}]" class="form-control text-center" placeholder="{{ number_format($trainingCalculator->getMaxTrainable($selectedDominion)[$unitType]) }}" min="0" max="" size="8" style="min-width:5em;" value="{{ old('train.' . $unitType) }}" {{ $selectedDominion->isLocked() ? 'disabled' : null }}>
                                          @endif
                                        </td>

                                        <td class="text-center">  <!-- Cost -->
                                            @php
                                                // todo: move this shit to view presenter or something
                                                $labelParts = [];

                                                foreach ($trainingCalculator->getTrainingCostsPerUnit($selectedDominion)[$unitType] as $costType => $value) {

                                                  # Only show resource if there is a corresponding cost
                                                  if($value > 0)
                                                  {

                                                    switch ($costType) {
                                                        case 'platinum':
                                                            $labelParts[] = number_format($value) . ' platinum';
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

                                                        case 'gem':
                                                            $labelParts[] =  number_format($value) . ' ' . str_plural('gem', $value);
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
                                                            $labelParts[] =  number_format($value) . ' ' . str_plural('Soul', $value);
                                                            break;

                                                        case 'unit1':
                                                        case 'unit2':
                                                        case 'unit3':
                                                        case 'unit4':
                                                            $labelParts[] =  number_format($value) . ' ' . str_plural($unitHelper->getUnitName($costType, $selectedDominion->race), $value);
                                                            break;

                                                        case 'morale':
                                                            $labelParts[] =  number_format($value) . '% morale';
                                                            break;

                                                        case 'wild_yeti':
                                                            $labelParts[] =  number_format($value) . ' ' . str_plural('wild yeti', $value);
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

                                                        default:
                                                            break;
                                                        }

                                                    } #ENDIF
                                                }

                                                echo implode(',<br>', $labelParts);
                                            @endphp
                                        </td>
                                    </tr>
                                @endforeach

                            </tbody>
                        </table>
                    </div>
                    <div class="box-footer">
                        <button type="submit" class="btn btn-primary" {{ $selectedDominion->isLocked() ? 'disabled' : null }}>
                          @if ($selectedDominion->race->name == 'Growth')
                          Mutate
                          @elseif ($selectedDominion->race->name == 'Myconid')
                          Grow
                          @elseif ($selectedDominion->race->name == 'Swarm')
                          Hatch
                          @else
                          Train
                          @endif
                        </button>
                        <div class="pull-right">
                          @if ($selectedDominion->race->name == 'Growth')
                            You have <strong>{{ number_format($selectedDominion->military_draftees) }}</strong> amoeba available to mutate.
                          @elseif ($selectedDominion->race->name == 'Myconid')
                            You have <strong>{{ number_format($selectedDominion->military_draftees) }}</strong> sporelings available to grow.
                          @elseif ($selectedDominion->race->name == 'Swarm')
                            You have <strong>{{ number_format($selectedDominion->military_draftees) }}</strong> cocoons available to hatch.
                          @else
                            You have <strong>{{ number_format($selectedDominion->military_draftees) }}</strong> {{ str_plural('draftee', $selectedDominion->military_draftees) }} available to train.
                          @endif

                          @if ($selectedDominion->race->name == 'Snow Elf')
                          <br> You also have <strong>{{ number_format($selectedDominion->resource_wild_yeti) }}</strong>  wild yeti trapped.
                          @endif

                          @if ($selectedDominion->race->name == 'Demon')
                          <br> You also have <strong>{{ number_format($selectedDominion->resource_soul) }}</strong> souls collected.
                          @endif

                          @if ($selectedDominion->race->name == 'Norse')
                          <br> You also have <strong>{{ number_format($selectedDominion->resource_champion) }}</strong> legendary champions awaiting.
                          @endif

                          @if ($militaryCalculator->getRecentlyInvadedCount($selectedDominion) and $selectedDominion->race->name == 'Sylvan')
                          <br> You were recently invaded, enraging your Spriggan and Leshy.
                          @endif
                        </div>
                    </div>
                </form>
            </div>

        </div>

        <div class="col-sm-12 col-md-3">

            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">Information</h3>
                    <a href="{{ route('dominion.advisors.military') }}" class="pull-right">Military Advisor</a>
                </div>
                <div class="box-body">
                    @if ($selectedDominion->race->name == 'Growth')
                    <p>Here you can mutate your amoeba into military units. Mutating Abscess and Blisters take <b>9 ticks</b> to process, while mutating Cysts and Ulcers take <b>12 ticks</b>.</p>
                    <p>You have {{ number_format($selectedDominion->military_draftees) }} amoeba.</p>

                    @elseif ($selectedDominion->race->name == 'Myconid')
                    <p>Here you can grow your sporelings into Fruitbodies, which can then be grown into Mushrooms. The Mushrooms can be further trained into the mystical Psilocybe or the mighty Amanita.</p>
                    <p>It takes three ticks to grow Fruitbodies, six ticks to grow Mushrooms, nine ticks to grow a Psilocybe, and 12 ticks to grow an Amanita.</p>
                    <p>You have {{ number_format($selectedDominion->military_draftees) }} sporelings.</p>

                    @elseif ($selectedDominion->race->name == 'Swarm')
                    <p>Here you can hatch your cocoons into units.</p>
                    <p>You have {{ number_format($selectedDominion->military_draftees) }} cocoons.</p>

                    @else
                    <p>Here you can train your draftees into stronger military units. Training specialist units take <b>9 ticks</b> to process, while training your other units take <b>12 ticks</b>.</p>
                    <p>You have {{ number_format($selectedDominion->resource_platinum) }} platinum, {{ number_format($selectedDominion->resource_ore) }} ore and {{ number_format($selectedDominion->military_draftees) }} {{ str_plural('draftee', $selectedDominion->military_draftees) }}.</p>
                    @endif

                    <p>You may also <a href="{{ route('dominion.military.release') }}">release your troops</a> if you wish.</p>
                </div>
            </div>

            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">Statistics</h3>
                </div>
                <div class="box-body table-responsive no-padding">
                    <table class="table">
                        <colgroup>
                            <col width="50%">
                            <col width="50%">
                        </colgroup>
                        <thead>
                            <tr>
                                <th class="text-center">Population</th>
                                <th class="text-center">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                              @if ($selectedDominion->race->name == 'Growth')
                                <td class="text-center">Cells</td>
                              @elseif ($selectedDominion->race->name == 'Myconid')
                                <td class="text-center">Spores</td>
                              @elseif ($selectedDominion->race->name == 'Swarm')
                                <td class="text-center">Larvae</td>
                              @else
                                <td class="text-center">Peasants</td>
                              @endif
                                <td class="text-center">
                                    {{ number_format($selectedDominion->peasants) }}
                                    ({{ number_format($populationCalculator->getPopulationPeasantPercentage($selectedDominion), 2) }}%)
                                </td>
                            </tr>
                            <tr>
                                <td class="text-center">Military</td>
                                <td class="text-center">
                                    {{ number_format($populationCalculator->getPopulationMilitary($selectedDominion)) }}
                                    ({{ number_format($populationCalculator->getPopulationMilitaryPercentage($selectedDominion), 2) }}%)
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($selectedDominion->race->name !== 'Growth')
            <div class="box">
                <div class="box-header with-border">
                    @if ($selectedDominion->race->name == 'Myconid')
                    <h3 class="box-title">Sporelings</h3>
                    @elseif ($selectedDominion->race->name == 'Swarm')
                    <h3 class="box-title">Cocoons</h3>
                    @else
                    <h3 class="box-title">Draftees</h3>
                    @endif
                </div>
                <form action="{{ route('dominion.military.change-draft-rate') }}" method="post" role="form">
                    @csrf
                    <div class="box-body table-responsive no-padding">
                        <table class="table">
                            <colgroup>
                                <col width="50%">
                                <col width="50%">
                            </colgroup>
                            <tbody>
                                <tr>
                                    @if ($selectedDominion->race->name == 'Myconid')
                                    <td class="text-center">Germination:</td>
                                    @else
                                    <td class="text-center">Draft Rate:</td>
                                    @endif
                                    <td class="text-center">
                                        <input type="number" name="draft_rate" class="form-control text-center"
                                               style="display: inline-block; width: 80px;" placeholder="0" min="0"
                                               max="100"
                                               value="{{ $selectedDominion->draft_rate }}" {{ $selectedDominion->isLocked() ? 'disabled' : null }}>
                                        %
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="box-footer">
                        <button type="submit"
                                class="btn btn-primary" {{ $selectedDominion->isLocked() ? 'disabled' : null }}>Change
                        </button>
                    </div>
                </form>
            </div>
            @endif
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="ra ra-sword"></i> Military Modifiers</h3>
                </div>
                    <div class="box-body table-responsive no-padding">
                        <table class="table">
                            <colgroup>
                                <col width="33%">
                                <col width="33%">
                                <col width="33%">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>Modifier</th>
                                    <th>Offensive</th>
                                    <th>Defensive</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>Total:</strong></td>
                                    <td><strong>{{ number_format(($militaryCalculator->getOffensivePowerMultiplier($selectedDominion) - 1) * 100, 2) }}%</strong></td>
                                    <td><strong>{{ number_format(($militaryCalculator->getDefensivePowerMultiplier($selectedDominion) - 1) * 100, 2) }}%</strong></td>
                                </tr>
                                <tr>
                                    <td>Prestige:</td>
                                    <td>{{ number_format($prestigeCalculator->getPrestigeMultiplier($selectedDominion) * 100, 2) }}%</td>
                                    <td>&mdash;</td>
                                </tr>
                                <tr>
                                    <td>Improvements:</td>
                                    <td>{{ number_format($improvementCalculator->getImprovementMultiplierBonus($selectedDominion, 'forges') * 100, 2) }}%</td>
                                    <td>{{ number_format($improvementCalculator->getImprovementMultiplierBonus($selectedDominion, 'walls') * 100, 2) }}%</td>
                                </tr>
                                <tr>
                                    <td>Advancements:</td>
                                    <td>{{ number_format($selectedDominion->getTechPerkMultiplier('offense') * 100, 2) }}%</td>
                                    <td>&mdash;</td>
                                </tr>
                                <tr>
                                    <td>Spell:</td>
                                    <td>{{ number_format($militaryCalculator->getSpellMultiplier($selectedDominion, null, 'offense') * 100, 2) }}%</td>
                                    <td>{{ number_format($militaryCalculator->getSpellMultiplier($selectedDominion, null, 'defense') * 100, 2) }}%</td>
                                </tr>
                                <tr>
                                    <td>Buildings:</td>
                                    <td>{{ number_format($militaryCalculator->getGryphonNestMultiplier($selectedDominion) * 100, 2) }}%</td>
                                    <td>{{ number_format($militaryCalculator->getGuardTowerMultiplier($selectedDominion) * 100, 2) }}%</td>
                                </tr>
                                <tr>
                                    <td>Faction bonus:</td>
                                    <td>{{ number_format($selectedDominion->race->getPerkMultiplier('offense') * 100, 2) }}%</td>
                                    <td>{{ number_format($selectedDominion->race->getPerkMultiplier('defense') * 100, 2) }}%</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
            </div>


    </div>
@endsection
