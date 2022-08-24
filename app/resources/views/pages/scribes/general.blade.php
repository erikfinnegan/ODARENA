@extends('layouts.topnav')
@section('title', "Scribes | General")

@section('content')
@include('partials.scribes.nav')

<div class="row">
    <div class="col-md-12 col-md-3">
        <div class="box">
            <ul class="sidebar-menu" data-widget="tree">
                <li><span class="btn btn-block"><a href="#casualties">Casualties</a></span></li>
                <li><span class="btn btn-block"><a href="#conversions">Community</a></span></li>
                <li><span class="btn btn-block"><a href="#conversions">Conversions</a></span></li>
                <li><span class="btn btn-block"><a href="#expeditions">Expeditions</a></span></li>
                <li><span class="btn btn-block"><a href="#governor">Governor</a></span></li>
                <li><span class="btn btn-block"><a href="#morale">Morale</a></span></li>
                <li><span class="btn btn-block"><a href="#prestige">Prestige</a></span></li>
                <li><span class="btn btn-block"><a href="#rounds">Rounds</a></span></li>
                <li><span class="btn btn-block"><a href="#sabotage">Sabotage</a></span></li>
                <li><span class="btn btn-block"><a href="#sorcery">Sorcery</a></span></li>
                <li><span class="btn btn-block"><a href="#theft">Theft</a></span></li>
                <li><span class="btn btn-block"><a href="#ticks">Ticks</a></span></li>
            </ul>
        </div>
    </div>

    <div class="col-sm-12 col-md-9">

        <a id="casualties"></a>
        <div class="col-sm-12 col-md-12">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h2 class="box-title">Casualties</h2>
                </div>
    
                <div class="box-body">
                    <p>Sometimes units die and I think that's sad.</p>
                </div>
            </div>
        </div>
    
        <a id="community"></a>
        <div class="col-sm-12 col-md-12">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h2 class="box-title">Community</h2>
                </div>
    
                <div class="box-body">
                    <p>Come join us on Discord!</p>
                    <p style="padding: 0 20px;">
                        <a href="{{ config('app.discord_invite_link') }}" target="_blank">
                            <img src="{{ asset('assets/app/images/join-the-discord.png') }}" alt="Join the Discord" class="img-responsive" style="max-height: 80px;">
                        </a>
                    </p>
                </div>
            </div>
        </div>

        <a id="conversions"></a>
        <div class="col-sm-12 col-md-12">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h2 class="box-title">Conversions</h2>
                </div>
    
                <div class="box-body">
                    <p>Sometimes units nearly die but they come back as something else.</p>
                </div>
            </div>
        </div>
    
        <a id="expeditions"></a>
        <div class="col-sm-12 col-md-12">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h2 class="box-title">Expeditions</h2>
                </div>
    
                <div class="box-body">
                    <p>Gain land by sending units out on expeditions and claim lands. The amount of land gained is calculated based on your current land size and the amount of Offensive Power you send.</p>
                </div>
            </div>
        </div>
    
        <a id="expeditions"></a>
        <div class="col-sm-12 col-md-12">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h2 class="box-title">Governor</h2>
                </div>
    
                <div class="box-body">
                    <p>Each realm can have a dominion has governor. The governor is able to moderate the realm message board and write a message seen by all dominions on the Status page.</p>
                    <p>In standard rounds, the governor is appointed by voting. Votes can only be changed once every 192 ticks.</p>
                    <p>In deathmatch rounds, governorship is first claimed by the player who first hits another player. If the governor is successfully invaded by another player, the governorship is transferred to that player.</p> 
                    <p>Governors gain 10% extra prestige. Successfully invading a governor gives 20% extra prestige for the attacker (if positive).</p>
                </div>
            </div>
        </div>
    
        <a id="morale"></a>
        <div class="col-sm-12 col-md-12">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h2 class="box-title">Morale</h2>
                </div>
    
                <div class="box-body">
                    <h4>Multiplier</h4>
                    <p>Offensive Power, Military Power, Wizard Ratio, Spy Ratio, and Production are modified by the Morale Multiplier.</p>
                    <p>Morale Multiplier: <code>0.9 + [Morale]/1000</code>. 120% morale yields a Morale Multiplier of: <code>0.9+120/1000=1.02</code>. The already modified power, ratio, or production is multiplied by this multiplier.</p>
                    <p>10,000 raw DP with 25% DP mod and 120% morale yields: <code>10000 * (1+0.25) * (0.9+120/1000)=12,750</code>.</p>

                    <h4>Gains and Losses</h4>
                    <p>Morale is gained and lost primarily through invasions. On invasions, attacker's morale change is calculated as follows:</p>
                    <ul>
                        <li>Where the land ratio is less than 60%: <code>-15 - (60 - [Land Ratio])</code></li>
                        <li>Land ratio greater than 60% and less than 75%: No morale change.</li>
                        <li>75% and up: <code>10 * ([Land Ratio] / 75) * (1 + [Land Ratio] / 100)</code></li>
                    </ul>
                    <p>Defender's morale change is <code>[Attacker Morale Change]*-1</code> for under 60% land ratio and <code>[Attacker Morale Change]*-0.60</code> for other. This calculation is done before attacker's morale modifiers (i.e. perks that increases attacker's morale gains) are applied.</p>

                    <h4>Morale Perks</h4>
                    <p>Morale is calculated in two steps: Base Morale and Morale Multipliers.</p>
                    <p>When a perk increases base morale, it’s an additive bonus added to the 100% standard. For example, one Legate gives <code>100% + 5% = 105%</code> base morale.</p>
                    <p>Multipliers are for example Taverns. 5% bonus from Taverns and no base morale modifiers yields <code>(100% + 0%) * (1.05) = 105%</code> morale.</p>
                    <p>A dominion with 20% bonus from Taverns and 10 units that provide 0.50% morale would have <code>(100% + 10*0.50%) * (1.20) = 126%</code> morale.</p>

                </div>
            </div>
        </div>

        <a id="prestige"></a>
        <div class="col-sm-12 col-md-12">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h2 class="box-title">Prestige</h2>
                </div>

                <div class="box-body">
                    <h4>Multiplier</h4>
                    <p>Offensive Power, Food Production, and Population are modified by the Prestige Multiplier.</p>
                    <p>Prestige Multiplier: <code>[Prestige]/10000</code>.</p>
                    <p>For Offensive Power, the Prestige Multiplier is additive. For Food Production and Population, it's multiplicative.</p> 

                    <h4>Gains and Losses</h4>
                    <p>Everyone starts with 600 prestige. Prestige is gained and lost primarily through invasions. On invasions, attacker's prestige change is calculated as follows:</p>
                    <table class="table table-striped">
                        <colgroup>
                            <col width="30%">
                            <col width="35%">
                            <col width="35%">
                        </colgroup>
                        <thead>
                            <tr>
                                <th></th>
                                <th>Attacker</th>
                                <th>Defender</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>Victory</strong><br><small class="text-muted">Successful invasion 75% or greater</small></td>
                                <td><code>60 * [Land Ratio] * [Net Victory Ratio Multiplier]</code></td>
                                <td><code>-20 * [Land Ratio]</code></td>
                            </tr>

                            <tr>
                                <td><strong>Bottomfeed over 60%</strong><br><small class="text-muted">Successful invasion 60%—75%</small></td>
                                <td><em class="text-muted">No change</em></td>
                                <td><em class="text-muted">No change</em></td>
                            </tr>

                            <tr>
                                <td><strong>Bottomfeed under 60%</strong><br><small class="text-muted">Successful invasion 40%–60%</small></td>
                                <td><code>-20</code></td>
                                <td><em class="text-muted">No change</em></td>
                            </tr>

                            <tr>
                                <td><strong>Bounce</strong><br><small class="text-muted">Unsuccessful invasion, less than 80% of required OP</small></td>
                                <td><code>-20</code></td>
                                <td><em class="text-muted">No change</em></td>
                            </tr>

                            <tr>
                                <td><strong>Raze</strong><br><small class="text-muted">Unsuccessful invasion, land ratio at least 75%, and at least 80% of required OP</small></td>
                                <td><em class="text-muted">No change</em></td>
                                <td><code>+10</code></td>
                            </tr>
                        </tbody>
                    </table>

                    <h4>Prestige Interest</h4>
                    <p>Prestige is also increased passively through Prestige Interest:</p>
                    <p><code>[Prestige] * (max(0, [Dominion Net Victories]) / 40000)</code></p>
                    <p>Decimals of prestige are calculated but not used when calculating any perks originating from prestige. If you have 600.99 prestige, you will have 600 prestige for the purposes of perks.</p>
                </div>
            </div>
        </div>
    
        <a id="sabotage"></a>
        <div class="col-sm-12 col-md-12">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h2 class="box-title">Rounds</h2>
                </div>
    
                <div class="box-body">
                    <p>There are four round types:</p>
                    <table class="table table-striped">
                        <colgroup>
                            <col width="20%">
                            <col width="30%">
                            <col width="30%">
                            <col width="20%">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>Mode</th>
                                <th>Realms</th>
                                <th>Goal</th>
                                <th>Duration</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><i class="fas fa-users fa-fw text-green"></i> Standard</td>
                                <td>Four realms: one per alignment.</td>
                                <td>The first dominion to a certain land size triggers a 48-tick countdown.</td>
                                <td>Indefinite</td>
                            </tr>
                            <tr>
                                <td><i class="fas fa-users fa-fw text-green"></i> Standard (duration)</td>
                                <td>Four realms: one per alignment.</td>
                                <td>The round lasts a certain number of ticks.</td>
                                <td>Fixed</td>
                            </tr>

                            <tr>
                                <td><i class="ra ra-daggers ra-fw text-red"></i> Deathamtch</td>
                                <td>Two realms: Barbarians and Players. In-realm invasions and other operations are enabled.</td>
                                <td>The first dominion to a certain land size triggers a 48-tick countdown.</td>
                                <td>Indefinite</td>
                            </tr>
                            <tr>
                                <td><i class="ra ra-daggers ra-fw text-red"></i> Deathmatch (duration)</td>
                                <td>Two realms: Barbarians and Players. In-realm invasions and other operations are enabled.</td>
                                <td>The round lasts a certain number of ticks.</td>
                                <td>Fixed</td>
                            </tr>
                        </tbody>
                    </table>
                    <p>In Standard and Standard (Duration) rounds, multis are allowed as long as they are all played with equal efforts and are all of the same alignment (same realm).</p>
                    <p>In Deathmatch and Deathmatch (Duration) rounds, multis are not allowed.</p>
                    <p>Unless otherwise announced, even numbered rounds start at 16:00 UTC and odd numbered rounds start at 04:00 UTC.</p>
                </div>
            </div>
        </div>
    
        <a id="sabotage"></a>
        <div class="col-sm-12 col-md-12">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h2 class="box-title">Sabotage</h2>
                </div>
    
                <div class="box-body">
                    <p>Damage is based on a Base Damage defined for each possible <a href="{{ route('scribes.sabotage') }}">Sabotage</a> operation, which can be be increased and decreased through perks.</p>
                    <p>Damage is calculated in the following manner:</p>
                    <ol>
                        <li>Ratio Multiplier: <code>1 + (([Saboteur SPA] - [Target SPA]) / [Saboteur SPA])</code>, min 0, max 1.</li>
                        <li>Target Multiplier: the sum of the target’s sabotage damage suffered perks (for example spells or buildings which lower sabotage damage).</li>
                        <li>Saboteur Multiplier: the sum of the saboteur’s sabotage damage dealt perks (for example spells and improvements which increase sabotage damage).</li>
                    </ol>
                    <p>If base damage is 2, Ratio Multiplier is 1, Target Multiplier is 0.25, Saboteur Multiplier is 1, and you send 5,000 units, damage dealt is: <code>2 * 1 * 0.25 * 1 * 5,000 = 2,500</code>.</p>
                </div>
            </div>
        </div>

        <a id="sorcery"></a>
        <div class="col-sm-12 col-md-12">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h2 class="box-title">Sorcery</h2>
                </div>
    
                <div class="box-body">
                    <ol>
                        <li>Select a target (normal range conditions).</li>
                        <li>Select spell to cast.</li>
                        <li>Select how much Wizard Strength you wish to spend on this sorcery.</li>
                        <li>Cast the spell.</li>
                    </ol>
                    <p>Mana cost is determined by a base mana cost (which, like for traditional spell casting is a value multiplied by current land size), which is multiplied by the wizard strength.</p>
                    <p>For example, if you are 2,000 acres and a spell has a cost multiplier of 0.1x, it will cost 200 mana per 1% Wizard Strength. So if you spend 100% Wizard Strength on that spell, it will cost you <code>2,000 * 0.1 * 100 = 20,000</code> mana.</p>
                    <p>Spell damage is calculated as <code>[Spell Base Damage] * [Sorcery Spell Damage Multiplier]</code>:</p>
                    <ul>
                        <li>Each spell is assigned Base Damage.</li>
                        <li>Sorcery Spell Damage Multiplier is calculated as <code>1 * [Wizard Strength Multiplier] * [Wizard Ratio Multiplier]</code></li>
                        <li>Wizard strength multiplier is <code>max([Wizard Strength], ([Wizard Strength] * (exp([Wizard Strength]/120)-1))</code></li>
                        <li>Wizard Ratio multiplier is <code>1 + (([Caster WPA] - [Target WPA]) / [Caster WPA])</code>, min 0, max 1.5.</li>
                        <li>For auras (passive spells, with a lingering effect), the base damage is the duration of the spell, meaning you can make for example Plague last longer by having stronger wizards or spending extra WS. The multiplier is divided by 20 and spell max duration is capped at 96 ticks.</li>
                    </ul>
                    <p>You need a minimum of 4% Wizard Strength and 0.10 WPA (on offense) to perform sorcery.</p>
                </div>
            </div>
        </div>
    
        <a id="theft"></a>
        <div class="col-sm-12 col-md-12">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h2 class="box-title">Theft</h2>
                </div>
    
                <div class="box-body">
                    <p>Stealing is the coolest crime.</p>
                </div>
            </div>
        </div>
    
        <a id="ticks"></a>
        <div class="col-sm-12 col-md-12">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h2 class="box-title">Ticks</h2>
                </div>
    
                <div class="box-body">
                    <p>The game ticks four times per hour, every 15 minutes at xx:00, xx:15, xx:30, xx:45.</p>
                    <p>When the round begins, there is an immediate tick.</p>
                    <p>You start with 96 protection ticks, during which you can manually tick yourself forward at your own leisure.</p>
                </div>
            </div>
        </div>

    </div>
</div>

</div>
@endsection
