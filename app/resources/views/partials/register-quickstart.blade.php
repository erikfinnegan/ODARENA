<div class="col-md-{{ $bootstrapColWidth }}">
    <label class="btn btn-block">
        <div class="box">
            <div class="box-header with-border">
                <input type="radio" id="quickstart" name="quickstart" value="{{ $quickstart->id }}" required>&nbsp;<h4 class="box-title">{{ $quickstart->name }}</h4>
            </div>

            <div class="box-body table-responsive no-padding">
                <table class="table">
                    <colgroup>
                        <col width="25%">
                        <col width="75%">
                    </colgroup>
                    <tbody>
                        <tr>
                            <td class="text-left">Faction</td>
                            <td class="text-left">{{ $quickstart->race->name }} <a href="{{ route('scribes.faction', $quickstart->race->name) }}"><i class="ra ra-scroll-unfurled"></i></a></td>
                        </tr>
                        <tr>
                            <td class="text-left">Offensive Power</td>
                            <td class="text-left">{{ number_format($quickstart->offensive_power) }} <em>(est.)</em></td>
                        </tr>
                        <tr>
                            <td class="text-left">Defensive Power</td>
                            <td class="text-left">{{ number_format($quickstart->defensive_power) }} <em>(est.)</em></td>
                        </tr>
                        <tr>
                            <td class="text-left">Deity</td>
                            <td class="text-left">{{ isset($quickstart->deity) ? ($quickstart->deity->name . ' (' . number_format($quickstart->devotion_ticks) . ' ' . str_plural('tick', $quickstart->devotion_ticks) . ' devotion)' ): 'none' }}</td>
                        </tr>
                        <tr>
                            <td class="text-left">Ticks</td>
                            <td class="text-left">{{ number_format($quickstart->protection_ticks) . ' (protection ' . str_plural('tick', $quickstart->protection_ticks) . ' remaining)'  }}</td>
                        </tr>
                        <tr>
                            <td class="text-left">Land</td>
                            <td class="text-left">{{ number_format(array_sum($quickstart->land)) }}</td>
                        </tr>
                        <tr>
                            <td class="text-left">Peasants</td>
                            <td class="text-left">{{ number_format($quickstart->peasants) }}</td>
                        </tr>
                        <tr>
                            <td class="text-left">Prestige</td>
                            <td class="text-left">{{ number_format($quickstart->prestige) }}</td>
                        </tr>
                        <tr>
                            <td class="text-left">XP</td>
                            <td class="text-left">{{ number_format($quickstart->xp) }}</td>
                        </tr>
                        @if($quickstart->draft_rate != 50)
                            <tr>
                                <td class="text-left">Draft rate</td>
                                <td class="text-left">{{ number_format($quickstart->draft_rate) }}%</td>
                            </tr>
                        @endif

                        @if($quickstart->morale != 100)
                            <tr>
                                <td class="text-left">Morale</td>
                                <td class="text-left">{{ number_format($quickstart->morale) }}%</td>
                            </tr>
                        @endif

                        @if($quickstart->spy_strength != 100)
                            <tr>
                                <td class="text-left">Spy strength</td>
                                <td class="text-left">{{ number_format($quickstart->spy_strength) }}%</td>
                            </tr>
                        @endif

                        @if($quickstart->wizard_strength != 100)
                            <tr>
                                <td class="text-left">Wizard strength</td>
                                <td class="text-left">{{ number_format($quickstart->wizard_strength) }}%</td>
                            </tr>
                        @endif


                        @if(!empty($quickstart->buildings))
                            <tr>
                                <td class="text-left">Buildings</td>
                                <td class="text-left">
                                    <ul>
                                    @foreach($quickstart->buildings as $buildingKey => $amount)
                                        @php
                                            $building = OpenDominion\Models\Building::where('key', $buildingKey)->first();
                                        @endphp
                                        <li>{{ $building->name }}: {{ number_format($amount) }}</li>
                                    @endforeach
                                    </ul>
                                </td>
                            </tr>
                        @endif

                        @if(!empty($quickstart->improvements))
                            <tr>
                                <td class="text-left">Improvements</td>
                                <td class="text-left">
                                    <ul>
                                    @foreach($quickstart->improvements as $improvementKey => $amount)
                                        @php
                                            $improvement = OpenDominion\Models\Improvement::where('key', $improvementKey)->first();
                                        @endphp
                                        <li>{{ $improvement->name }}: {{ number_format($amount) }} points</li>
                                    @endforeach
                                    </ul>
                                </td>
                            </tr>
                        @endif

                        @if(!empty($quickstart->land))
                            <tr>
                                <td class="text-left">Land</td>
                                <td class="text-left">
                                    <ul>
                                    @foreach($quickstart->land as $landType => $amount)
                                        <li>{{ ucwords($landType) }}: {{ number_format($amount) }}</li>
                                    @endforeach
                                    </ul>
                                </td>
                            </tr>
                        @endif

                        @if(!empty($quickstart->resources))
                            <tr>
                                <td class="text-left">Resources</td>
                                <td class="text-left">
                                    <ul>
                                    @foreach($quickstart->resources as $resourceKey => $amount)
                                        @php
                                            $resource = OpenDominion\Models\Resource::where('key', $resourceKey)->first();
                                        @endphp
                                        <li>{{ $resource->name }}: {{ number_format($amount) }}</li>
                                    @endforeach
                                    </ul>
                                </td>
                            </tr>
                        @endif

                        @if(!empty($quickstart->spells))
                            <tr>
                                <td class="text-left">Spells (active)</td>
                                <td class="text-left">
                                    <ul>
                                    @foreach($quickstart->spells as $spellKey => $amount)
                                        @php
                                            $spell = OpenDominion\Models\Spell::where('key', $spellKey)->first();
                                        @endphp
                                        <li>{{ $spell->name }}: {{ number_format($amount) . ' ' . str_plural('tick', $amount) }}</li>
                                    @endforeach
                                    </ul>
                                </td>
                            </tr>
                        @endif

                        @if(!empty($quickstart->cooldown))
                            <tr>
                                <td class="text-left">Spells (cooldown)</td>
                                <td class="text-left">
                                    <ul>
                                    @foreach($quickstart->cooldown as $spellKey => $amount)
                                        @php
                                            $spell = OpenDominion\Models\Spell::where('key', $spellKey)->first();
                                        @endphp
                                        <li>{{ $spell->name }}: {{ number_format($amount) . ' ' . str_plural('tick', $amount) }} cooldown</li>
                                    @endforeach
                                    </ul>
                                </td>
                            </tr>
                        @endif

                        @if(!empty($quickstart->units))
                            <tr>
                                <td class="text-left">Units</td>
                                <td class="text-left">
                                    <ul>
                                    @foreach($quickstart->units as $unitKey => $amount)
                                        @if($amount > 0)
                                            @php
                                                if(in_array($unitKey, ['unit1','unit2','unit3','unit4']))
                                                {
                                                    $slot = (int)str_replace('unit','',$unitKey);

                                                    $unit = $quickstart->race->units->filter(function ($unit) use ($slot) {
                                                        return ($unit->slot === $slot);
                                                    })->first();
                                                    $unitName = $unit->name;
                                                }
                                                elseif(in_array($unitKey, ['spies','wizards','archmages']))
                                                {
                                                    $unitName = ucwords($unitKey);
                                                }
                                                else
                                                {
                                                    $unitName = $raceHelper->getDrafteesTerm($quickstart->race);
                                                }
                                            @endphp
                                            <li>{{ $unitName }}: {{ number_format($amount) }}</li>
                                        @endif
                                    @endforeach
                                    </ul>
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </label>
</div>