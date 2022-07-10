@if(isset($selectedDominion))
<li data-toggle="tooltip" data-placement="bottom" title="Tick: <strong>{{ number_format($selectedDominion->round->ticks) }}</strong><br><em><small class='text-muted'>At page load</small></em>">
@else
<li>
@endif
    <a href="{{ route('dominion.status') }}"><span id="ticker-server">{{ date('H:i:s') }}</span></a>
</li>
