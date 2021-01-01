<footer class="main-footer">

    <div class="pull-right">

      @if (isset($selectedDominion) and $selectedDominion->round->isActive())
          @php
          $diff = $selectedDominion->round->start_date->subDays(1)->diff(now());
          $roundDay = $selectedDominion->round->start_date->subDays(1)->diffInDays(now());
          $roundDurationInDays = $selectedDominion->round->durationInDays();
          $currentHour = ($diff->h + 1);
          $currentTick = 1+floor(intval(Date('i')) / 15);
          @endphp

          Day <strong>{{ $roundDay }}</strong>/{{ $roundDurationInDays }}, hour <strong>{{ $currentHour }}</strong>, tick <strong>{{ $currentTick }}</strong>.

      @elseif (isset($selectedDominion) and !$selectedDominion->round->hasStarted())

          Round <strong>{{ $selectedDominion->round->number }}</strong> starts in <strong>{{ number_format($hoursUntilRoundStarts) . ' ' . str_plural('hour', $hoursUntilRoundStarts) }}</strong>.

      @endif
      <br>

    </div>

    <i class="ra ra-campfire"></i> <a href="https://lounge.odarena.com/" target="_blank">Lounge</a> | <i class="fa fa-file-text-o"></i> <a href="{{ route('legal.privacypolicy') }}">Privacy Policy</a> / <a href="{{ route('legal.termsandconditions') }}">Terms and Conditions</a> | <i class="fab fa-discord"></i><a href="{{ config('app.discord_invite_link') }}">Discord</a>

</footer>
