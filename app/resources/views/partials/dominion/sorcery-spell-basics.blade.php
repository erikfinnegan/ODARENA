<div class="row">

    <div class="col-sm-12">
        Cost: <span class="text-primary" data-toggle="tooltip" data-placement="top" title="Base mana cost">{{ number_format($spellCalculator->getManaCost($selectedDominion, $spell->key)) }}</span> <small class="text-muted">per 1% WS</small>
    </div>

    @if($spell->class == 'passive')
        <div class="col-sm-12">
            Duration: <span class="text-primary" data-toggle="tooltip" data-placement="top" title="Base duration">{{ number_format($spell->duration) . ' ' . str_plural('tick', $spell->duration)}}</span> <small class="text-muted">(base)</small>
        </div>
    @elseif($spell->class == 'active')
        <div class="col-sm-12">
            Duration: <span class="text-muted" class="text-primary" data-toggle="tooltip" data-placement="top" title="This is an impact spell with immediate damage and no lingering effect">Immediate</span>
        </div>
    @endif

    @if($spell->cooldown > 0)
        <div class="col-sm-12">
            Cooldown: <span class="text-info" data-toggle="tooltip" data-placement="top" title="Cooldown until spell can be cast again">{{ number_format($spell->duration) . ' ' . str_plural('tick', $spell->duration)}}</span>=
        </div>
    @endif
</div>
