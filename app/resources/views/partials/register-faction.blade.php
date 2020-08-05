<div class="col-xs-12">
    <label class="btn btn-block" style="border: 1px solid #d2d6de; margin: 5px 0px; white-space: normal;">
        <div class="row text-left">
            <div class="col-lg-3">
                <p>
                    @if($roundsPlayed < $race->getPerkValue('min_rounds_played'))
                    <input type="radio" name="race" value="{{ $race->id }}" autocomplete="off" {{ (old('race') == $race->id) ? 'checked' : null }}>
                    @else
                    <input type="radio" name="race" value="{{ $race->id }}" autocomplete="off" {{ (old('race') == $race->id) ? 'checked' : null }} required>
                    @endif
                    <strong>{{ $race->name }}</strong>
                    &nbsp;&mdash;&nbsp;
                <a href="{{ route('scribes.faction', $race->name) }}">Scribes</a>
                @if($roundsPlayed < 2 and $raceHelper->isBeginnerFriendly($race))
                    &nbsp;&mdash;&nbsp;
                    <span class="label label-success">Beginner Friendly</span>
                @endif
                </p>
                <ul>
                  <li>Currently:&nbsp;
                  @if(isset($countRaces[$race->name]))
                    {{ number_format($countRaces[$race->name]) }}
                  @else
                  0
                  @endif
                  @if($race->getPerkValue('max_per_round') > 0)
                  (Max {{ $race->getPerkValue('max_per_round') }} per round)
                  @endif
                  </li>
                </ul>
            </div>

            <div class="col-sm-3">
                <ul>
                    <li>Attacking: {{ $raceHelper->getRacePlayStyleString($race->attacking) }}</li>
                    <li>Converting: {{ $raceHelper->getRacePlayStyleString($race->converting) }}</li>
                    <li>Exploring: {{ $raceHelper->getRacePlayStyleString($race->exploring) }}</li>
                </ul>
            </div>

            @if($race->getPerkValue('min_rounds_played') > 0)
            <div class="col-sm-4">
                  <p><em>You must have played at least {{ number_format($race->getPerkValue('min_rounds_played')) }} rounds to play {{ $race->name }}.</em></p>
            </div>
            @endif
        </div>
    </label>
</div>
