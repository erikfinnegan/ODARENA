@extends('layouts.topnav')
@section('title', "Scribes | Quickstarts")

@section('content')
@include('partials.scribes.nav')


<div class="row">

    <div class="col-sm-12 col-md-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h2 class="box-title">Quickstarts</h2>
            </div>
        </div>
    </div>

</div>
<div class="row">
    <div class="col-md-12 col-md-12">
        <div class="box box-warning">
            <div class="box-header with-border">
                <h4 class="box-title">The Commonwealth</h4>
            </div>
            <table class="table table-striped" style="margin-bottom: 0">
                <colgroup>
                    <col>
                    <col width="120">
                    <col width="120">
                    <col width="120">
                    <col width="120">
                </colgroup>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Faction</th>
                        <th>Title</th>
                        <th>Deity</th>
                        <th>Remaing Ticks</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($quickstarts as $quickstart)
                        <tr>
                            <td><a href="{{ route('scribes.quickstart', $quickstart->id) }}">{{ $quickstart->name }}</a></td>
                            <td>{{ $quickstart->race->name }}</td>
                            <td>{{ $quickstart->title->name }}</td>
                            <td>{{ isset($quickstart->deity) ? $quickstart->deity->name : 'None' }}</td>
                            <td>{{ $quickstart->protection_ticks }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    </div>
</div>
@endsection
