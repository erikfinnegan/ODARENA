<div class="row">
    <div class="box">
        <div class="box-body text-center">
            <p>These are the Chronicles of ODARENA, where history becomes legends.</p>

            <div class="col-sm-4">
                @if(Route::is('chronicles.index') or Route::is('chronicles.rounds'))
                    <h4 class="box-title"><i class="fas fa-book fa-fw"></i> Rounds</h4>
                @else
                    <a href="{{ route('chronicles.rounds') }}">
                        <h4 class="box-title"><i class="fas fa-book fa-fw"></i> Rounds</h4>
                    </a>
                @endif
            </div>

            <div class="col-sm-4">
                @if(Route::is('chronicles.rulers'))
                    <h4 class="box-title"><i class="ra ra-knight-helmet ra-fw"></i> Rulers</h4>
                @else
                <a href="{{ route('chronicles.rulers') }}">
                    <h4 class="box-title"><i class="ra ra-knight-helmet ra-fw"></i> Rulers</h4>
                </a>
                @endif
            </div>

            <div class="col-sm-4">
                @if(Route::is('chronicles.factions'))
                    <h4 class="box-title"><i class="fas fa-flag fa-fw"></i> Factions</h4>
                @else
                <a href="{{ route('chronicles.factions') }}">
                    <h4 class="box-title"><i class="fas fa-flag fa-fw"></i> Factions</h4>
                </a>
                @endif
            </div>

        </div>
    </div>

</div>
