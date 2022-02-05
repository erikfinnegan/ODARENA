@extends('layouts.topnav')

@section('content')

@include('partials.chronicles.top-row')

<div class="row">
    <div class="box box-primary">
        <div class="box-header with-border">
            <h3 class="box-title"><i class="ra ra-knight-helmet"></i> Rulers</h3>
        </div>
        <div class="box-body table-responsive no-padding">
            <table class="table table-striped">
                <colgroup>
                    <col>
                    <col width="200">
                    <col width="200">
                </colgroup>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Rounds Played</th>
                        <th>Join Date</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $user)
                        <tr>
                            <td><a href="{{ route('chronicles.ruler', $user->display_name) }}">{{ $user->display_name }}</a></td>
                            <td>{{ number_format($userHelper->getRoundsPlayed($user)) }}</td>
                            <td>{{ $user->created_at->toFormattedDateString() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
