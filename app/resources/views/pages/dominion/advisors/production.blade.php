@extends('layouts.master')

@section('content')
    @include('partials.dominion.advisor-selector')

    <div class="row">
<!-- PRODUCTION DETAILS -->
<div class="col-md-12 col-md-12">
    <div class="box box-primary">
        <div class="box-header with-border">
            <h3 class="box-title"><i class="ra ra-mining-diamonds"></i> Production Details</h3>
        </div>

            <div class="box-body table-responsive no-padding">
                <div class="row">
                    <div class="col-xs-12 col-sm-12">
                        <table class="table">
                            <thead>
                                  <tr>
                                      <th>Resource</th>
                                      <th><span data-toggle="tooltip" data-placement="top" title="Production per tick including modifiers">Production/tick</span></th>
                                      <th><span data-toggle="tooltip" data-placement="top" title="Raw production per tick (not including modifiers)">Raw/tick</span></th>
                                      <th><span data-toggle="tooltip" data-placement="top" title="Modifier for production of this resource (includes morale modifier)">Modifier</span></th>
                                      <th><span data-toggle="tooltip" data-placement="top" title="How much (if any) is lost of this resource per tick in upkeep">Loss/tick</span></th>
                                      <th><span data-toggle="tooltip" data-placement="top" title="Net change per tick">Net/tick</span></th>
                                      <th><span data-toggle="tooltip" data-placement="top" title="How much you currently have">Current</span></th>
                                  </tr>
                            </thead>
                            <tbody>
                                  <tr>
                                      <td>Gold</td>
                                      <td>{{ number_format($productionCalculator->getGoldProduction($selectedDominion)) }}</td>
                                      <td>{{ number_format($productionCalculator->getGoldProductionRaw($selectedDominion)) }}</td>
                                      <td>{{ number_format(($productionCalculator->getGoldProductionMultiplier($selectedDominion)-1)*100, 2) }}%</td>
                                      <td>&mdash;</td>
                                      <td>{{ number_format($productionCalculator->getGoldProduction($selectedDominion)) }}</td>
                                      <td>{{ number_format($selectedDominion->resource_gold) }}</td>
                                  </tr>
                                  <tr>
                                      <td>Food</td>
                                      <td>{{ number_format($productionCalculator->getFoodProduction($selectedDominion)) }}</td>
                                      <td>{{ number_format($productionCalculator->getFoodProductionRaw($selectedDominion)) }}</td>
                                      <td>{{ number_format(($productionCalculator->getFoodProductionMultiplier($selectedDominion)-1)*100, 2) }}%</td>
                                      <td><span data-toggle="tooltip" data-placement="top" title="Food consumption" class="text-red">-{{ number_format($productionCalculator->getFoodConsumption($selectedDominion)) }}</span></td>
                                      <td>
                                          @if($productionCalculator->getFoodNetChange($selectedDominion) > 0)
                                              <span class="text-green">
                                          @else
                                              <span class="text-red">
                                          @endif
                                          {{ number_format($productionCalculator->getFoodNetChange($selectedDominion)) }}
                                          </span>
                                      </td>
                                      <td>{{ number_format($selectedDominion->resource_food) }}</td>
                                  </tr>
                                  <tr>
                                      <td>Lumber</td>
                                      <td>{{ number_format($productionCalculator->getLumberProduction($selectedDominion)) }}</td>
                                      <td>{{ number_format($productionCalculator->getLumberProductionRaw($selectedDominion)) }}</td>
                                      <td>{{ number_format(($productionCalculator->getLumberProductionMultiplier($selectedDominion)-1)*100, 2) }}%</td>
                                      <td>&mdash;</td>
                                      <td>
                                          @if($productionCalculator->getLumberProduction($selectedDominion) > 0)
                                              <span class="text-green">
                                          @else
                                              <span class="text-red">
                                          @endif
                                          {{ number_format($productionCalculator->getLumberProduction($selectedDominion)) }}
                                          </span>
                                      </td>
                                      <td>{{ number_format($selectedDominion->resource_lumber) }}</td>
                                  </tr>
                                  <tr>
                                      <td>Mana</td>
                                      <td>{{ number_format($productionCalculator->getManaProduction($selectedDominion)) }}</td>
                                      <td>{{ number_format($productionCalculator->getManaProductionRaw($selectedDominion)) }}</td>
                                      <td>{{ number_format(($productionCalculator->getManaProductionMultiplier($selectedDominion)-1)*100, 2) }}%</td>
                                      <td>
                                            @if($productionCalculator->getContribution($selectedDominion, 'mana'))
                                                <span class="text-red">-{{ number_format(($productionCalculator->getContribution($selectedDominion, 'mana'))) }}</span>
                                            @else
                                                &mdash;
                                            @endif
                                      </td>
                                      <td>
                                          @if($productionCalculator->getManaNetChange($selectedDominion) > 0)
                                              <span class="text-green">
                                          @else
                                              <span class="text-red">
                                          @endif
                                          {{ number_format($productionCalculator->getManaNetChange($selectedDominion)) }}
                                          </span>
                                      </td>
                                      <td>{{ number_format($selectedDominion->resource_mana) }}</td>
                                  </tr>
                                  <tr>
                                      <td>Ore</td>
                                      <td>{{ number_format($productionCalculator->getOreProduction($selectedDominion)) }}</td>
                                      <td>{{ number_format($productionCalculator->getOreProductionRaw($selectedDominion)) }}</td>
                                      <td>{{ number_format(($productionCalculator->getOreProductionMultiplier($selectedDominion)-1)*100, 2) }}%</td>
                                      <td>&mdash;</td>
                                      <td>{{ number_format($productionCalculator->getOreProduction($selectedDominion)) }}</td>
                                      <td>{{ number_format($selectedDominion->resource_ore) }}</td>
                                  </tr>
                                  <tr>
                                      <td>Gems</td>
                                      <td>{{ number_format($productionCalculator->getGemProduction($selectedDominion)) }}</td>
                                      <td>{{ number_format($productionCalculator->getGemProductionRaw($selectedDominion)) }}</td>
                                      <td>{{ number_format(($productionCalculator->getGemProductionMultiplier($selectedDominion)-1)*100, 2) }}%</td>
                                      <td>&mdash;</td>
                                      <td>{{ number_format($productionCalculator->getGemProduction($selectedDominion)) }}</td>
                                      <td>{{ number_format($selectedDominion->resource_gems) }}</td>
                                  </tr>
                                  <tr>
                                      <td>XP</td>
                                      <td>{{ number_format($productionCalculator->getTechProduction($selectedDominion)) }}</td>
                                      <td>{{ number_format($productionCalculator->getTechProductionRaw($selectedDominion)) }}</td>
                                      <td>{{ number_format(($productionCalculator->getTechProductionMultiplier($selectedDominion)-1)*100, 2) }}%</td>
                                      <td>&mdash;</td>
                                      <td>{{ number_format($productionCalculator->getTechProduction($selectedDominion)) }}</td>
                                      <td>{{ number_format($selectedDominion->resource_tech) }}</td>
                                  </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
    </div>
</div>
<!-- /PRODUCTION DETAILS -->


<!-- SPENDING DETAILS -->
<div class="col-md-12 col-md-12">
    <div class="box box-primary">
        <div class="box-header with-border">
            <h3 class="box-title"><i class="ra ra-book"></i> Spending Details</h3>
        </div>

            <div class="box-body table-responsive no-padding">
                <div class="row">

                    <div class="col-xs-12 col-sm-12">
                        <table class="table">
                            <colgroup>
                                <col>
                                <col width="12.5%">
                                <col width="12.5%">
                                <col width="12.5%">
                                <col width="12.5%">
                                <col width="12.5%">
                                <col width="12.5%">
                                <col width="12.5%">
                            </colgroup>
                            <thead>
                                  <tr>
                                      <th>Resource</th>
                                      <th><span data-toggle="tooltip" data-placement="top" title="How much you have spent of this resource on training units (including spies, wizards, and arch mages)">Training</span></th>
                                      <th><span data-toggle="tooltip" data-placement="top" title="How much you have spent of this resource on buildings">Building</span></th>
                                      <th><span data-toggle="tooltip" data-placement="top" title="How much you have spent of this resource on rezoning land">Rezoning</span></th>
                                      <th><span data-toggle="tooltip" data-placement="top" title="How much you have spent of this resource on exploring land">Exploring</span></th>
                                      <th><span data-toggle="tooltip" data-placement="top" title="How much you have spent of this resource on improvements">Improvements</span></th>
                                      <th><span data-toggle="tooltip" data-placement="top" title="How much of this resource you have bought">Bought</span></th>
                                      <th><span data-toggle="tooltip" data-placement="top" title="How much of this resource you have sold">Sold</span></th>
                                  </tr>
                            </thead>
                            <tbody>
                                  <tr>
                                      <td>Gold</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'gold_training')) }}</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'gold_building')) }}</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'gold_rezoning')) }}</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'gold_exploring')) }}</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'gold_improvements')) }}</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'gold_bought')) }}</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'gold_sold')) }}</td>
                                  </tr>
                                  <tr>
                                      <td>Food</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'food_training')) }}</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'food_building')) }}</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'food_rezoning')) }}</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'food_exploring')) }}</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'food_improvements')) }}</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'food_bought')) }}</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'food_sold')) }}</td>
                                  </tr>
                                  <tr>
                                      <td>Lumber</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'lumber_training')) }}</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'lumber_building')) }}</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'lumber_rezoning')) }}</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'lumber_exploring')) }}</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'lumber_improvements')) }}</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'lumber_bought')) }}</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'lumber_sold')) }}</td>
                                  </tr>
                                  <tr>
                                      <td>Mana</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'mana_training')) }}</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'mana_building')) }}</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'mana_rezoning')) }}</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'mana_exploring')) }}</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'mana_improvements')) }}</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'mana_bought')) }}</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'mana_sold')) }}</td>
                                  </tr>
                                  <tr>
                                      <td>Ore</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'ore_training')) }}</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'ore_building')) }}</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'ore_rezoning')) }}</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'ore_exploring')) }}</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'ore_improvements')) }}</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'ore_bought')) }}</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'ore_sold')) }}</td>
                                  </tr>
                                  <tr>
                                      <td>Gems</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'gems_training')) }}</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'gems_building')) }}</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'gems_rezoning')) }}</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'gems_exploring')) }}</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'gems_improvements')) }}</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'gems_bought')) }}</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'gems_sold')) }}</td>
                                  </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
    </div>
</div>
<!-- /SPENDING DETAILS -->


<!-- MAINTENANCE DETAILS -->
<div class="col-md-12 col-md-12">
    <div class="box box-primary">
        <div class="box-header with-border">
            <h3 class="box-title"><i class="ra ra-book"></i> Additional Details</h3>
        </div>

            <div class="box-body table-responsive no-padding">
                <div class="row">

                    <div class="col-xs-12 col-sm-12">
                        <table class="table">
                            <colgroup>
                                <col>
                                <col width="15%">
                                <col width="15%">
                                <col width="15%">
                                <col width="15%">
                                <col width="15%">
                            </colgroup>
                            <thead>
                                  <tr>
                                      <th>Resource</th>
                                      <th><span data-toggle="tooltip" data-placement="top" title="Salvaged from units lost in combat">Salvaged</span></th>
                                      <th><span data-toggle="tooltip" data-placement="top" title="Plundered from other dominions">Plundered</span></th>
                                      <th><span data-toggle="tooltip" data-placement="top" title="Stolen from other dominions">Stolen</span></th>
                                      <th><span data-toggle="tooltip" data-placement="top" title="Lost to theft or plunder">Lost</span></th>
                                      <th><span data-toggle="tooltip" data-placement="top" title="Mana spent on casting spells">Cast</span></th>
                                  </tr>
                            </thead>
                            <tbody>
                                  <tr>
                                      <td>Gold</td>
                                      <td>&mdash;</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'gold_plundered')) }}</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'gold_stolen')) }}</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'gold_lost')) }}</td>
                                      <td>&mdash;</td>
                                  </tr>
                                  <tr>
                                      <td>Food</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'food_salvaged')) }}</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'food_plundered')) }}</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'food_stolen')) }}</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'food_lost')) }}</td>
                                      <td>&mdash;</td>
                                  </tr>
                                  <tr>
                                      <td>Lumber</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'lumber_salvaged')) }}</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'lumber_plundered')) }}</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'lumber_stolen')) }}</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'lumber_lost')) }}</td>
                                      <td>&mdash;</td>
                                  </tr>
                                  <tr>
                                      <td>Mana</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'mana_salvaged')) }}</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'mana_plundered')) }}</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'mana_stolen')) }}</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'mana_lost')) }}</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'mana_cast')) }}</td>
                                  </tr>
                                  <tr>
                                      <td>Ore</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'ore_salvaged')) }}</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'ore_plundered')) }}</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'ore_stolen')) }}</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'ore_lost')) }}</td>
                                      <td>&mdash;</td>
                                  </tr>
                                  <tr>
                                      <td>Gems</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'gems_salvaged')) }}</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'gems_plundered')) }}</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'gems_stolen')) }}</td>
                                      <td>{{ number_format($statsService->getStat($selectedDominion, 'gems_lost')) }}</td>
                                      <td>&mdash;</td>
                                  </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
    </div>
</div>
<!-- /MAINTENANCE DETAILS -->




@endsection
