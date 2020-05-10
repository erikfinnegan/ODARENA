<div class="box box-primary">
    <div class="box-body no-padding">
        <div class="row">
            <div class="col-md-12 col-md-12">
                <div class="navbar-collapse">
                    <ul class="nav navbar-nav scribes-menu">
                        <li class="{{ Route::is('scribes.factions') ? 'active' : null }}"><a href="{{ route('scribes.factions') }}">Factions</a></li>
                        <li class="{{ Route::is('scribes.construction') ? 'active' : null }}"><a href="{{ route('scribes.construction') }}">Construction</a></li>
                        <li class="{{ Route::is('scribes.espionage') ? 'active' : null }}"><a href="{{ route('scribes.espionage') }}">Espionage</a></li>
                        <li class="{{ Route::is('scribes.magic') ? 'active' : null }}"><a href="{{ route('scribes.magic') }}">Magic</a></li>
                        <li class="{{ Route::is('scribes.titles') ? 'active' : null }}"><a href="{{ route('scribes.titles') }}">Titles</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
