@extends('layouts.topnav')

@section('content')
    <div class="box box-primary">
        <div class="box-header with-border">
            <h1 class="box-title"><i class="fas fa-book"></i> Chronicles for Round {{ number_format($round->number) }}: {{ $round->name }}</h1>
        </div>
        <div class="box-body">

            <div class="row">
                <div class="col-md-12 text-center">
                    <h3>Overall</h3>
                </div>
            </div>
            <div class="row form-group">
                <div class="col-sm-6 text-center">
                    <h4>Largest</h4>
                    <a href="{{ route('chronicles.round.type', [$round, 'largest-dominions']) }}">The Largest Dominions</a><br>
                    <a href="{{ route('chronicles.round.type', [$round, 'largest-realms']) }}">The Largest Realms</a><br>
                </div>
                <div class="col-sm-6 text-center">
                    <h4>Strongest</h4>
                    <a href="{{ route('chronicles.round.type', [$round, 'strongest-dominions']) }}">The Strongest Dominions</a><br>
                    <a href="{{ route('chronicles.round.type', [$round, 'strongest-realms']) }}">The Strongest Realms</a><br>
                </div>
            </div>


        </div>
    </div>
@endsection
