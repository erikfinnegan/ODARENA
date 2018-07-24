@extends('layouts.master')

@section('page-header', "Register to round {$round->number} ({$round->league->description})")

@section('content')
    <div class="box box-primary">
        <div class="box-header with-border">
            <h3 class="box-title">Register to round {{ $round->name }} (#{{ $round->number }})</h3>
        </div>
        <form action="{{ route('round.register', $round) }}" method="post" class="form-horizontal" role="form">
            @csrf

            <div class="box-body">

                <!-- Dominion Name -->
                <div class="form-group">
                    <label for="dominion_name" class="col-sm-3 control-label">Dominion Name</label>
                    <div class="col-sm-9">
                        <input type="text" name="dominion_name" id="dominion_name" class="form-control" placeholder="Dominion Name" required autofocus>
                        <p class="help-block">Your dominion name is shown when viewing and interacting with other players.</p>
                    </div>
                </div>

                <!-- Ruler Name -->
                <div class="form-group">
                    <label for="ruler_name" class="col-sm-3 control-label">Ruler Name</label>
                    <div class="col-sm-9">
                        <input type="text" name="ruler_name" id="ruler_name" class="form-control" placeholder="{{ Auth::user()->display_name }}">
                        <p class="help-block">This is your personal alias in the round which will be shown to your realmies. Defaults to your display name '{{ Auth::user()->display_name }}' if omitted.</p>
                    </div>
                </div>

                <!-- Race -->
                <div class="form-group">
                    <label for="race" class="col-sm-3 control-label">Race</label>
                    <div class="col-sm-9">
                        <div class="row">

                            <div class="col-xs-6">
                                <div class="text-center">
                                    <strong>Good Aligned Races</strong>
                                </div>
                                <div class="row">
                                    @foreach ($races->filter(function ($race) { return $race->alignment === 'good'; }) as $race)
                                        <div class="col-xs-6">
                                            <label class="btn btn-block">
                                                <p>
                                                    <input type="radio" name="race" value="{{ $race->id }}" autocomplete="off">
                                                    <strong>{{ $race->name }}</strong>
                                                </p>
                                                {!! $raceHelper->getRaceDescriptionHtml($race) !!}
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="col-xs-6">
                                <div class="text-center">
                                    <strong>Evil Aligned Races</strong>
                                </div>
                                <div class="row">
                                    @foreach ($races->filter(function ($race) { return $race->alignment === 'evil'; }) as $race)
                                        <div class="col-xs-6">
                                            <label class="btn btn-block">
                                                <p>
                                                    <input type="radio" name="race" value="{{ $race->id }}" autocomplete="off">
                                                    <strong>{{ $race->name }}</strong>
                                                </p>
                                                {!! $raceHelper->getRaceDescriptionHtml($race) !!}
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- Realm -->
                <div class="form-group">
                    <label for="realm" class="col-sm-3 control-label">Realm</label>
                    <div class="col-sm-9">
                        <select name="realm" class="form-control" required>
                            <option value="random">Put me in a random realm</option>
                            <option value="pack">Pack</option>
                        </select>
                    </div>
                </div>

                <!-- Pack -->
                <div class="form-group">
                    <label for="pack_name" class="col-sm-3 control-label">Pack name</label>
                    <div class="col-sm-9">
                        <input type="input" name="pack_name" id="pack_name" class="form-control" placeholder="Pack name">
                    </div>
                    <label for="pack_password" class="col-sm-3 control-label">Pack password</label>
                    <div class="col-sm-9">
                        <input type="password" name="pack_password" id="pack_password" class="form-control" placeholder="Pack password">
                    </div>
                    <label for="create_pack" class="col-sm-3 control-label">Create new pack</label>
                    <div class="col-sm-9">
                        <input type="checkbox" name="create_pack" id="create_pack" >
                    </div>
                    <label for="pack_size" class="col-sm-3 control-label">Pack size</label>
                    <div class="col-sm-9">
                        <select name="pack_size" class="form-control">
                        @for ($i = 2; $i < $round->pack_size; $i++)
                            <option value="{{$i}}">{{$i}}</option>
                        @endfor
                        </select>
                    </div>
                </div>

            </div>

            <div class="box-footer">
                <button type="submit" class="btn btn-primary">Register</button>
            </div>

        </form>
    </div>
@endsection
