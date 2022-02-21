Cost: <span class="text-primary" data-toggle="tooltip" data-placement="top" title="Base mana cost">{{ number_format($spellCalculator->getManaCost($selectedDominion, $spell->key)) }}</span>

@if($spell->class == 'passive')
 / Duration: <span class="text-primary" data-toggle="tooltip" data-placement="top" title="Base duration">{{ number_format($spell->duration) . ' ' . str_plural('tick', $spell->duration)}}</span>
@endif

@if($spell->cooldown > 0)
 / Cooldown: <span class="text-info" data-toggle="tooltip" data-placement="top" title="Cooldown until spell can be cast again">{{ number_format($spell->duration) . ' ' . str_plural('tick', $spell->duration)}}</span>
@endif
