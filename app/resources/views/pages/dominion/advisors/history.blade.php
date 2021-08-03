@extends('layouts.master')

@section('content')
    @include('partials.dominion.advisor-selector')

<div class="row">
  <div class="col-sm-12 col-md-12">
      <div class="box box-primary">
          <div class="box-header with-border">
              <h3 class="box-title"><i class="ra ra-book ra-fw"></i> History</h3>
          </div>

          @if ($history->isEmpty())
              <div class="box-body">
                  <p>No recent news.</p>
              </div>
          @else
              <div class="box-body">
                  <table class="table table-condensed no-border">
                        <thead>
                            <tr>
                                <th>Date and time</th>
                                <th>Event</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach ($history as $event)
                            @if(array_sum($event->delta) !== 0)
                                <tr>
                                    <td>{{ $event->created_at }}</td>
                                    <td>{{ $event->event }}</td>
                                    <td>{{ $event->delta }}</td>
                                </tr>
                            @endif
                        @endforeach
                      <tbody>
                  </table>
              </div>
              <div class="box-footer">
                  <div class="pull-right">
                      {{ $history->links() }}
                  </div>
              </div>
          @endif

          </div>
      </div>
</div>
@endsection
