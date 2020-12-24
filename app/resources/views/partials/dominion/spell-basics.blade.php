@php
    $manaAfforded = $spellCalculator->getManaCost($selectedDominion, $spell->key) <= $selectedDominion->resource_mana ? 'text-green' : 'text-red';
@endphp

<span data-toggle="tooltip" data-placement="top" title="Mana required to cast spell">M</span>: <span class="{{ $manaAfforded }}">{{ number_format($spellCalculator->getManaCost($selectedDominion, $spell->key)) }}</span>
@if($spell->duration > 0)
  / <span data-toggle="tooltip" data-placement="top" title="Tick duration of effect">{{--T--}}<i class="ra ra-hourglass"></i></span>:
        @if($isActive)
            <span class="text-green">{{ $spellCalculator->getSpellDuration($selectedDominion, $spell->key) }}/{{ $spell->duration }}</span>
        @else
            <span class="text-muted">{{ $spell->duration }}</span>
        @endif
         ticks
@endif
@if($spell->cooldown > 0)
  / <span data-toggle="tooltip" data-placement="top" title="Cooldown until spell can be cast again">CD</span>:
        @if($spellCalculator->isOnCooldown($selectedDominion, $spell))
            <span class="text-red">{{ $spellCalculator->getSpellCooldown($selectedDominion, $spell) }}/{{ $spell->cooldown }}h</span>
        @else
            <span class="text-muted">{{ $spell->cooldown }}h</span>
        @endif
@endif
@if($spell->wizard_strength)
  / <span data-toggle="tooltip" data-placement="top" title="Wizard stregth required to cast spell">WS</span>: {{ $spell->wizard_strength }}%
@endif
