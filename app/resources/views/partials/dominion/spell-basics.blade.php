@php
    $manaAfforded = $spellCalculator->getManaCost($selectedDominion, $spell->key) <= $resourceCalculator->getAmount($selectedDominion, 'mana') ? 'text-green' : 'text-red';
@endphp

<span class="{{ $manaAfforded }}"  data-toggle="tooltip" data-placement="top" title="Mana required to cast spell">{{ number_format($spellCalculator->getManaCost($selectedDominion, $spell->key)) }}</span>
@if($spell->duration > 0)
  / <span data-toggle="tooltip" data-placement="top" title="Duration (ticks)"><i class="fas fa-hourglass-start"></i></span>:
        @if(isset($isActive) and $isActive)
            <span class="text-green">{{ number_format($spellCalculator->getSpellDuration($selectedDominion, $spell->key)) }}/{{ number_format($spell->duration) }}</span>
        @else
            <span class="text-muted">{{ number_format($spell->duration) }}</span>
        @endif
         ticks
@endif
@if($spell->cooldown > 0)
  / <span data-toggle="tooltip" data-placement="top" title="Cooldown until spell can be cast again (ticks)"><i class="fas fa-hourglass-end"></i></i></span>:
        @if($spellCalculator->isOnCooldown($selectedDominion, $spell))
            <span class="text-red">{{ number_format($spellCalculator->getSpellCooldown($selectedDominion, $spell)) }}/{{ number_format($spell->cooldown) }}</span>
        @else
            <span class="text-muted">{{ number_format($spell->cooldown) }}</span>
        @endif
        ticks
@endif
@if($spell->wizard_strength)
  / <span data-toggle="tooltip" data-placement="top" title="Wizard stregth required to cast spell">WS</span>: {{ $spell->wizard_strength }}%
@endif
