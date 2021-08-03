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
                  <table class="table table-condensed table-striped no-border">
                        <colgroup>
                            <col width="150">
                            <col width="100">
                            <col>
                        </colgroup>
                        <thead>
                            <tr>
                                <th>Date and time</th>
                                <th>Event</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach ($history as $event)
                            <tr>
                                <td>{{ $event->created_at }}</td>
                                <td><i class="{!! $historyHelper->getEventIcon($event->event) !!}ra ra-hourglass"></i>  {{ $historyHelper->getEventName($event->event) }}</td>
                                <td>
                                    <table>
                                        <colgroup>
                                            <col width="200">
                                            <col>
                                        </colgroup>
                                        @foreach(json_decode($event->delta, TRUE) as $data => $delta)
                                          <tr>
                                              <td>{{ $data }}</td>
                                              <td>{{ $delta }}</td>
                                          </tr>
                                        @endforeach
                                  </table>
                                </td>
                            </tr>
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
