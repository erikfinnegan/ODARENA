@extends('layouts.topnav')

@section('content')

@include('partials.chronicles.top-row')

<div class="row">
    <div class="box box-primary">
        <div class="box-header with-border">
            <h3 class="box-title"><i class="fas fa-flag fa-fw"></i> Factions</h3>
        </div>
        <div class="box-body table-responsive no-padding">
            <table class="table table-striped">
                <colgroup>
                    <col>
                    <col width="200">
                </colgroup>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Dominions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($races as $race)
                        <tr>
                            <td><a href="{{ route('chronicles.faction', $race->name) }}">{{ $race->name }}</a></td>
                            <td>{{ number_format($raceHelper->getDominionCountForRace($race)) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
