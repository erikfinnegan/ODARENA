@extends('layouts.topnav')

@section('content')
    @include('partials.scribes.nav')
    <div class="box box-primary">
        <div class="box-header with-border">
            <h3 class="box-title">Improvements</h3>
        </div>
        <div class="box-body">
            <div class="row">
            </div>
        </div>
    </div>

    <div class="box">
        <div class="box-header with-border">
            <h3 class="box-title">Improvements</h3>
        </div>

        <div class="box-body table-responsive">
            <div class="row">
                <div class="col-md-12">
                  <table class="table table-striped">
                      <colgroup>
                          <col width="200">
                          <col>
                      </colgroup>
                      <thead>
                          <tr>
                              <th>Improvement</th>
                              <th>Perks</th>
                          </tr>
                      </thead>
                      @foreach ($improvements as $improvement)
                          <tr>
                              <td>
                                  {{ $improvement->name }}
                                  {!! $improvementHelper->getExclusivityString($improvement) !!}
                              </td>
                              <td>
                                  {!! $improvementHelper->getImprovementDescription($improvement) !!}
                              </td>
                          </tr>
                      @endforeach
                  </table>
                </div>
            </div>
        </div>
    </div>
@endsection
