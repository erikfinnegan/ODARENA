@extends('layouts.master')

@section('content')

@if ($selectedDominion->race->getPerkValue('cannot_improve_castle'))
<div class="row">
    <div class="col-sm-12 col-md-9">
        <div class="box box-primary">
            <p>{{ $selectedDominion->race->name }} cannot use improvements.</p>
        </div>
    </div>
</div>
@endif
<div class="row">
      <div class="col-sm-12 col-md-9">
          <div class="box box-primary">
              <div class="box-header with-border">
                  <h3 class="box-title"><i class="fa fa-arrow-up fa-fw"></i> Improvements</h3>
              </div>

              <form action="{{ route('dominion.improvements') }}" method="post" role="form">
                  @csrf

                  <input type="hidden" name="imps2" value=1 />

                  <div class="box-body table-responsive no-padding">
                      <table class="table">
                          <colgroup>
                              <col width="150">
                              <col width="150">
                              <col width="50">
                              <col>
                              <col width="100">
                          </colgroup>
                          <thead>
                              <tr>
                                  <th>Improvement</th>
                                  <th colspan="2">Invest</th>
                                  <th>Perks</th>
                                  <th class="text-center">Invested</th>
                              </tr>
                          </thead>
                          <tbody>
                              @foreach ($improvementHelper->getImprovementsByRace($selectedDominion->race) as $improvement)
                                  <tr>
                                      <td><span data-toggle="tooltip" data-placement="top"> {{ $improvement->name }}</span></td>
                                      <td class="text-center">
                                          <input type="number" name="improve[{{ $improvement->key }}]" class="form-control text-center" placeholder="0" min="0" size="8" style="min-width:8em; width:100%;" value="{{ old('improve.' . $improvement->key) }}" {{ $selectedDominion->isLocked() ? 'disabled' : null }}>
                                      </td>
                                      <td>
                                          <button class="btn btn-default improve-max" data-type="{{ $improvement->key }}" type="button" style="width:4em;">Max</button>
                                      </td>
                                      <td>
                                          @foreach($improvement->perks as $perk)
                                              @php
                                                  $improvementPerkMax = $selectedDominion->extractImprovementPerkValues($perk->pivot->value)[0] * (1 + $selectedDominion->getBuildingPerkMultiplier('improvements') + $selectedDominion->getBuildingPerkMultiplier('improvements_capped') + $selectedDominion->getTechPerkMultiplier('improvements') + $selectedDominion->getSpellPerkMultiplier('improvements') + $selectedDominion->race->getPerkMultiplier('improvements_max'));
                                                  $improvementPerkCoefficient = $selectedDominion->extractImprovementPerkValues($perk->pivot->value)[1];

                                                  $spanClass = 'text-muted';

                                                  if($improvementPerkMultiplier = $selectedDominion->getImprovementPerkMultiplier($perk->key))
                                                  {
                                                      $spanClass = '';
                                                  }
                                              @endphp

                                              <span class="{{ $spanClass }}" data-toggle="tooltip" data-placement="top" title="Max: {{ number_format($improvementPerkMax,2) }}%<br>Coefficient: {{ number_format($improvementPerkCoefficient) }}">

                                              @if($improvementPerkMultiplier > 0)
                                                  +{{ number_format($improvementPerkMultiplier * 100, 2) }}%
                                              @else
                                                  {{ number_format($improvementPerkMultiplier * 100, 2) }}%
                                              @endif

                                               {{ $improvementHelper->getImprovementPerkDescription($perk->key) }} <br></span>

                                          @endforeach
                                      </td>
                                      <td class="text-center">{{ number_format($improvementCalculator->getDominionImprovementAmountInvested($selectedDominion, $improvement)) }}</td>
                                  </tr>
                              @endforeach
                                  <tr>
                                      <td colspan="4" class="text-right"><strong>Total</strong></td>
                                      <td class="text-center">{{ number_format($improvementCalculator->getDominionImprovementTotalAmountInvested($selectedDominion)) }}</td>
                                  </tr>

                              @php
                                  $totalSabotaged = 0;
                              @endphp
                              @foreach($queueService->getSabotageQueue($selectedDominion) as $sabotage)
                                  @php
                                  $totalSabotaged += $sabotage->amount;
                                  @endphp
                              @endforeach
                              @if($totalSabotaged > 0)
                                  <tr>
                                      <td colspan="4" class="text-right"><strong>Sabotaged</strong><br><small class="text-muted">Will be restored automatically</small></td>
                                      <td class="text-center">{{ number_format($totalSabotaged) }}</td>
                                  </tr>
                              @endif
                          </tbody>
                      </table>
                  </div>
                  <div class="box-footer">
                      <div class="pull-right">
                          <select name="resource" class="form-control">
                              @foreach($selectedDominion->race->improvement_resources as $resource => $value)
                                  <option value="{{ $resource }}" data-amount="{{ $selectedDominion->{'resource_'.$resource} }}" {{ $selectedDominion->most_recent_improvement_resource  == $resource ? 'selected' : '' }}>{{ ucwords($resource) }}</option>
                              @endforeach
                          </select>
                      </div>

                      <div class="pull-right" style="padding: 7px 8px 0 0">
                          Resource to invest:
                      </div>

                      <button type="submit" class="btn btn-primary" {{ $selectedDominion->isLocked() ? 'disabled' : null }}>Invest</button>
                  </div>
              </form>
          </div>
      </div>

      <div class="col-sm-12 col-md-3">
          <div class="box">
              <div class="box-header with-border">
                  <h3 class="box-title">Information</h3>
              </div>
              <div class="box-body">
                  <p>Invest resources into your improvements to immediately strengthen that part of your dominion. Resources invested are converted to points.</p>
                  <p>The return on investments use an exponential function, which yields less return the more you have invested. The function is based on a coefficient and a maximum.</p>

                  @php
                      $improvementsBonus = 0;
                      $improvementsBonus += $selectedDominion->getBuildingPerkMultiplier('improvements');
                      $improvementsBonus += $selectedDominion->getBuildingPerkMultiplier('improvements_capped');
                      $improvementsBonus += $selectedDominion->getSpellPerkMultiplier('improvements');
                      $improvementsBonus += $selectedDominion->getTechPerkMultiplier('improvements');
                      $improvementsBonus += $selectedDominion->race->getPerkMultiplier('improvements_max');
                  @endphp

                  @if($improvementsBonus > 0)
                      <p>Your improvements are increased by <strong>{{ number_format($improvementsBonus*100,2) }}%</strong>.</p>
                  @endif

                  <table class="table">
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
                              <th>Available</th>
                              <th>Points each</th>
                              <th>Points each (raw)</th>
                              <th>Modifier</th>
                          </tr>
                      </thead>
                      <tbody>
                      @foreach($selectedDominion->race->improvement_resources as $resource => $rawValue)
                          <tr>
                              <td>{{ ucwords($resource) }}</td>
                              <td>{{ number_format($selectedDominion->{'resource_'.$resource}) }}
                              <td>{{ number_format($improvementCalculator->getResourceWorth($resource, $selectedDominion),2) }}</td>
                              <td>{{ number_format($rawValue,2) }}</td>
                              <td>{{ number_format($improvementCalculator->getResourceWorthMultipler($resource, $selectedDominion)*100,2) }}%</td>
                          </tr>
                      @endforeach
                      </tbody>
                  </table>
              </div>
          </div>
      </div>
</div>
@endsection

@push('inline-scripts')
    <script type="text/javascript">
        (function ($) {
            $('.improve-max').click(function(e) {
                var selectedOption = $('select[name=resource] option:selected'),
                    selectedResource = selectedOption.val(),
                    maxAmount = selectedOption.data('amount'),
                    improvementType = $(this).data('type');
                $('input[name^=improve]').val('');
                $('input[name=improve\\['+improvementType+'\\]]').val(maxAmount);
            });
        })(jQuery);
    </script>
@endpush
