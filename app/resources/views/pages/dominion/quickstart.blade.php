@extends('layouts.master')
@section('title', 'Quickstart')

@section('content')
<div class="row">

    <div class="col-sm-12 col-md-9">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-fast-forward fa-fw text-orange"></i> Quickstart</h3>
            </div>
            <div class="box-body">
                <div class="row">
                    <div class="col-lg-12">
                        <textarea id="notes" name="notes" rows=20 style="width:100%; font-family:monospace;" name="body" id="body" class="form-control" required {{ $selectedDominion->isLocked() ? 'disabled' : null }}>
                            {{ $quickstart }}
                        </textarea>
                    </div>
                </div>
            </div>
            <div class="box-footer">
                <a href="{{ route(Route::currentRouteName()) }}" class="btn btn-primary">
                    <i class="fas fa-redo fa-fw"></i> Refresh
                </a>
            </div>
        </div>
    </div>

    <div class="col-sm-12 col-md-3">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Information</h3>
            </div>
            <div class="box-body">
                <p>These notes are only visible to you.</p>
            </div>
        </div>
    </div>

</div>

<hr>
<hr>
<hr>
<div class="row">
    <div class="col-sm-12 col-md-9">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-fast-forward fa-fw text-orange"></i> Quickstart</h3>
            </div>
            <div class="box-body">
                <div class="row">
                    <div class="col-lg-12">
                        <textarea id="notes" name="notes" rows=20 style="width:100%; font-family:monospace;" name="body" id="body" class="form-control" required {{ $selectedDominion->isLocked() ? 'disabled' : null }}>
                            {{ $quickstart }}
                        </textarea>
                    </div>
                </div>
            </div>
            <div class="box-footer">
                <a href="{{ route(Route::currentRouteName()) }}" class="btn btn-primary">
                    <i class="fas fa-redo fa-fw"></i> Refresh
                </a>
            </div>
        </div>
    </div>

    <div class="col-sm-12 col-md-3">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Information</h3>
            </div>
            <div class="box-body">
                <p>These notes are only visible to you.</p>
            </div>
        </div>
    </div>
</div>


@endsection

