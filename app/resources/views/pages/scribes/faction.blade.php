@extends('layouts.topnav')

{{--
@section('page-header', 'Military')
--}}

@section('content')
<div class="row">

    <div class="col-sm-12 col-md-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h2 class="box-title">{{ $race->name }}</h2>
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
                            <th class="text-center">Cost</th>
                        </tr>
                    </thead>
                    <tbody>
                      @foreach ($race->units as $unit)
                          @if(in_array($unit->slot, ['wizards','spies','archmages']))
                              {{ dd($unit) }}
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

                              <td class="text-center">  <!-- Cost -->
                                  @php
                                      // todo: move this shit to view presenter or something
                                      $labelParts = [];

                                      foreach ($trainingCalculator->getTrainingCostsPerUnit($selectedDominion)[$unitType] as $costType => $value) {

                                        # Only show resource if there is a corresponding cost
                                        if($value !== 0)
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
        </div>
      </div>

        <div class="col-sm-12 col-md-3">

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
                            @foreach ($race->perks as $perk)
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
<div class="row">

        <div class="col-sm-12 col-md-5">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">Spells</h3>
                </div>
                <div class="box-body">
                    <p>TBD</p>
                </div>
            </div>
        </div>

        <div class="col-sm-12 col-md-4">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">Spy Ops</h3>
                </div>
                <div class="box-body">
                    <p>TBD</p>
                </div>
            </div>
        </div>

        <div class="col-sm-12 col-md-3">
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
