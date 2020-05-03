@extends('layouts.master')

@section('page-header', 'The World')

@section('content')
    <div class="row">

        <div class="col-sm-12 col-md-9">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="ra ra-circle-of-circles"></i> {{ $realm->name }} (#{{ $realm->number }})</h3>
                </div>
                <div class="box-body table-responsive no-padding">

                    <table class="table">
                        <colgroup>
                            <col width="50">
                            <col>
                            @if ($isOwnRealm && $selectedDominion->pack !== null)
                                <col width="200">
                            @endif
                            <col width="100">
                            <col width="100">
                            <col width="100">
                        </colgroup>
                        <thead>
                            <tr>
                                <th class="text-center">#</th>
                                <th>Dominion</th>
                                @if ($isOwnRealm && $selectedDominion->pack !== null)
                                    <th class="text-center">Player from Pack</th>
                                @endif
                                <th class="text-center">Faction</th>
                                <th class="text-center">Land</th>
                                <th class="text-center">Networth</th>
                            </tr>
                        </thead>
                        <tbody>
                            @for ($i = 0; $i < $round->realm_size; $i++)
                                @php
                                    $dominion = $dominions->get($i);
                                @endphp

                                @if ($dominion === null)
                                <!--
                                    <tr>
                                        <td>&nbsp;</td>
                                        @if ($isOwnRealm && $selectedDominion->pack !== null)
                                            <td colspan="5"><i>Vacant</i></td>
                                        @else
                                            <td colspan="4"><i>Vacant</i></td>
                                        @endif
                                    </tr>
                                  -->
                                @else
                                  @if ($dominion->is_locked == 1)
                                    <tr style="text-decoration:line-through; color: #666">
                                  @else
                                    <tr>
                                  @endif
                                        <td class="text-center">{{ $i + 1 }}</td>
                                        <td>
                                            @if ($dominion->is_locked == 1)
                                                <i class="ra ra-cancel ra-lg text-grey" title="This dominion has been locked by the administrator."></i>
                                            @endif

                                            @if ($spellCalculator->isSpellActive($dominion, 'rainy_season'))
                                                <i class="fa fa-cloud-showers-heavy fa-lg text-blue" title="Rainy Season"></i>
                                            @endif

                                            @if ($dominion->isMonarch())
                                                <i class="ra ra-queen-crown ra-lg text-red"></i>
                                            @endif

                                            @if ($protectionService->isUnderProtection($dominion))
                                                <i class="ra ra-shield ra-lg text-aqua" title="{{ $dominion->protection_ticks }} protection tick(s) left"></i>
                                            @endif

                                            @if ($guardMembershipService->isEliteGuardMember($dominion))
                                                <i class="ra ra-heavy-shield ra-lg text-yellow" title="Elite Guard"></i>
                                            @elseif ($guardMembershipService->isRoyalGuardMember($dominion))
                                                <i class="ra ra-heavy-shield ra-lg text-green" title="Royal Guard"></i>
                                            @endif

                                            @if ($dominion->id === $selectedDominion->id)
                                                <b>{{ $dominion->name }}</b>
                                            @else
                                                @if ($isOwnRealm)
                                                    {{ $dominion->name }}
                                                @else
                                                    <a href="{{ route('dominion.op-center.show', $dominion) }}">{{ $dominion->name }}</a>
                                                @endif
                                            @endif

                                            @if ($isOwnRealm && $dominion->round->isActive() && $dominion->user->isOnline() and $dominion->id !== $selectedDominion->id)
                                                <span class="label label-success">Online</span>
                                            @endif
                                        </td>
                                        @if ($isOwnRealm && $selectedDominion->pack !== null)
                                            @if (($dominion->pack !== null) && ($dominion->pack->id === $selectedDominion->pack->id))
                                                <td class="text-center">{{ $dominion->user->display_name }}</td>
                                            @else
                                                <td class="text-center"></td>
                                            @endif
                                        @endif
                                        <td class="text-center">
                                            {{ $dominion->race->name }}
                                        </td>
                                        <td class="text-center">{{ number_format($landCalculator->getTotalLand($dominion)) }}</td>
                                        <td class="text-center">{{ number_format($networthCalculator->getDominionNetworth($dominion)) }}</td>
                                    </tr>
                                @endif
                            @endfor
                        </tbody>
                    </table>

                </div>
            </div>
        </div>

        <div class="col-sm-12 col-md-3">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">Information</h3>
                </div>
                <div class="box-body">
                      <div class="row">
                          <div class="col-xs-2">
                            @if($realm->alignment == 'good')
                            <img src="{{ asset('assets/app/images/commonwealth.svg') }}" class="img-responsive" alt="The Commonwealth">
                            @elseif($realm->alignment == 'evil')
                            <img src="{{ asset('assets/app/images/empire.svg') }}" class="img-responsive" alt="The Empire">
                            @elseif($realm->alignment == 'independent')
                            <img src="{{ asset('assets/app/images/independent.svg') }}" class="img-responsive" alt="Independent Dominions">
                            @elseif($realm->alignment == 'npc')
                            <img src="{{ asset('assets/app/images/barbarian.svg') }}" class="img-responsive" alt="The Barbarian Horde">
                            @endif
                          </div>
                          <div class="col-xs-10">
                            <p>This is the
                            @if($realm->alignment == 'good')
                            Commonwealth Realm of <strong>{{ $realm->name }} (#{{ $realm->number }})</strong>.</p>
                            @elseif($realm->alignment == 'evil')
                            Imperial Realm of <strong>{{ $realm->name }} (#{{ $realm->number }})</strong>.</p>
                            @elseif($realm->alignment == 'independent')
                            Independent Realm of <strong>{{ $realm->name }} (#{{ $realm->number }})</strong>.</p>
                            @elseif($realm->alignment == 'npc')
                            <strong>Barbarian Horde</strong>.</p>
                            @endif
                          </div>
                      </div>
                      <div class="row">
                          <div class="col-xs-12">
                            <div class="box-body table-responsive no-padding">
                              <table class="table">
                                  <colgroup>
                                      <col width="50%">
                                      <col width="50%">
                                  </colgroup>
                                  <tr>
                                    <td>Dominions:</td>
                                    <td>{{ number_format($dominions->count()) }}</td>
                                  </tr>
                                  <tr>
                                    <td>Victories:</td>
                                    <td>{{ number_format($realmDominionsStats['victories']) }}</td>
                                  </tr>
                                    <tr>
                                      <td>Prestige:</td>
                                      <td>{{ number_format($realmDominionsStats['prestige']) }}</td>
                                    </tr>
                                  <tr>
                                  <tr>
                                    <td>Current land:</td>
                                    <td>{{ number_format($landCalculator->getTotalLandForRealm($realm)) }} acres</td>
                                  </tr>
                                    <td>Land conquered:</td>
                                    <td>{{ number_format($realmDominionsStats['total_land_conquered']) }} acres</td>
                                  </tr>
                                  <tr>
                                    <td>Land explored:</td>
                                    <td>{{ number_format($realmDominionsStats['total_land_explored']) }} acres</td>
                                  </tr>
                                  <tr>
                                    <td>Land lost:</td>
                                    <td>{{ number_format($realmDominionsStats['total_land_lost']) }} acres</td>
                                  </tr>
                                  <tr>
                                    <td>Networth:</td>
                                    <td>{{ number_format($networthCalculator->getRealmNetworth($realm)) }}</td>
                                  </tr>
                              </table>
                            </div>

                            <p><a href="{{ route('dominion.town-crier', [$realm->number]) }}">View the realm's News</a></p>
                          </div>
                      </div>

                </div>
                @if (($prevRealm !== null) || ($nextRealm !== null))
                    <div class="box-footer">
                        <div class="row">
                            <div class="col-xs-4">
                                @if ($prevRealm !== null)
                                    <a href="{{ route('dominion.realm', $prevRealm->number) }}">&lt; Previous</a><br>
                                    <small class="text-muted">{{ $prevRealm->name }} (# {{  $prevRealm->number }})</small>
                                @endif
                            </div>
                            <div class="col-xs-4">
                                <form action="{{ route('dominion.realm.change-realm') }}" method="post" role="form">
                                    @csrf
                                    <input type="number" name="realm" class="form-control text-center" placeholder="{{ $realm->number }}" min="1" max="{{ $realmCount }}">
                                </form>
                            </div>
                            <div class="col-xs-4 text-right">
                                @if ($nextRealm !== null)
                                    <a href="{{ route('dominion.realm', $nextRealm->number) }}">Next &gt;</a><br>
                                    <small class="text-muted">{{ $nextRealm->name }} (# {{  $nextRealm->number }})</small>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

    </div>
@endsection
